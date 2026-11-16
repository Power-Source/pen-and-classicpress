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

async function checkPortrait(endpoint) {
	function element() {
		return { dataset: {}, style: {}, children: [], isConnected: true, classList: { add() {}, toggle() {} },
			setAttribute() {}, append(...children) { this.children.push(...children); },
			replaceChildren(...children) { this.children = children; } };
	}
	const members = element();
	const feedback = { textContent: '' };
	const table = {
		dataset: { tableEndpoint: endpoint, tableEpoch: 'epoch', tableNonce: 'nonce', tableUser: '7', tableLabels: '{}' },
		classList: { add() {} }, addEventListener() {},
		querySelector(selector) { return selector === '[data-table-members]' ? members : selector === '[data-table-feedback]' ? feedback : null; },
		querySelectorAll() { return []; }
	};
	let tick;
	let shared = true;
	const requests = [];
	vm.runInNewContext(fs.readFileSync(require.resolve('../assets/table.js'), 'utf8'), {
		URL,
		document: { querySelector() { return table; }, createElement: element, createDocumentFragment: element, hidden: false },
		window: { location: { href: 'https://example.test/game', hash: '' }, setInterval(callback) { tick = callback; return 1; }, clearInterval() {} },
		async fetch(url, options) {
			requests.push({ url, options });
			const portrait = ( url.searchParams.get('rest_route') || url.pathname ).endsWith('/portrait');
			return { ok: true, async json() { return portrait ? { image: 'data:image/png;base64,test' } : {
				members: [{ id: 7, name: shared ? 'Hero' : 'Player', status: 'unreported', role: 'player',
					portrait_key: shared ? 'image-key' : '', portrait_style: 'object-fit:contain;' }],
				turn: { user_id: 0, available: true }
			}; } };
		}
	});
	await new Promise(resolve => setImmediate(resolve));
	assert.equal(feedback.textContent, '');
	assert.equal(requests.length, 2);
	const imageRequest = requests[1];
	assert.equal(imageRequest.url.searchParams.get('scope_epoch'), 'epoch');
	assert.equal(imageRequest.options.headers['X-WP-Nonce'], 'nonce');
	assert.equal(imageRequest.url.searchParams.get('key'), 'image-key');
	assert.equal(imageRequest.url.searchParams.get('rest_route') || imageRequest.url.pathname, '/ps-pen-paper/v1/campaigns/5/participants/7/portrait');
	assert.equal(members.children[0].children[0].children[0].children[0].src, 'data:image/png;base64,test');
	tick();
	await new Promise(resolve => setImmediate(resolve));
	assert.equal(requests.length, 3, 'Unchanged portraits should reuse the protected image cache.');
	shared = false;
	tick();
	await new Promise(resolve => setImmediate(resolve));
	const portrait = members.children[0].children[0].children[0];
	assert.equal(portrait.textContent, 'P');
	assert.equal(portrait.children.length, 0, 'Revoked portrait remained visible after polling.');
}

(async function () {
	await check('11111111-1111-4111-8111-111111111111');
	await check('');
	await checkGroupLink();
	await checkPortrait('https://example.test/?rest_route=/ps-pen-paper/v1/campaigns/5/table');
	await checkPortrait('https://example.test/ps-pen-paper/v1/campaigns/5/table');
	console.log('Table requests retain REST query parameters and epoch; replaced contexts stop polling and edits.');
}()).catch(error => { console.error(error); process.exitCode = 1; });
