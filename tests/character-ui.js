'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const listeners = {};
const tabs = ['overview', 'stats', 'inventory', 'adventures', 'edit'].map(name => ({
	id: 'character-tab-' + name,
	hash: '#character-' + name,
	dataset: { characterTab: name },
	attributes: {},
	events: {},
	setAttribute(key, value) { this.attributes[key] = value; },
	addEventListener(type, callback) { this.events[type] = callback; },
	focus() { this.focused = true; }
}));
const panels = tabs.map(tab => ({
	id: tab.hash.slice(1),
	dataset: { characterPanel: tab.dataset.characterTab },
	hidden: false,
	attributes: {},
	setAttribute(key, value) { this.attributes[key] = value; },
	closest() { return this; }
}));
const sheet = {
	querySelector() { return { setAttribute() {}, querySelectorAll() { return tabs; } }; },
	querySelectorAll() { return panels; },
	contains(target) { return panels.includes(target); }
};
const location = { hash: '' };
const pointInputs = [0, 0].map(value => ({
	value: String(value), events: {}, validity: '',
	setCustomValidity(message) { this.validity = message; },
	addEventListener(name, callback) { this.events[name] = callback; }
}));
const counter = {
	dataset: { pointBudget: '5', pointMessage: '%1$d total, %2$d remaining', pointError: 'Budget exceeded' },
	closest() { return { querySelectorAll() { return pointInputs; } }; }
};
const image = { style: {} };
const imageEvents = {};
const positions = { x: { value: '75', checkValidity() { return Number(this.value) <= 100; } }, y: { value: '20', checkValidity() { return true; } }, fit: { value: 'cover' } };
const imageFields = {
	querySelector(selector) { return selector === '[data-image-view-preview]' ? image : positions[selector.match(/\[(x|y|fit)\]/)[1]]; },
	addEventListener(name, callback) { imageEvents[name] = callback; }
};
vm.runInNewContext(fs.readFileSync(require.resolve('../assets/character.js'), 'utf8'), {
	document: {
		querySelectorAll(selector) {
			if (selector === '[data-character-allocation]') return [counter];
			if (selector === '[data-image-view]') return [imageFields];
			return [sheet];
		},
		getElementById(id) { return panels.find(panel => panel.id === id) || null; }
	},
	window: { location, addEventListener(name, callback) { listeners[name] = callback; } }
});
imageEvents.input();
assert.equal(image.style.objectPosition, '75% 20%');
positions.fit.value = 'contain'; imageEvents.change();
assert.equal(image.style.objectFit, 'contain');
positions.x.value = '200'; imageEvents.input();
assert.equal(image.style.objectPosition, '75% 20%');
assert.equal(counter.textContent, '5 total, 5 remaining');
pointInputs[0].value = '3'; pointInputs[1].value = '2'; pointInputs[0].events.input();
assert.equal(counter.textContent, '5 total, 0 remaining');
assert.equal(pointInputs[0].validity, '');
pointInputs[1].value = '3'; pointInputs[1].events.input();
assert.equal(pointInputs[0].validity, 'Budget exceeded');
pointInputs[1].value = '0'; pointInputs[1].events.input();
assert.equal(counter.textContent, '5 total, 2 remaining');
assert.equal(pointInputs[0].validity, '');

function visible(name) {
	assert.deepEqual(panels.filter(panel => !panel.hidden).map(panel => panel.dataset.characterPanel), [name]);
	assert.equal(tabs.find(tab => tab.dataset.characterTab === name).attributes['aria-selected'], 'true');
}
visible('overview');
tabs[2].events.click();
visible('inventory');
let prevented = false;
tabs[2].events.keydown({ key: 'ArrowRight', preventDefault() { prevented = true; } });
visible('adventures');
assert.equal(prevented, true);
assert.equal(tabs[3].focused, true);
location.hash = '#character-stats';
listeners.hashchange();
visible('stats');
location.hash = '#pspp-adventures';
listeners.hashchange();
visible('adventures');
assert.equal(panels[3].attributes['aria-labelledby'], tabs[3].id);
assert.equal(tabs[3].attributes['aria-controls'], panels[3].id);
console.log('Character tabs switch panels, support keyboard navigation and preserve direct/legacy links.');
