import { ajax } from 'main.core';
import { EventEmitter } from 'main.core.events';
import './eventfiles.css';

const ACTION_GET_FILE_URLS = 'custom:eventfiles.link.getFileUrls';
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
const GRID_EVENTS = ['Grid::ready', 'Grid::updated'];
const AJAX_SUCCESS_EVENT = 'onAjaxSuccess';

/**
 * Finds possible file names in text.
 *
 * @param {string} text
 * @returns {Array<{start: number, end: number, name: string}>}
 */
export function collectCandidates(text)
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
export function findMentions(candidates, getUrl)
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

/**
 * Turns file names in CRM card text into Shared Drive links.
 */
export class HistoryFileLinks
{
	fileUrls = new Map();

	seen = new WeakMap();

	pendingRoots = new Set();

	scanTimer = null;
	observer = null;
	failures = 0;

	init()
	{
		console.info(`${LOG_PREFIX} loaded`);

		GRID_EVENTS.forEach((eventName) => {
			this.safe(() => EventEmitter.subscribe(eventName, this.onEvent));
		});
		this.safe(() => BX.addCustomEvent(AJAX_SUCCESS_EVENT, this.onEvent));

		this.safe(() => {
			this.observer = new MutationObserver(this.onMutations);
			this.observer.observe(document.body, { childList: true, subtree: true });
		});

		this.schedule(document.body);
	}

	destroy()
	{
		GRID_EVENTS.forEach((eventName) => {
			this.safe(() => EventEmitter.unsubscribe(eventName, this.onEvent));
		});
		this.safe(() => BX.removeCustomEvent(AJAX_SUCCESS_EVENT, this.onEvent));

		this.observer?.disconnect();
		clearTimeout(this.scanTimer);
	}

	safe(callback)
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

	onEvent = () => {
		this.schedule(document.body);
	};

	onMutations = (records) => {
		for (const record of records)
		{
			record.addedNodes.forEach((node) => this.schedule(node));
		}
	};

	schedule(root)
	{
		this.pendingRoots.add(root);
		clearTimeout(this.scanTimer);
		this.scanTimer = setTimeout(() => this.scan(), SCAN_DELAY);
	}

	scan()
	{
		const roots = [...this.pendingRoots];
		this.pendingRoots.clear();

		const items = [];
		const names = new Set();

		for (const root of roots)
		{
			if (!root.isConnected)
			{
				continue;
			}

			for (const node of this.getTextNodes(root))
			{
				const value = node.nodeValue;
				if (this.seen.get(node) === value)
				{
					continue;
				}
				this.seen.set(node, value);

				const candidates = collectCandidates(value);
				if (candidates.length > 0)
				{
					items.push({ node, value, candidates });
					candidates.forEach((candidate) => names.add(candidate.name));
				}
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
				const linked = [...names].filter((name) => this.fileUrls.get(name)).length;
				if (unknownNames.length > 0)
				{
					console.info(`${LOG_PREFIX} found on Disk: ${linked}`);
				}
				items.forEach((item) => this.render(item));
			})
			.catch((error) => {
				console.warn(`${LOG_PREFIX} failed to load file links`, error);

				if (++this.failures < MAX_FAILURES)
				{
					items.forEach(({ node }) => this.seen.delete(node));
					this.schedule(document.body);
				}
			});
	}

	loadFileUrls(fileNames)
	{
		const requests = [];
		for (let start = 0; start < fileNames.length; start += MAX_NAMES_PER_REQUEST)
		{
			const names = fileNames.slice(start, start + MAX_NAMES_PER_REQUEST);

			requests.push(
				new Promise((resolve, reject) => {
					ajax.runAction(ACTION_GET_FILE_URLS, { data: { fileNames: names } }).then(resolve, reject);
				}).then((response) => {
					if (response.status === 'error')
					{
						throw new Error(JSON.stringify(response.errors));
					}

					const urls = response.data || {};
					names.forEach((name) => {
						this.fileUrls.set(name, urls[name] || null);
					});
				}),
			);
		}

		return Promise.all(requests);
	}

	render({ node, value })
	{
		if (!node.isConnected || node.nodeValue !== value)
		{
			return;
		}

		const mentions = findMentions(collectCandidates(value), (name) => this.fileUrls.get(name));
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

	getTextNodes(root)
	{
		if (root.nodeType === Node.TEXT_NODE)
		{
			return root.parentElement && !this.isInsideSkipped(root.parentElement) ? [root] : [];
		}

		if (root.nodeType !== Node.ELEMENT_NODE || this.isInsideSkipped(root))
		{
			return [];
		}

		const nodes = [];
		const walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
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
			nodes.push(node);
			node = walker.nextNode();
		}

		return nodes;
	}

	isInsideSkipped(element)
	{
		for (let current = element; current; current = current.parentElement)
		{
			if (SKIP_TAGS.has(current.tagName) || current.isContentEditable)
			{
				return true;
			}
		}

		return false;
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

const start = () => {
	try
	{
		new HistoryFileLinks().init();
	}
	catch (error)
	{
		console.error('custom.eventfiles: init failed', error);
	}
};

if (document.readyState === 'loading')
{
	document.addEventListener('DOMContentLoaded', start);
}
else
{
	start();
}
