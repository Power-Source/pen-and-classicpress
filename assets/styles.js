/* global document, Event */
'use strict';

document.querySelectorAll('[data-style-editor]').forEach((editor) => {
	const preview = editor.querySelector('[data-style-preview]');
	const rows = Array.from(editor.querySelectorAll('[data-style-color]'));
	const status = editor.querySelector('[data-contrast-status]');
	const error = editor.querySelector('[data-style-error]');
	const reset = editor.querySelector('[data-style-defaults]');

	const luminance = (color) => {
		const channels = [1, 3, 5].map((offset) => {
			const value = parseInt(color.slice(offset, offset + 2), 16) / 255;
			return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
		});
		return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
	};
	const contrast = (first, second) => {
		const a = luminance(first);
		const b = luminance(second);
		return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
	};
	const update = () => {
		const colors = {};
		const valid = rows.every((row) => {
			const value = row.querySelector('[data-color-value]').value;
			if (!/^#[0-9a-f]{6}$/i.test(value)) {
				return false;
			}
			colors[row.dataset.styleColor] = value.toLowerCase();
			return true;
		});
		error.hidden = valid;
		if (!valid) {
			return;
		}
		rows.forEach((row) => {
			const color = colors[row.dataset.styleColor];
			row.querySelector('[data-color-picker]').value = color;
			preview.style.setProperty(`--pspp-${row.dataset.styleColor}`, color);
		});
		preview.style.setProperty('--pspp-primary-text', contrast(colors.primary, '#ffffff') >= contrast(colors.primary, '#000000') ? '#ffffff' : '#000000');
		status.querySelectorAll('[data-contrast-pair]').forEach((line) => {
			const [first, second] = line.dataset.contrastPair.split(' ');
			const ratio = contrast(colors[first], colors[second]);
			const warning = ratio < 4.5;
			line.classList.toggle('pspp-contrast-warning', warning);
			line.textContent = `${line.dataset.label}: ${ratio.toLocaleString(document.documentElement.lang || 'de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}:1 - ${warning ? status.dataset.warning : status.dataset.good}`;
		});
	};

	rows.forEach((row) => {
		const picker = row.querySelector('[data-color-picker]');
		const value = row.querySelector('[data-color-value]');
		picker.hidden = false;
		picker.addEventListener('input', () => {
			value.value = picker.value;
			value.dispatchEvent(new Event('input', {bubbles: true}));
		});
		value.addEventListener('input', update);
	});
	reset.hidden = false;
	reset.addEventListener('click', () => {
		rows.forEach((row) => {
			const value = row.querySelector('[data-color-value]');
			value.value = row.dataset.default;
			value.dispatchEvent(new Event('input', {bubbles: true}));
		});
	});
	update();
});
