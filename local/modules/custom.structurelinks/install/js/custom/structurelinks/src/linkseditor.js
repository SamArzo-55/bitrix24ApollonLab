import { ajax, Dom } from 'main.core';
import { MessageBox, MessageBoxButtons } from 'ui.dialogs.messagebox';

const ACTION_SAVE = 'custom:structurelinks.links.save';

export class LinksEditor
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
