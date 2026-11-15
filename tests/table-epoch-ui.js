'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

async function check(epoch) {
	const feedback = { textContent: '' };
	const buttons = [{ disabled: false }];
	const table = {
		dataset: {
			tableEndpoint: 'https://example.test/?rest_route=/ps-pen-paper/v1/campaigns/5/table',
			tableEpoch: epoch, tableNonce: 'nonce', tableUser: '7', tableLabels: '{"error":"Error"}'
		},
		classList: { add() {} },
		querySelector(selector) { return selector === '[data-table-feedback]' ? feedback : null; },
		querySelectorAll(selector) { return selector === '[data-presence], [data-turn-form] button' ? buttons : []; },
		addEventListener() {}
	};
	let cleared = false;
	let requested;
	vm.runInNewContext(fs.readFileSync(require.resolve('../assets/table.js'), 'utf8'), {
		URL,
		document: { querySelector() { return table; }, hidden: false },
		window: {
			location: { href: 'https://example.test/game', hash: '' },
			setInterval() { return 1; }, clearInterval() { cleared = true; }
		},
		async fetch(url) {
			requested = url;
			return { ok: false, status: 409, async json() { return { code: 'scope_changed', message: 'Reload restored campaign' }; } };
		}
	});
	await new Promise(resolve => setImmediate(resolve));
	assert.equal(requested.searchParams.get('rest_route'), '/ps-pen-paper/v1/campaigns/5/table');
	assert.equal(requested.searchParams.get('scope_epoch'), epoch);
	assert.equal(feedback.textContent, 'Reload restored campaign');
	assert.equal(cleared, true);
	assert.equal(buttons[0].disabled, true);
}

async function checkGroupLink() {
	const panels = ['play', 'adventures'].map(name => ({ dataset: { tablePanel: name }, hidden: false }));
	const feedback = { textContent: '' };
	let scrolled = false;
	const target = { closest() { return panels[1]; }, scrollIntoView() { scrolled = true; } };
	const table = {
		dataset: { tableEndpoint: 'https://example.test/table', tableLabels: '{}', tableUser: '7' },
		classList: { add() {} },
		querySelector(selector) { return selector === '[data-table-feedback]' ? feedback : null; },
		querySelectorAll(selector) { return selector === '[data-table-panel]' ? panels : []; },
		contains(node) { return node === target; },
		addEventListener() {}
	};
	vm.runInNewContext(fs.readFileSync(require.resolve('../assets/table.js'), 'utf8'), {
		URL,
		document: { querySelector() { return table; }, getElementById() { return target; }, hidden: true },
		window: { location: { hash: '#pspp-adventure-group-42' }, addEventListener() {}, setInterval() { return 1; }, clearInterval() {} },
		async fetch() { return { ok: false, status: 409, async json() { return { code: 'scope_changed', message: 'Reload' }; } }; }
	});
	await new Promise(resolve => setImmediate(resolve));
	assert.equal(panels[0].hidden, true);
	assert.equal(panels[1].hidden, false);
	assert.equal(scrolled, true);
}

(async function () {
	await check('11111111-1111-4111-8111-111111111111');
	await check('');
	await checkGroupLink();
	console.log('Table requests retain REST query parameters and epoch; replaced contexts stop polling and edits.');
}()).catch(error => { console.error(error); process.exitCode = 1; });
