'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const form = (download) => ({
	listeners: {},
	dataset: { unsavedMessage: 'Unsaved edits' },
	hasAttribute: (attribute) => attribute === 'data-pspp-download' && download,
	addEventListener(type, callback) { this.listeners[type] = callback; },
});
const edit = form(false);
const other = form(false);
const download = form(true);
const documentListeners = {};
const windowListeners = {};
let confirmations = 0;
let accept = true;
const context = vm.createContext({
	document: {
		querySelectorAll: (selector) => selector === '[data-pspp-form]' ? [edit, other, download] : [],
		addEventListener: (type, callback) => { documentListeners[type] = callback; },
	},
	window: {
		addEventListener: (type, callback) => { windowListeners[type] = callback; },
		confirm: () => { confirmations++; return accept; },
	},
});
vm.runInContext(fs.readFileSync(path.join(__dirname, '../assets/admin.js'), 'utf8'), context);
const event = (target) => ({
	target, defaultPrevented: false,
	preventDefault() { this.defaultPrevented = true; },
});

assert.equal(download.listeners.input, undefined);
assert.equal(download.listeners.change, undefined);
edit.listeners.input();
documentListeners.submit(event(download));
assert.equal(confirmations, 0, 'A download must not ask to discard edits.');
const unload = event();
windowListeners.beforeunload(unload);
assert.equal(unload.defaultPrevented, true, 'A download must retain the unsaved-edit warning.');

accept = false;
const cancelled = event(other);
documentListeners.submit(cancelled);
assert.equal(cancelled.defaultPrevented, true, 'Ordinary forms must still protect other unsaved edits.');
assert.equal(confirmations, 1);
documentListeners.submit(event(edit));
const saved = event();
windowListeners.beforeunload(saved);
assert.equal(saved.defaultPrevented, false, 'Submitting the edited form must still clear the warning.');
console.log('Export download preserves unsaved edits; ordinary form behavior passed.');
