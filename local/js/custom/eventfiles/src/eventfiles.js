import { Event } from 'main.core';
import './eventfiles.css';

// Только front: ни ajax, ни форм. Ссылка собирается из уникального ID файла Диска (b_disk_object.ID).
// Универсальный переходник Диска: сам находит файл по ID и открывает его с проверкой прав.
// Если на портале нужен другой адрес - меняется только этот шаблон (#ID# заменяется на ID файла).
const FILE_URL_TEMPLATE = '/bitrix/tools/disk/focus.php?objectId=#ID#&cmd=show&action=showObjectInGrid&ncc=1';

// Пусто = обрабатывать все таблицы страницы; иначе ID таблиц (см. BX.Main.gridManager.data в консоли).
const GRID_IDS = [];

// Маркер в тексте события: [file:<уникальный ID файла>|<имя для показа>], например [file:123|Договор.docx]
const FILE_MARKER_PATTERN = /\[file:(\d+)\|([^\]\r\n]+)]/g;

const SKIPPED_PARENTS = 'a, script, style, textarea';
const LINK_CLASS = 'custom-eventfiles-link';
const GRID_EVENTS = ['Grid::ready', 'Grid::updated'];

export function tokenize(text)
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

export class EventFileLinks
{
	timer = null;

	static start()
	{
		new EventFileLinks().init();
	}

	init()
	{
		GRID_EVENTS.forEach((name) => BX.addCustomEvent(name, (grid) => this.processGrid(grid)));

		// вкладка "История" подгружается позже расширения: следим за DOM и снова проходим по таблицам
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
		// строка таблицы открывает сущность по клику - не даём перехватить клик по ссылке
		link.addEventListener('click', (event) => event.stopPropagation());

		return link;
	}
}

Event.ready(EventFileLinks.start);
