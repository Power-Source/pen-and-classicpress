/* global document, window */
'use strict';

const psppDirtyForms = new Set();
document.querySelectorAll('[data-pspp-form]').forEach((form) => {
	if (form.hasAttribute('data-pspp-download')) return;
	form.addEventListener('input', () => psppDirtyForms.add(form));
	form.addEventListener('change', () => psppDirtyForms.add(form));
});
document.querySelectorAll('[data-loot-editor]').forEach((editor) => {
	const list = editor.querySelector('[data-loot-rows]');
	const template = editor.querySelector('[data-loot-template]');
	const add = editor.querySelector('[data-add-loot]');
	let next = Number(editor.dataset.nextIndex);
	const update = () => { add.disabled = list.children.length >= 30; };
	add.addEventListener('click', () => {
		if (list.children.length >= 30) return;
		list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(next++)));
		psppDirtyForms.add(editor.closest('form'));
		list.lastElementChild.querySelector('input').focus();
		update();
	});
	list.addEventListener('click', (event) => {
		if (!event.target.closest('[data-remove-loot]')) return;
		event.target.closest('[data-loot-row]').remove();
		psppDirtyForms.add(editor.closest('form'));
		add.focus();
		update();
	});
	update();
});
document.querySelectorAll('[data-condition-editor]').forEach((editor) => {
	const list = editor.querySelector('[data-condition-rows]');
	const template = editor.querySelector('[data-condition-template]');
	const add = editor.querySelector('[data-add-condition]');
	let next = Number(editor.dataset.nextIndex);
	const update = () => {
		add.disabled = list.children.length >= 20;
		list.querySelectorAll('[data-remove-condition]').forEach((button) => {
			button.hidden = false;
			button.disabled = list.children.length <= 1;
		});
	};
	add.hidden = false;
	add.addEventListener('click', () => {
		if (list.children.length >= 20) return;
		list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(next++)));
		psppDirtyForms.add(editor.closest('form'));
		list.lastElementChild.querySelector('select').focus();
		update();
	});
	list.addEventListener('click', (event) => {
		const remove = event.target.closest('[data-remove-condition]');
		if (!remove || list.children.length <= 1) return;
		remove.closest('[data-condition-row]').remove();
		psppDirtyForms.add(editor.closest('form'));
		add.focus();
		update();
	});
	update();
});
window.addEventListener('beforeunload', (event) => {
	if (psppDirtyForms.size) {
		event.preventDefault();
		event.returnValue = '';
	}
});
document.addEventListener('submit', (event) => {
	if (event.target.hasAttribute('data-pspp-download')) return;
	const others = Array.from(psppDirtyForms).filter((form) => form !== event.target);
	if (others.length && !window.confirm(others[0].dataset.unsavedMessage)) {
		event.preventDefault();
		return;
	}
	if (!event.defaultPrevented) {
		psppDirtyForms.clear();
	}
});

document.querySelectorAll('[data-character-resource]').forEach((field) => {
	const current = field.querySelector('[data-character-number="current"]');
	const maximum = field.querySelector('[data-character-number="maximum"]');
	const validate = () => {
		current.setCustomValidity(Number(current.value) > Number(maximum.value) ? field.dataset.rangeMessage : '');
	};
	field.addEventListener('input', validate);
	validate();
});

document.querySelectorAll('[data-rules-editor]').forEach((editor) => {
	const form = editor.closest('form');
	const list = editor.querySelector('[data-fields]');
	const template = editor.querySelector('[data-field-template]');
	const addButton = editor.querySelector('[data-add-field]');
	const status = editor.querySelector('[data-rules-status]');
	const limit = Number(editor.dataset.limit);
	let nextIndex = list.children.length;
	const locked = editor.dataset.structureLocked === 'true';

	const markDirty = () => {
		psppDirtyForms.add(form);
		status.textContent = editor.dataset.unsavedMessage;
	};

	const validateRange = (field) => {
		const numeric = field.querySelector('[data-numeric]');
		const type = field.querySelector('[data-field-type]').value;
		const isNumeric = ['number', 'attribute', 'resource'].includes(type);
		numeric.hidden = !isNumeric;
		numeric.disabled = !isNumeric;
		field.querySelector('[data-resource-note]').hidden = type !== 'resource';
		const min = numeric.querySelector('[data-number="min"]');
		const max = numeric.querySelector('[data-number="max"]');
		const start = numeric.querySelector('[data-number="start"]');
		const valid = !isNumeric || (Number(min.value) <= Number(max.value) && Number(start.value) >= Number(min.value) && Number(start.value) <= Number(max.value));
		start.setCustomValidity(valid ? '' : editor.dataset.rangeMessage);
	};

	const refresh = () => {
		const fields = Array.from(list.children);
		fields.forEach((field, index) => {
			field.querySelector('[data-field-number]').textContent = String(index + 1);
			field.querySelector('[data-field-actions]').hidden = false;
			field.querySelector('[data-move="up"]').disabled = index === 0;
			field.querySelector('[data-move="down"]').disabled = index === fields.length - 1;
			validateRange(field);
		});
		addButton.disabled = locked || fields.length >= limit;
	};

	editor.addEventListener('input', (event) => {
		markDirty();
		const field = event.target.closest('.pspp-field');
		if (field) {
			validateRange(field);
		}
	});

	editor.addEventListener('change', (event) => {
		markDirty();
		const field = event.target.closest('.pspp-field');
		if (field) {
			validateRange(field);
		}
	});

	addButton.addEventListener('click', () => {
		if (locked || list.children.length >= limit) {
			status.textContent = editor.dataset.limitMessage;
			return;
		}
		const content = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
		list.insertAdjacentHTML('beforeend', content);
		refresh();
		markDirty();
		list.lastElementChild.querySelector('[data-field-label]').focus();
	});

	list.addEventListener('click', (event) => {
		const button = event.target.closest('button');
		if (!button) {
			return;
		}
		const field = button.closest('.pspp-field');
		if (button.hasAttribute('data-remove')) {
			if (!window.confirm(editor.dataset.removeMessage)) {
				return;
			}
			const next = field.nextElementSibling || field.previousElementSibling;
			field.remove();
			refresh();
			(next ? next.querySelector('[data-field-label]') : addButton).focus();
		} else if (button.dataset.move === 'up' && field.previousElementSibling) {
			list.insertBefore(field, field.previousElementSibling);
		} else if (button.dataset.move === 'down' && field.nextElementSibling) {
			list.insertBefore(field.nextElementSibling, field);
		} else {
			return;
		}
		refresh();
		markDirty();
		status.textContent = editor.dataset.changeMessage;
		if (button.isConnected) {
			// Keep keyboard focus usable when the moved button becomes disabled.
			field.querySelector('[data-field-label]').focus();
		}
	});

	addButton.hidden = locked;
	refresh();
});
