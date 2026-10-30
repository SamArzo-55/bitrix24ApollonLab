import { Dom, Event, Extension } from 'main.core';
import { LinksEditor } from './linkseditor';
import './structurelinks.css';

const EXTENSION_NAME = 'custom.structurelinks';
const BLOCK_ID = 'custom-structure-links';
const HOST_SELECTORS = [
	'#humanresources-company-structure>.humanresources-chart',
	'.main-grid-wrapper',
];

export class StructureLinks
{
	observer = null;

	constructor(links, messages)
	{
		this.links = links;
		this.messages = messages;
	}

	start()
	{
		this.mount();
		this.observer = new MutationObserver(() => {
			if (!document.getElementById(BLOCK_ID))
			{
				this.mount();
			}
		});
		this.observer.observe(document.body, { childList: true, subtree: true });
	}

	findHost()
	{
		for (const selector of HOST_SELECTORS)
		{
			const host = document.querySelector(selector);
			if (host)
			{
				return host;
			}
		}

		return null;
	}

	mount()
	{
		const host = this.findHost();
		if (!host || document.getElementById(BLOCK_ID))
		{
			return;
		}

		host.insertBefore(this.render(), host.firstChild);
	}

	render()
	{
		const items = this.links.map((item) => Dom.create('a', {
			props: { className: 'custom-structure-links__item' },
			attrs: { href: item.url, target: '_blank', rel: 'noopener' },
			text: item.title,
		}));

		const editButton = Dom.create('button', {
			props: {
				className: 'ui-btn ui-btn-light-border ui-btn-xs custom-structure-links__edit',
				type: 'button',
			},
			text: this.links.length > 0 ? this.messages.edit : this.messages.addFirst,
			events: { click: () => this.openEditor() },
		});

		return Dom.create('div', {
			attrs: { id: BLOCK_ID },
			props: { className: 'custom-structure-links' },
			children: [...items, editButton],
		});
	}

	openEditor()
	{
		new LinksEditor(this.links, this.messages, (links) => {
			this.links = links;
			Dom.remove(document.getElementById(BLOCK_ID));
			this.mount();
		}).open();
	}
}

Event.ready(() => {
	const settings = Extension.getSettings(EXTENSION_NAME);
	new StructureLinks(settings.get('links', []), settings.get('messages', {})).start();
});
