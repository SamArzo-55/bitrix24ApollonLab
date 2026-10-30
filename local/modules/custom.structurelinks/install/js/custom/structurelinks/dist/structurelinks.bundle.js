/* Assembled from ./src (equivalent of `chef build`); regenerate with chef when available. */
this.BX = this.BX || {};
this.BX.Custom = this.BX.Custom || {};
(function (exports, main_core, ui_dialogs_messagebox) {
'use strict';

const { ajax, Dom, Event, Extension } = main_core;
const { MessageBox, MessageBoxButtons } = ui_dialogs_messagebox;


const ACTION_SAVE = 'custom:structurelinks.links.save';

class LinksEditor
{
	rows = [];

	constructor(links, messages, onSaved)
	{
		this.links = links;
		this.messages = messages;
		this.onSaved = onSaved;
	}

	open()
	{
		this.rows = [];
		this.list = Dom.create('div', { props: { className: 'custom-structure-links-editor' } });
		this.error = Dom.create('div', { props: { className: 'custom-structure-links-editor__error' } });

		const items = this.links.length > 0 ? this.links : [{ title: '', url: '' }];
		items.forEach((item) => this.addRow(item));

		const addButton = Dom.create('button', {
			props: { className: 'ui-btn ui-btn-light-border ui-btn-sm', type: 'button' },
			text: this.messages.addRow,
			events: { click: () => this.addRow({ title: '', url: '' }) },
		});

		Dom.append(addButton, this.list);
		Dom.append(this.error, this.list);

		MessageBox.show({
			title: this.messages.editTitle,
			message: this.list,
			modal: true,
			buttons: MessageBoxButtons.OK_CANCEL,
			okCaption: this.messages.save,
			cancelCaption: this.messages.cancel,
			onOk: () => this.save(),
		});
	}

	addRow(item)
	{
		const title = this.createInput(this.messages.titlePlaceholder, item.title);
		const url = this.createInput(this.messages.urlPlaceholder, item.url);
		const row = { title, url };

		const node = Dom.create('div', {
			props: { className: 'custom-structure-links-editor__row' },
			children: [
				Dom.create('div', { props: { className: 'ui-ctl ui-ctl-textbox' }, children: [title] }),
				Dom.create('div', { props: { className: 'ui-ctl ui-ctl-textbox' }, children: [url] }),
				Dom.create('button', {
					props: { className: 'ui-btn ui-btn-light-border ui-btn-sm', type: 'button' },
					text: '×',
					attrs: { title: this.messages.remove },
					events: {
						click: () => {
							Dom.remove(node);
							this.rows = this.rows.filter((current) => current !== row);
						},
					},
				}),
			],
		});

		this.rows.push(row);
		const lastChild = this.list.querySelector('button.ui-btn-sm:not([title])');
		this.list.insertBefore(node, lastChild);
	}

	createInput(placeholder, value)
	{
		return Dom.create('input', {
			props: { className: 'ui-ctl-element', type: 'text', placeholder, value },
		});
	}

	collect()
	{
		return this.rows
			.map((row) => ({ title: row.title.value.trim(), url: row.url.value.trim() }))
			.filter((item) => item.title !== '' && item.url !== '');
	}

	save()
	{
		return ajax.runAction(ACTION_SAVE, { data: { links: this.collect() } })
			.then((response) => {
				this.onSaved(response.data.links);

				return true;
			})
			.catch((response) => {
				const first = response && response.errors && response.errors[0];
				this.error.textContent = first ? first.message : this.messages.error;
				this.error.style.display = 'block';

				return false;
			});
	}
}


const EXTENSION_NAME = 'custom.structurelinks';
const BLOCK_ID = 'custom-structure-links';
const HOST_SELECTORS = [
	'#humanresources-company-structure>.humanresources-chart',
	'.main-grid-wrapper',
];

class StructureLinks
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

exports.StructureLinks = StructureLinks;

}(this.BX.Custom.StructureLinks = this.BX.Custom.StructureLinks || {}, BX, BX.UI.Dialogs));
