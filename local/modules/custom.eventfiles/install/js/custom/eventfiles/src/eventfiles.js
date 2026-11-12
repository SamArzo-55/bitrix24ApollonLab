import { ajax, Event } from 'main.core';
import { EventEmitter } from 'main.core.events';
import './eventfiles.css';

const ACTION_GET_URLS = 'custom:eventfiles.fileLink.getUrls';
const LOG_PREFIX = 'custom.eventfiles:';

const SEGMENT_SPLIT_PATTERN = /[\\/:*?"<>|\r\n\u00AB\u00BB\u201C\u201D]+/;

const EXTENSION_PATTERN = /\.[A-Za-z0-9]{1,8}(?![A-Za-z0-9])/g;

const MAX_NAME_LENGTH = 200;
const MAX_WORDS_IN_NAME = 12;
const MAX_NAMES_PER_REQUEST = 100;
const MAX_NAMES_PER_SCAN = 300;
const MAX_FAILURES = 3;

const SKIP_TAGS = new Set(['A', 'SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT', 'BUTTON', 'SELECT', 'OPTION']);

const SCAN_DELAY = 300;

// Bitrix events after which new history content may appear in the deal card.
const GRID_EVENTS = ['Grid::ready', 'Grid::updated'];
const TAB_SHOW_EVENT = 'onEntityDetailsTabShow';
const AJAX_SUCCESS_EVENT = 'onAjaxSuccess';

/**
 * Finds possible file names in text and matches them with known file URLs.
 */
export class FileNameParser
{
	/**
	 * Finds possible file names in text.
	 *
	 * @param {string} text
	 * @returns {Array<{start: number, end: number, name: string}>}
	 */
	static collectCandidates(text)
	{
		const source = String(text).replace(/\u00A0/g, ' ');
		const result = [];
		let offset = 0;

		for (const segment of source.split(SEGMENT_SPLIT_PATTERN))
		{
			const segmentStart = source.indexOf(segment, offset);
			offset = segmentStart + segment.length;

			const wordStarts = [];
			const wordPattern = /[^\s(\[{,;]+/g;
			let wordMatch;
			while ((wordMatch = wordPattern.exec(segment)) !== null)
			{
				wordStarts.push(wordMatch.index);
			}

			let extensionMatch;
			EXTENSION_PATTERN.lastIndex = 0;
			while ((extensionMatch = EXTENSION_PATTERN.exec(segment)) !== null)
			{
				if (!/[A-Za-z]/.test(extensionMatch[0]))
				{
					continue;
				}

				const end = extensionMatch.index + extensionMatch[0].length;
				const starts = wordStarts.filter((start) => start < end).slice(-MAX_WORDS_IN_NAME);

				for (const start of starts)
				{
					const name = segment.slice(start, end);
					if (name.length <= MAX_NAME_LENGTH)
					{
						result.push({ start: segmentStart + start, end: segmentStart + end, name });
					}
				}
			}
		}

		return result;
	}

	/**
	 * Keeps candidates that have a URL; of overlapping ones the longest name wins.
	 *
	 * @param {Array<{start: number, end: number, name: string}>} candidates
	 * @param {(name: string) => string|null|undefined} getUrl
	 * @returns {Array<{start: number, end: number, name: string, url: string}>}
	 */
	static findMentions(candidates, getUrl)
	{
		const found = candidates
			.map((candidate) => ({ ...candidate, url: getUrl(candidate.name) }))
			.filter((candidate) => candidate.url)
			.sort((a, b) => (a.start - b.start) || ((b.end - b.start) - (a.end - a.start)));

		const mentions = [];
		let lastEnd = -1;
		for (const mention of found)
		{
			if (mention.start >= lastEnd)
			{
				mentions.push(mention);
				lastEnd = mention.end;
			}
		}

		return mentions;
	}
}

/**
 * Turns file names in the CRM deal card into Shared Drive links.
 */
export class HistoryFileLinks
{
	fileUrls = new Map();

	seenTextNodes = new WeakMap();

	scanTimer = null;
	failures = 0;

	static start()
	{
		try
		{
			new HistoryFileLinks().init();
		}
		catch (error)
		{
			console.error(`${LOG_PREFIX} init failed`, error);
		}
	}

	init()
	{
		console.info(`${LOG_PREFIX} loaded`);

		GRID_EVENTS.forEach((eventName) => {
			this.runSafely(() => EventEmitter.subscribe(eventName, this.onBitrixEvent));
		});
		this.runSafely(() => BX.addCustomEvent(TAB_SHOW_EVENT, this.onBitrixEvent));
		this.runSafely(() => BX.addCustomEvent(AJAX_SUCCESS_EVENT, this.onBitrixEvent));

		this.scheduleScan();
	}

	destroy()
	{
		GRID_EVENTS.forEach((eventName) => {
			this.runSafely(() => EventEmitter.unsubscribe(eventName, this.onBitrixEvent));
		});
		this.runSafely(() => BX.removeCustomEvent(TAB_SHOW_EVENT, this.onBitrixEvent));
		this.runSafely(() => BX.removeCustomEvent(AJAX_SUCCESS_EVENT, this.onBitrixEvent));

		clearTimeout(this.scanTimer);
	}

	runSafely(callback)
	{
		try
		{
			callback();
		}
		catch (error)
		{
			console.warn(`${LOG_PREFIX} init step failed`, error);
		}
	}

	onBitrixEvent = () => {
		this.scheduleScan();
	};

	scheduleScan()
	{
		clearTimeout(this.scanTimer);
		this.scanTimer = setTimeout(() => this.scan(), SCAN_DELAY);
	}

	scan()
	{
		const items = [];
		const names = new Set();

		for (const node of this.getTextNodes(document.body))
		{
			const value = node.nodeValue;
			if (this.seenTextNodes.get(node) === value)
			{
				continue;
			}
			this.seenTextNodes.set(node, value);

			const candidates = FileNameParser.collectCandidates(value);
			if (candidates.length > 0)
			{
				items.push({ node, value });
				candidates.forEach((candidate) => names.add(candidate.name));
			}
		}

		if (items.length === 0)
		{
			return;
		}

		const unknownNames = [...names].filter((name) => !this.fileUrls.has(name)).slice(0, MAX_NAMES_PER_SCAN);
		console.info(`${LOG_PREFIX} texts with possible file names: ${items.length}, names to check: ${unknownNames.length}`);

		this.loadFileUrls(unknownNames)
			.then(() => {
				const linkedCount = [...names].filter((name) => this.fileUrls.get(name)).length;
				if (unknownNames.length > 0)
				{
					console.info(`${LOG_PREFIX} found on Disk: ${linkedCount}`);
				}
				items.forEach((item) => this.renderLinks(item));
			})
			.catch((error) => {
				console.warn(`${LOG_PREFIX} failed to load file links`, error);

				if (++this.failures < MAX_FAILURES)
				{
					items.forEach(({ node }) => this.seenTextNodes.delete(node));
					this.scheduleScan();
				}
			});
	}

	loadFileUrls(fileNames)
	{
		const requests = [];
		for (let start = 0; start < fileNames.length; start += MAX_NAMES_PER_REQUEST)
		{
			const namesChunk = fileNames.slice(start, start + MAX_NAMES_PER_REQUEST);

			requests.push(
				new Promise((resolve, reject) => {
					ajax.runAction(ACTION_GET_URLS, { data: { fileNames: namesChunk } }).then(resolve, reject);
				}).then((response) => {
					if (response.status === 'error')
					{
						throw new Error(JSON.stringify(response.errors));
					}

					const urls = response.data || {};
					namesChunk.forEach((name) => {
						this.fileUrls.set(name, urls[name] || null);
					});
				}),
			);
		}

		return Promise.all(requests);
	}

	renderLinks({ node, value })
	{
		if (!node.isConnected || node.nodeValue !== value)
		{
			return;
		}

		const mentions = FileNameParser.findMentions(
			FileNameParser.collectCandidates(value),
			(name) => this.fileUrls.get(name),
		);
		if (mentions.length === 0)
		{
			return;
		}

		const fragment = document.createDocumentFragment();
		let position = 0;
		for (const mention of mentions)
		{
			if (mention.start > position)
			{
				fragment.append(value.slice(position, mention.start));
			}
			fragment.append(this.createLink(value.slice(mention.start, mention.end), mention.url));
			position = mention.end;
		}
		if (position < value.length)
		{
			fragment.append(value.slice(position));
		}

		node.replaceWith(fragment);
	}

	getTextNodes(rootElement)
	{
		const textNodes = [];
		const walker = document.createTreeWalker(rootElement, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
			acceptNode: (node) => {
				if (node.nodeType === Node.ELEMENT_NODE)
				{
					return SKIP_TAGS.has(node.tagName) || node.isContentEditable
						? NodeFilter.FILTER_REJECT
						: NodeFilter.FILTER_SKIP;
				}

				return NodeFilter.FILTER_ACCEPT;
			},
		});

		let node = walker.nextNode();
		while (node)
		{
			textNodes.push(node);
			node = walker.nextNode();
		}

		return textNodes;
	}

	createLink(text, url)
	{
		const link = document.createElement('a');
		link.className = 'custom-eventfiles-link';
		link.href = url;
		link.target = '_blank';
		link.rel = 'noopener';
		link.textContent = text;

		link.addEventListener('click', (event) => event.stopPropagation());

		return link;
	}
}

Event.ready(HistoryFileLinks.start);
