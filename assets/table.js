(function () {
	'use strict';

	const table = document.querySelector('.pspp-table');
	if (!table) return;
	const labels = JSON.parse(table.dataset.tableLabels);
	const panels = Array.from(table.querySelectorAll('[data-table-panel]'));
	const tabs = table.querySelectorAll('[data-table-view]');
	const feedback = table.querySelector('[data-table-feedback]');
	const controls = table.querySelector('[data-presence-controls]');
	const activeControls = table.querySelector('[data-presence-active]');
	const join = table.querySelector('[data-presence="join"]');
	const turnForm = table.querySelector('[data-turn-form]');
	const endpoint = table.dataset.tableEndpoint;
	const epoch = table.dataset.tableEpoch || '';
	const userId = Number(table.dataset.tableUser);
	let sharing = false;
	let busy = false;
	let turnRevision = 0;
	let turnDirty = false;
	let stopped = false;
	let initialized = false;
	let timer;
	const portraits = new Map();

	function renderPortrait(portrait, member) {
		if (!member.portrait_key) return;
		if (!portraits.has(member.portrait_key)) {
			const url = new URL(endpoint, window.location.href);
			if (url.searchParams.has('rest_route')) url.searchParams.set('rest_route', url.searchParams.get('rest_route').replace(/\/table\/?$/, '/participants/' + member.id + '/portrait'));
			else url.pathname = url.pathname.replace(/\/table\/?$/, '/participants/' + member.id + '/portrait');
			url.searchParams.set('scope_epoch', epoch);
			url.searchParams.set('key', member.portrait_key);
			portraits.set(member.portrait_key, fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-WP-Nonce': table.dataset.tableNonce } }).then(async function (response) {
				const data = await response.json();
				if (!response.ok) throw new Error(data.message || labels.error);
				return data.image;
			}));
		}
		portraits.get(member.portrait_key).then(function (source) {
			if (stopped || !portrait.isConnected) return;
			const image = document.createElement('img');
			image.src = source;
			image.alt = '';
			image.style.cssText = member.portrait_style;
			portrait.replaceChildren(image);
		}).catch(function (error) {
			portraits.delete(member.portrait_key);
			if (!portrait.isConnected) return;
			const message = document.createElement('small');
			message.setAttribute('role', 'alert');
			message.textContent = error.message || labels.error;
			portrait.append(message);
		});
	}

	table.classList.add('pspp-table-ready');
	function showView(view) {
		if (!panels.some(function (panel) { return panel.dataset.tablePanel === view; })) view = 'play';
		const stage = view === 'group' ? 'play' : view;
		table.classList.toggle('pspp-table-group-open', view === 'group');
		const playing = ['play', 'adventures', 'sheet', 'dice', 'log', 'handouts', 'group'].includes(view);
		panels.forEach(function (panel) {
			const name = panel.dataset.tablePanel;
			panel.hidden = name !== stage && !(name === 'group' && playing);
		});
		const groupControls = table.querySelector('[data-group-controls]');
		if (groupControls) groupControls.open = view === 'group';
		const management = table.querySelector('[data-table-management]');
		if (management && !playing) management.open = true;
		tabs.forEach(function (tab) {
			if (tab.dataset.tableView === view) tab.setAttribute('aria-current', 'page');
			else tab.removeAttribute('aria-current');
		});
	}

	const hashes = { '#pspp-play': 'play', '#pspp-sheet': 'sheet', '#pspp-group': 'group', '#pspp-group-adventures': 'play', '#pspp-dice': 'dice', '#pspp-log': 'log', '#pspp-rulebook': 'rules', '#pspp-settings': 'settings', '#pspp-story': 'story', '#pspp-folio': 'folio', '#pspp-handouts': 'handouts', '#pspp-equipment': 'equipment' };
	function followHash() {
		const target = document.getElementById(window.location.hash.slice(1));
		const panel = target && table.contains(target) ? target.closest('[data-table-panel]') : null;
		showView(hashes[window.location.hash] || (panel ? panel.dataset.tablePanel : table.dataset.tableInitialView || 'play'));
		if (panel && target !== panel) target.scrollIntoView({ block: 'start' });
	}
	if (panels.length) {
		followHash();
		window.addEventListener('hashchange', followHash);
		table.addEventListener('click', function (event) {
			const link = event.target.closest('a[href^="#pspp-"]');
			if (link && hashes[link.hash]) {
				showView(hashes[link.hash]);
				if (link.hasAttribute('data-group-toggle')) table.querySelector('[data-group-controls]').open = true;
			}
		});
	}

	function render(state) {
		const list = table.querySelector('[data-table-members]');
		if (list) {
			const fragment = document.createDocumentFragment();
			state.members.forEach(function (member) {
				const item = document.createElement('li');
				item.dataset.memberId = member.id;
				item.dataset.memberStatus = member.status;
				item.classList.toggle('is-turn', member.id === state.turn.user_id && state.turn.available);
				const portrait = document.createElement('span');
				portrait.className = 'pspp-portrait';
				portrait.setAttribute('aria-hidden', 'true');
				portrait.textContent = Array.from(member.name)[0] || '';
				renderPortrait(portrait, member);
				const info = document.createElement('div');
				const name = document.createElement('strong');
				name.textContent = member.name;
				const role = document.createElement('small');
				role.textContent = labels[member.role];
				const status = document.createElement('span');
				status.textContent = labels[member.status] + (member.note ? ' · ' + member.note : '');
				info.append(name, role, status);
				item.append(portrait, info);
				fragment.append(item);
			});
			list.replaceChildren(fragment);
		}
		table.querySelectorAll('[data-table-turn]').forEach(function (banner) {
			banner.textContent = !state.turn.available ? labels.unavailable : (state.turn.user_id ? labels.turn + ' ' : '') + state.turn.name;
		});
		if (turnForm && !turnDirty) {
			const select = turnForm.querySelector('select');
			select.querySelectorAll('option:not([value="0"])').forEach(function (option) { option.remove(); });
			state.members.forEach(function (member) {
				const option = document.createElement('option');
				option.value = member.id;
				option.textContent = member.name;
				select.append(option);
			});
			select.value = state.turn.available ? String(state.turn.user_id) : '0';
			turnRevision = state.turn.revision;
		}
		if (activeControls) activeControls.hidden = !sharing;
		if (join) join.hidden = sharing;
		const self = state.members.find(function (member) { return member.id === userId; });
		if (controls) controls.hidden = !self;
		const rolls = table.querySelector('[data-chat-rolls]');
		if (rolls && state.chat_rolls) {
			const current = new Map(Array.from(rolls.children).map(function (item) { return [Number(item.dataset.rollId), item]; }));
			const ids = new Set(state.chat_rolls.map(function (roll) { return roll.id; }));
			Array.from(rolls.children).forEach(function (item) { if (!ids.has(Number(item.dataset.rollId))) item.remove(); });
			state.chat_rolls.forEach(function (roll) {
				if (current.has(roll.id)) return;
				const item = document.createElement('li');
				item.dataset.rollId = roll.id;
				item.textContent = roll.text;
				rolls.append(item);
			});
		}
	}

	async function request(input) {
		const url = new URL(endpoint, window.location.href);
		url.searchParams.set('scope_epoch', epoch);
		const response = await fetch(url, {
			method: input ? 'POST' : 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'X-WP-Nonce': table.dataset.tableNonce, 'Content-Type': 'application/json' },
			body: input ? JSON.stringify(input) : undefined
		});
		const data = await response.json();
		if (!response.ok) {
			if (data.code === 'presence_inactive') {
				sharing = false;
				if (activeControls) activeControls.hidden = true;
				if (join) join.hidden = false;
			}
			if (response.status === 401 || response.status === 403 || data.code === 'scope_changed') {
				stopped = true;
				window.clearInterval(timer);
				table.querySelectorAll('[data-presence], [data-turn-form] button').forEach(function (button) { button.disabled = true; });
			}
			throw new Error(data.message || labels.error);
		}
		return data;
	}

	async function update(input) {
		if (busy || stopped) return;
		busy = true;
		table.querySelectorAll('[data-presence], [data-turn-form] button').forEach(function (button) { button.disabled = true; });
		try {
			const state = await request(input);
			if (!initialized) {
				const self = state.members.find(function (member) { return member.id === userId; });
				sharing = !!self && ['online', 'afk'].includes(self.status);
				initialized = true;
			}
			if (input && ['join', 'afk', 'back'].includes(input.operation)) sharing = true;
			if (input && input.operation === 'leave') sharing = false;
			if (input && input.operation === 'turn') turnDirty = false;
			render(state);
			feedback.textContent = input && input.operation !== 'heartbeat' ? (sharing ? labels.sharing : (input.operation === 'leave' ? labels.stopped : '')) : '';
		} catch (error) {
			feedback.textContent = error.message || labels.error;
		} finally {
			busy = false;
			if (!stopped) table.querySelectorAll('[data-presence], [data-turn-form] button').forEach(function (button) { button.disabled = false; });
		}
	}

	if (controls) {
		controls.addEventListener('click', function (event) {
			const button = event.target.closest('[data-presence]');
			if (!button) return;
			const operation = button.dataset.presence;
			update({ operation: operation, note: table.querySelector('#pspp-away-note').value });
		});
	}
	if (turnForm) {
		turnForm.hidden = false;
		turnForm.addEventListener('change', function () { turnDirty = true; });
		turnForm.addEventListener('submit', function (event) {
			event.preventDefault();
			update({ operation: 'turn', user_id: Number(turnForm.querySelector('select').value), revision: turnRevision });
		});
	}

	const dock = table.querySelector('[data-table-chat]');
	if (dock) {
		const badge = dock.querySelector('[data-chat-unread]');
		const known = new Set();
		let unread = 0;
		new MutationObserver(function () {
			const ids = Array.from(dock.querySelectorAll('.psource-chat-row[id]')).map(function (row) { return row.id; });
			if (!ids.length) return;
			ids.forEach(function (id) {
				if (!known.has(id)) unread += 1;
				known.add(id);
			});
			if (dock.open) unread = 0;
			badge.hidden = !unread;
			badge.textContent = labels.new + ': ' + unread;
		}).observe(dock.querySelector('.pspp-chat-body'), { childList: true, subtree: true });
		dock.addEventListener('toggle', function () {
			if (dock.open) {
				unread = 0;
				badge.hidden = true;
			}
		});
		dock.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && dock.open) {
				dock.open = false;
				dock.querySelector('summary').focus();
			}
		});
	}

	timer = window.setInterval(function () {
		if (!document.hidden) update(sharing ? { operation: 'heartbeat' } : undefined);
	}, 30000);
	update();
}());
