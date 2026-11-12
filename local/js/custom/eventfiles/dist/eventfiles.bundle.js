this.BX = this.BX || {};
this.BX.Custom = this.BX.Custom || {};
(function (exports,main_core) {
	'use strict';

	const FILE_URL_TEMPLATE = '/bitrix/tools/disk/focus.php?objectId=#ID#&cmd=show&action=showObjectInGrid&ncc=1';

	const GRID_IDS = [];

	const FILE_MARKER_PATTERN = /\[file:(\d+)\|([^\]\r\n]+)]/g;

	const SKIPPED_PARENTS = 'a, script, style, textarea';
	const LINK_CLASS = 'custom-eventfiles-link';
	const GRID_EVENTS = ['Grid::ready', 'Grid::updated'];

	function tokenize(text)
	{
		const parts = [];
		let lastIndex = 0;

		for (const match of text.matchAll(FILE_MARKER_PATTERN))
		{
			parts.push({ text: text.slice(lastIndex, match.index), fileId: null });
			parts.push({ text: match[2].trim(), fileId: match[1] });
			lastIndex = match.index + match[0].length;
		}
		parts.push({ text: text.slice(lastIndex), fileId: null });

		return parts.filter((part) => part.text !== '');
	}

	class EventFileLinks
	{
		timer = null;

		static start()
		{
			new EventFileLinks().init();
		}

		init()
		{
			GRID_EVENTS.forEach((name) => BX.addCustomEvent(name, (grid) => this.processGrid(grid)));

			new MutationObserver(() => {
				clearTimeout(this.timer);
				this.timer = setTimeout(() => this.processAllGrids(), 100);
			}).observe(document.body, { childList: true, subtree: true });

			this.processAllGrids();
		}

		processAllGrids()
		{
			(BX.Main?.gridManager?.data ?? []).forEach((item) => this.processGrid(item.instance));
		}

		processGrid(gridObject)
		{
			if (!gridObject?.getRows || (GRID_IDS.length > 0 && !GRID_IDS.includes(gridObject.getId())))
			{
				return;
			}

			this.processRows(gridObject.getRows().getRows());
		}

		processRows(rows)
		{
			rows.forEach((row) => this.processCells(row.getCells()));
		}

		processCells(cells)
		{
			Array.from(cells).forEach((cell) => this.processCell(cell));
		}

		processCell(cell)
		{
			if (!cell.textContent.includes('[file:'))
			{
				return;
			}

			const walker = document.createTreeWalker(cell, NodeFilter.SHOW_TEXT, {
				acceptNode: (node) => (
					node.parentElement?.closest(SKIPPED_PARENTS) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT
				),
			});

			const nodes = [];
			while (walker.nextNode())
			{
				nodes.push(walker.currentNode);
			}

			nodes.forEach((node) => {
				const parts = tokenize(node.nodeValue);
				if (!parts.some((part) => part.fileId))
				{
					return;
				}

				const fragment = document.createDocumentFragment();
				parts.forEach((part) => {
					fragment.append(part.fileId ? this.createLink(part.fileId, part.text) : document.createTextNode(part.text));
				});
				node.replaceWith(fragment);
			});
		}

		createLink(fileId, fileName)
		{
			const link = document.createElement('a');
			link.className = LINK_CLASS;
			link.href = FILE_URL_TEMPLATE.replace('#ID#', fileId);
			link.target = '_blank';
			link.rel = 'noopener';
			link.title = `Файл Диска, ID ${fileId}`;
			link.textContent = fileName;

			link.addEventListener('click', (event) => event.stopPropagation());
			return link;
		}
	}

	main_core.Event.ready(EventFileLinks.start);

	exports.tokenize = tokenize;
	exports.EventFileLinks = EventFileLinks;

}((this.BX.Custom.EventFiles = this.BX.Custom.EventFiles || {}),BX));
