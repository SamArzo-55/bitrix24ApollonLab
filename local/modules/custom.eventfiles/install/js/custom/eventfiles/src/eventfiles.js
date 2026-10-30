import { ajax, Dom, Event } from 'main.core';
import './eventfiles.css';

const ACTION_GET_LINKS = 'custom:eventfiles.file.getLinks';
const CELL_SELECTOR = '.main-grid-cell-content';
const FILE_NAME_PATTERN = /^[^\\/:*?"<>|]{1,200}\.[A-Za-z0-9]{2,5}$/;
const DONE_FLAG = 'eventfilesDone';
const SCAN_DELAY = 300;

export class EventFiles
{
	cache = {};
	timer = null;
	observer = null;

	start()
	{
		this.observer = new MutationObserver(() => this.scheduleScan());
		this.observer.observe(document.documentElement, { childList: true, subtree: true });
	}

	scheduleScan()
	{
		clearTimeout(this.timer);
		this.timer = setTimeout(() => this.scan(), SCAN_DELAY);
	}

	getName(cell)
	{
		return cell.textContent.trim();
	}

	findCells()
	{
		return [...document.querySelectorAll(CELL_SELECTOR)].filter((cell) => {
			return !cell.firstElementChild
				&& !cell.dataset[DONE_FLAG]
				&& FILE_NAME_PATTERN.test(this.getName(cell));
		});
	}

	scan()
	{
		const cells = this.findCells();
		if (cells.length === 0)
		{
			return;
		}

		const names = [...new Set(cells.map((cell) => this.getName(cell)))]
			.filter((name) => !(name in this.cache));

		if (names.length === 0)
		{
			this.render(cells);

			return;
		}

		ajax.runAction(ACTION_GET_LINKS, { data: { names } })
			.then((response) => {
				names.forEach((name) => {
					this.cache[name] = response.data[name] || null;
				});
				this.render(cells);
			})
			.catch(() => {});
	}

	render(cells)
	{
		cells.forEach((cell) => {
			const name = this.getName(cell);
			const url = this.cache[name];

			cell.dataset[DONE_FLAG] = '1';
			if (!url)
			{
				return;
			}

			Dom.clean(cell);
			Dom.append(this.createLink(name, url), cell);
		});
	}

	createLink(name, url)
	{
		return Dom.create('a', {
			props: { className: 'custom-eventfiles-link' },
			attrs: { href: url, target: '_blank', rel: 'noopener' },
			text: name,
		});
	}
}

Event.ready(() => new EventFiles().start());
