'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/chat.js'), 'utf8');

for (const view of ['overview', 'lead']) {
	let filter;
	vm.runInNewContext(source, {
		URL,
		window: {
			location: new URL('http://example.test/game/'),
			jQuery: { ajaxPrefilter(callback) { filter = callback; } }
		},
		document: { querySelector() { return { dataset: { chatView: view } }; } }
	});
	for (const [url, data, expected] of [
		['/wp-admin/admin-ajax.php', { action: 'chatProcess' }, view],
		['/wp-json/psource-chat/v1/messages', {}, view],
		['/?rest_route=%2Fpsource-chat%2Fv1%2Fmessages', {}, view],
		['/wp-json/other/v1/messages', {}, undefined],
		['/wp-admin/admin-ajax.php', { action: 'other' }, undefined],
		['https://other.test/wp-json/psource-chat/v1/messages', {}, undefined]
	]) {
		const headers = {};
		filter({ url }, { data }, { setRequestHeader(name, value) { headers[name] = value; } });
		assert.equal(headers['X-PSPP-View'], expected, url);
	}
}
console.log('Chat workspace stays per request for legacy/REST transports and never reaches unrelated requests.');
