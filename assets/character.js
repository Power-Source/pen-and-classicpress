(function () {
	'use strict';

	document.querySelectorAll('[data-image-view]').forEach(function (fields) {
		const image = fields.querySelector('[data-image-view-preview]');
		if (!image) return;
		function update() {
			const x = fields.querySelector('[name="image_view[x]"]');
			const y = fields.querySelector('[name="image_view[y]"]');
			const fit = fields.querySelector('[name="image_view[fit]"]');
			if (!x.checkValidity() || !y.checkValidity()) return;
			image.style.objectPosition = x.value + '% ' + y.value + '%';
			image.style.objectFit = fit.value;
		}
		fields.addEventListener('input', update);
		fields.addEventListener('change', update);
	});

	document.querySelectorAll('[data-character-allocation]').forEach(function (counter) {
		const inputs = Array.from(counter.closest('form').querySelectorAll('[data-character-allocation-field] input'));
		const budget = Number(counter.dataset.pointBudget);
		function update() {
			const spent = inputs.reduce(function (total, input) { return total + Number(input.value); }, 0);
			inputs.forEach(function (input) { input.setCustomValidity(Number.isFinite(spent) && spent > budget ? counter.dataset.pointError : ''); });
			if (!Number.isFinite(spent)) return;
			counter.textContent = counter.dataset.pointMessage.replace('%1$d', budget).replace('%2$d', budget - spent);
		}
		inputs.forEach(function (input) { input.addEventListener('input', update); });
		update();
	});

	document.querySelectorAll('[data-character-tabs]').forEach(function (sheet) {
		const navigation = sheet.querySelector('.pspp-character-tab-nav');
		const tabs = Array.from(navigation.querySelectorAll('[data-character-tab]'));
		const panels = Array.from(sheet.querySelectorAll('[data-character-panel]'));
		navigation.setAttribute('role', 'tablist');
		tabs.forEach(function (tab) {
			tab.setAttribute('role', 'tab');
			tab.setAttribute('aria-controls', tab.hash.slice(1));
		});
		panels.forEach(function (panel) {
			panel.setAttribute('role', 'tabpanel');
			const tab = tabs.find(function (candidate) { return candidate.dataset.characterTab === panel.dataset.characterPanel; });
			panel.setAttribute('aria-labelledby', tab.id);
		});
		function select(tab, focus) {
			tabs.forEach(function (candidate) {
				const active = candidate === tab;
				candidate.setAttribute('aria-selected', String(active));
				candidate.tabIndex = active ? 0 : -1;
			});
			panels.forEach(function (panel) { panel.hidden = panel.dataset.characterPanel !== tab.dataset.characterTab; });
			if (focus) tab.focus();
		}
		function followHash() {
			const target = document.getElementById(window.location.hash.slice(1));
			const panel = target && sheet.contains(target) ? target.closest('[data-character-panel]') : null;
			if (panel) select(tabs.find(function (tab) { return tab.dataset.characterTab === panel.dataset.characterPanel; }), false);
			else if (window.location.hash === '#pspp-adventures') select(tabs.find(function (tab) { return tab.dataset.characterTab === 'adventures'; }), false);
		}
		select(tabs[0], false);
		followHash();
		window.addEventListener('hashchange', followHash);
		tabs.forEach(function (tab, index) {
			tab.addEventListener('click', function () { select(tab, false); });
			tab.addEventListener('keydown', function (event) {
				let next;
				if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
				else if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
				else if (event.key === 'Home') next = 0;
				else if (event.key === 'End') next = tabs.length - 1;
				else return;
				event.preventDefault();
				select(tabs[next], true);
			});
		});
	});
}());
