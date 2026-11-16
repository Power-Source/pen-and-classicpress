(function ($) {
	'use strict';

	const dock = document.querySelector('[data-chat-view]');
	if (!dock || !$) return;
	$.ajaxPrefilter(function (options, original, request) {
		const url = new URL(options.url, window.location.href);
		if (url.origin !== window.location.origin) return;
		const data = original.data;
		const legacy = data && typeof data === 'object' && data.action === 'chatProcess';
		const route = url.searchParams.get('rest_route') || url.pathname;
		if (legacy || /\/psource-chat\/v1(?:\/|$)/.test(route)) {
			request.setRequestHeader('X-PSPP-View', dock.dataset.chatView);
		}
	});
})(window.jQuery);
