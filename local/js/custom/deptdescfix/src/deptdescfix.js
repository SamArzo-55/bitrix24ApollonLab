import { Dom, Event } from 'main.core';
import './deptdescfix.css';

const SOURCE_CLASS = 'hr-department-detail-content-description';
const COPY_CLASS = 'dept-desc-linkified';
const URL_PATTERN = /https?:\/\/[^\s<>"']+[^\s<>"'.,;:!?)\]]/g;
const TOGGLE_PATTERN = /chevron|arrow|expand|collapse|toggle/i;

export class DeptDescFix
{
	observer = null;

	start()
	{
		this.run();
		this.observer = new MutationObserver(() => requestAnimationFrame(() => this.run()));
		this.observer.observe(document.body, { childList: true, subtree: true, characterData: true });
	}

	run()
	{
		document.querySelectorAll(`.${SOURCE_CLASS}:not(.${COPY_CLASS})`).forEach((source) => {
			const copy = this.getCopy(source);
			this.fillCopy(copy, source.textContent.trim());
			this.hideToggles(source, copy);
		});

		this.removeOrphans();
	}

	getCopy(source)
	{
		let copy = source.nextElementSibling;
		if (!copy || !copy.classList.contains(COPY_CLASS))
		{
			copy = Dom.create('div', {
				props: { className: `${source.className.replace(/\s*--overflowed\b/, '')} ${COPY_CLASS}` },
			});
			source.after(copy);
		}

		return copy;
	}

	fillCopy(copy, text)
	{
		if (copy.dataset.text === text)
		{
			return;
		}

		copy.dataset.text = text;
		Dom.clean(copy);
		this.linkify(text).forEach((node) => Dom.append(node, copy));
	}

	linkify(text)
	{
		const nodes = [];
		let lastIndex = 0;

		for (const match of text.matchAll(URL_PATTERN))
		{
			if (match.index > lastIndex)
			{
				nodes.push(document.createTextNode(text.slice(lastIndex, match.index)));
			}

			nodes.push(Dom.create('a', {
				attrs: { href: match[0], target: '_blank', rel: 'noopener noreferrer' },
				text: match[0],
			}));
			lastIndex = match.index + match[0].length;
		}

		if (lastIndex < text.length)
		{
			nodes.push(document.createTextNode(text.slice(lastIndex)));
		}

		return nodes;
	}

	hideToggles(source, copy)
	{
		[...source.parentNode.children].forEach((element) => {
			if (element !== source && element !== copy && TOGGLE_PATTERN.test(element.className || ''))
			{
				Dom.style(element, 'display', 'none');
			}
		});
	}

	removeOrphans()
	{
		document.querySelectorAll(`.${COPY_CLASS}`).forEach((copy) => {
			const previous = copy.previousElementSibling;
			if (!previous || !previous.matches(`.${SOURCE_CLASS}:not(.${COPY_CLASS})`))
			{
				Dom.remove(copy);
			}
		});
	}
}

Event.ready(() => new DeptDescFix().start());
