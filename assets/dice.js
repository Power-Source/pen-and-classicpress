/* global document, window, Event */
'use strict';

document.querySelectorAll('[data-dice-controller]').forEach((controller) => {
	const form = controller.querySelector('form');
	const stage = controller.querySelector('[data-dice-stage]');
	if (stage && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		stage.classList.add('is-rolling');
	}
	if (!form) {
		return;
	}
	const quick = controller.querySelector('[data-dice-quick]');
	const count = quick.querySelector('[data-dice-count]');
	const expression = form.querySelector('[data-dice-expression]');
	const character = form.querySelector('[name="roll[character]"]');
	const attribute = form.querySelector('[name="roll[attribute]"]');
	quick.hidden = false;
	quick.querySelectorAll('[data-choose-die]').forEach((button) => {
		button.addEventListener('click', () => {
			if (!count.reportValidity()) {
				return;
			}
			expression.value = `${Number(count.value)}W${button.dataset.chooseDie}`;
			expression.dispatchEvent(new Event('input', {bubbles: true}));
		});
	});
	const validate = () => {
		attribute.setCustomValidity(attribute.value && !character.value ? attribute.dataset.attributeError : '');
	};
	character.addEventListener('change', validate);
	attribute.addEventListener('change', validate);
	validate();
});

document.addEventListener('submit', (event) => {
	const form = event.target;
	if (event.defaultPrevented || !form.querySelector('[name="pspp_operation"][value="roll_dice"]')) {
		return;
	}
	form.setAttribute('aria-busy', 'true');
	form.querySelector('[type="submit"]').disabled = true;
	const progress = form.querySelector('[data-roll-progress]');
	progress.textContent = progress.dataset.message;
});

window.addEventListener('pageshow', () => {
	document.querySelectorAll('[data-dice-controller] form').forEach((form) => {
		form.removeAttribute('aria-busy');
		form.querySelector('[type="submit"]').disabled = false;
		form.querySelector('[data-roll-progress]').textContent = '';
	});
});
