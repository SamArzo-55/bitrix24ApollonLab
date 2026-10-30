/* Assembled from ./src (equivalent of `chef build`); regenerate with chef when available. */
this.BX = this.BX || {};
this.BX.Custom = this.BX.Custom || {};
(function (exports, main_core, ui_dialogs_messagebox) {
'use strict';

const { ajax, Dom, Event, Extension, Type } = main_core;
const { MessageBox, MessageBoxButtons } = ui_dialogs_messagebox;

const EXTENSION_NAME = 'custom.toolbarlinks';
const ACTION_SAVE = 'custom:toolbarlinks.link.save';
const EMPTY_HREF = '#';

const ATTR_CODE = 'data-custom-toolbarlinks-code';
const SELECTOR_LINK = '[data-custom-toolbarlinks-link]';
const SELECTOR_EDIT = '[data-custom-toolbarlinks-edit]';

class ToolbarLinks
{
	messages = {};

	constructor()
	{
		this.messages = Extension.getSettings(EXTENSION_NAME).get('messages', {});
	}

	bind()
	{
		Event.bind(document, 'click', this.handleClick.bind(this));
	}

	handleClick(event)
	{
		if (!Type.isDomNode(event.target))
		{
			return;
		}

		const editButton = event.target.closest(SELECTOR_EDIT);
		if (editButton)
		{
			event.preventDefault();
			this.openEditor(editButton.getAttribute('data-custom-toolbarlinks-edit'));

			return;
		}

		const linkButton = event.target.closest(SELECTOR_LINK);
		if (linkButton && this.getUrl(linkButton) === '')
		{
			event.preventDefault();
			this.openEditor(linkButton.getAttribute(ATTR_CODE));
		}
	}

	findLinkButton(code)
	{
		return document.querySelector(`${SELECTOR_LINK}[${ATTR_CODE}="${code}"]`);
	}

	getUrl(linkButton)
	{
		const href = linkButton ? linkButton.getAttribute('href') : '';

		return !href || href === EMPTY_HREF ? '' : href;
	}

	applyUrl(code, url)
	{
		const linkButton = this.findLinkButton(code);
		if (linkButton)
		{
			linkButton.setAttribute('href', url === '' ? EMPTY_HREF : url);
		}
	}

	openEditor(code)
	{
		const input = Dom.create('input', {
			props: {
				className: 'ui-ctl-element',
				type: 'text',
				placeholder: 'https://...',
				value: this.getUrl(this.findLinkButton(code)),
			},
		});
		const error = Dom.create('div', {
			props: { className: 'ui-alert-text' },
			style: { display: 'none', color: '#c0392b', marginTop: '8px' },
		});
		const message = Dom.create('div', {
			children: [
				Dom.create('div', { text: this.messages.label, style: { marginBottom: '8px' } }),
				Dom.create('div', {
					props: { className: 'ui-ctl ui-ctl-textbox ui-ctl-w100' },
					children: [input],
				}),
				error,
			],
		});

		MessageBox.show({
			title: this.messages.title,
			message,
			modal: true,
			buttons: MessageBoxButtons.OK_CANCEL,
			okCaption: this.messages.save,
			cancelCaption: this.messages.cancel,
			onOk: () => this.save(code, input.value.trim(), error),
		});
	}

	save(code, url, errorNode)
	{
		return ajax.runAction(ACTION_SAVE, { data: { code, url } })
			.then((response) => {
				this.applyUrl(code, response.data.url);

				return true;
			})
			.catch((response) => {
				const first = response && response.errors && response.errors[0];
				errorNode.textContent = first ? first.message : this.messages.error;
				errorNode.style.display = 'block';

				return false;
			});
	}
}

Event.ready(() => new ToolbarLinks().bind());

exports.ToolbarLinks = ToolbarLinks;

}(this.BX.Custom.ToolbarLinks = this.BX.Custom.ToolbarLinks || {}, BX, BX.UI.Dialogs));
