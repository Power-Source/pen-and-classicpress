(function () {
	'use strict';

	document.querySelectorAll('[data-map-token-editor]').forEach(function (editor) {
		const rows = editor.querySelector('[data-map-token-rows]');
		const add = editor.querySelector('[data-map-add-token]');
		let index = rows.children.length;
		add.hidden = false;
		add.addEventListener('click', function () {
			if (rows.children.length >= 30) {
				editor.querySelector('[data-map-editor-feedback]').textContent = editor.dataset.limitMessage;
				return;
			}
			const fragment = editor.querySelector('template').content.cloneNode(true);
			fragment.querySelectorAll('[name]').forEach(function (input) { input.name = input.name.replace('__INDEX__', String(index)); });
			index++;
			rows.append(fragment);
			rows.lastElementChild.querySelector('input:not([type="hidden"])').focus();
			editor.dispatchEvent(new Event('change', {bubbles:true}));
		});
		editor.addEventListener('click', function (event) {
			const remove = event.target.closest('[data-map-remove-token]');
			if (!remove) return;
			remove.closest('[data-map-token-row]').remove();
			editor.dispatchEvent(new Event('change', {bubbles:true}));
		});
	});

	document.querySelectorAll('[data-map]').forEach(function (root, mapIndex) {
		const preview = root.dataset.mapPreview === 'true';
		const labels = JSON.parse(root.dataset.mapLabels);
		const svg = root.querySelector('[data-map-svg]');
		const image = root.querySelector('[data-map-image]');
		const tokenLayer = root.querySelector('[data-map-tokens]');
		const pingLayer = root.querySelector('[data-map-pings]');
		const feedback = root.querySelector('[data-map-feedback]');
		const form = root.querySelector('[data-map-move]');
		const pingButton = root.querySelector('[data-map-ping]');
		const stage = preview ? null : root.closest('.pspp-scene');
		const fallback = stage && stage.querySelector('[data-map-fallback]');
		const pointers = new Map();
		let state = JSON.parse(root.dataset.mapState);
		let selected = '';
		let imageKey = state.scene ? state.scene.id + ':' + state.scene.asset_id : '';
		let view = {x:0,y:0,w:1000,h:700};
		let drag = null;
		let pinchDistance = 0;
		let busy = false;
		let loading = false;
		let refreshPromise = Promise.resolve();
		let stopped = false;
		let pingMode = false;
		let pingId = '';
		let pingTimer;
		let timer;
		root.removeAttribute('data-map-state');

		function reportError(error) {
			feedback.textContent = error.message || labels.error;
			if (root.hidden && stage) {
				const status = document.querySelector('[data-table-feedback]');
				if (status) status.textContent = feedback.textContent;
			}
		}
		function element(name, attributes, text) {
			const node = document.createElementNS('http://www.w3.org/2000/svg', name);
			Object.entries(attributes).forEach(function (entry) { node.setAttribute(entry[0], entry[1]); });
			if (text !== undefined) node.textContent = text;
			return node;
		}
		function point(event) {
			const p = svg.createSVGPoint();
			p.x = event.clientX;
			p.y = event.clientY;
			return p.matrixTransform(svg.getScreenCTM().inverse());
		}
		function applyView() {
			svg.setAttribute('viewBox', [view.x,view.y,view.w,view.h].join(' '));
		}
		function fit() {
			if (!state.scene) return;
			view = {x:0,y:0,w:state.scene.width,h:state.scene.height};
			applyView();
		}
		function zoom(factor, center) {
			if (!state.scene) return;
			const width = Math.max(state.scene.width / 6, Math.min(state.scene.width, view.w / factor));
			const ratio = width / view.w;
			const c = center || {x:view.x + view.w / 2,y:view.y + view.h / 2};
			view = {x:c.x - (c.x - view.x) * ratio,y:c.y - (c.y - view.y) * ratio,w:width,h:width * state.scene.height / state.scene.width};
			view.x = Math.max(0, Math.min(state.scene.width - view.w, view.x));
			view.y = Math.max(0, Math.min(state.scene.height - view.h, view.y));
			applyView();
		}
		function select(id, fill = true) {
			selected = id;
			const token = state.scene && state.scene.tokens.find(function (item) {return item.id === id;});
			root.querySelector('[data-map-selection]').textContent = token ? labels.selected + ' ' + token.name : '';
			tokenLayer.querySelectorAll('[data-token-id]').forEach(function (node) {node.classList.toggle('is-selected', node.dataset.tokenId === id);});
			if (form && token) {
				form.elements.token.value = id;
				if (fill) {
					form.elements.x.value = token.x / 100;
					form.elements.y.value = token.y / 100;
				}
			}
		}
		async function request(url, input) {
			const controller = new AbortController();
			const timeout = window.setTimeout(function () {controller.abort();}, 20000);
			try {
				const response = await fetch(url, {
					method:input ? 'POST' : 'GET',credentials:'same-origin',cache:'no-store',signal:controller.signal,
					headers:{'X-WP-Nonce':root.dataset.mapNonce,'X-PSPP-View':root.dataset.mapView || 'overview','Content-Type':'application/json'},
					body:input ? JSON.stringify(input) : undefined
				});
				const data = await response.json();
				if (!response.ok) {
					if (response.status === 401 || (response.status === 403 && data.code !== 'image_not_released')) {
						stopped = true;
						window.clearInterval(timer);
						state = {scene:null,ping:null};
						root.hidden = true;
						image.removeAttribute('href');
						tokenLayer.replaceChildren();
						pingLayer.replaceChildren();
						const status = document.querySelector('[data-table-feedback]');
						if (status) status.textContent = data.message || labels.error;
					}
					throw new Error(data.message || labels.error);
				}
				return data;
			} finally {window.clearTimeout(timeout);}
		}
		async function render(next) {
			const previous = state.scene;
			state = next;
			const scene = state.scene;
			root.hidden = !scene;
			if (stage) stage.classList.toggle('pspp-has-map', !!scene);
			if (fallback) fallback.hidden = !!scene;
			if (!scene) {
				image.removeAttribute('href');
				tokenLayer.replaceChildren();
				pingLayer.replaceChildren();
				imageKey = '';
				return;
			}
			const changed = !previous || previous.id !== scene.id || previous.publication !== scene.publication;
			const canvas = !!(scene.asset_id || scene.grid || scene.tokens.length);
			root.querySelector('[data-map-tools]').hidden = !canvas;
			root.querySelector('.pspp-map-viewport').hidden = !canvas;
			root.querySelector('.pspp-map-help').hidden = !canvas;
			const movement = root.querySelector('.pspp-map-movement');
			if (movement) movement.hidden = !canvas;
			root.querySelector('[data-map-title]').textContent = scene.title;
			root.querySelector('[data-map-text]').textContent = scene.text;
			if (changed) {selected = ''; drag = null; pointers.clear(); tokenLayer.replaceChildren(); pingLayer.replaceChildren(); fit();}
			image.setAttribute('width', scene.width);
			image.setAttribute('height', scene.height);
			const key = scene.id + ':' + scene.asset_id;
			if (imageKey !== key) {
				image.removeAttribute('href');
				if (scene.asset_id) {
					const data = await request(root.dataset.mapEndpoint.replace(/\/map$/, '/maps/' + scene.id + '/images/' + scene.asset_id));
					image.setAttribute('href', data.image);
				}
				imageKey = key;
			}
			const grid = root.querySelector('[data-map-grid]');
			grid.replaceChildren();
			if (scene.grid) {
				const id = 'pspp-map-grid-' + mapIndex;
				const pattern = element('pattern', {id:id,width:scene.grid,height:scene.grid,patternUnits:'userSpaceOnUse'});
				pattern.append(element('path', {d:'M ' + scene.grid + ' 0 L 0 0 0 ' + scene.grid,fill:'none',stroke:'#ffffff','stroke-opacity':0.35,'stroke-width':1,'vector-effect':'non-scaling-stroke'}));
				const defs = element('defs', {});
				defs.append(pattern);
				grid.append(defs, element('rect', {width:scene.width,height:scene.height,fill:'url(#' + id + ')'}));
			}
			const radius = Math.max(12, Math.min(scene.width, scene.height) / 30);
			tokenLayer.replaceChildren();
			scene.tokens.forEach(function (token) {
				const group = element('g', {'data-token-id':token.id,transform:'translate(' + token.x / 10000 * scene.width + ',' + token.y / 10000 * scene.height + ')',tabindex:token.can_move ? 0 : -1,role:'button','aria-label':token.name,'data-movable':token.can_move ? 'true' : 'false'});
				group.append(element('title', {}, token.name));
				group.append(element(token.type === 'object' ? 'rect' : 'circle', token.type === 'object' ? {x:-radius,y:-radius,width:radius * 2,height:radius * 2,rx:4,fill:token.color} : {r:radius,fill:token.color}));
				group.append(element('text', {y:radius * 0.32,'text-anchor':'middle','font-size':radius,'aria-hidden':'true'}, Array.from(token.name)[0]));
				group.append(element('text', {y:radius * 1.8,'text-anchor':'middle','font-size':radius * 0.6,class:'pspp-map-token-name','aria-hidden':'true'}, token.name));
				tokenLayer.append(group);
			});
			if (form) {
				const dirty = form.contains(document.activeElement);
				const own = scene.tokens.filter(function (token) {return token.can_move;});
				const selectNode = form.elements.token;
				selectNode.replaceChildren();
				const placeholder = document.createElement('option');
				placeholder.value = '';
				placeholder.textContent = own.length ? labels.choose : labels.none;
				selectNode.append(placeholder);
				own.forEach(function (token) {const option = document.createElement('option');option.value = token.id;option.textContent = token.name;selectNode.append(option);});
				form.querySelectorAll('input,select,button').forEach(function (input) {input.disabled = !own.length || busy;});
				if (!own.some(function (token) {return token.id === selected;})) selected = '';
				selectNode.value = selected;
				select(selected, !dirty || changed);
			}
			if (state.ping && state.ping.id !== pingId && state.ping.expires_in > 0) {
				pingId = state.ping.id;
				feedback.textContent = labels.ping_from + ' ' + state.ping.name + ' · X ' + state.ping.x / 100 + ' % · Y ' + state.ping.y / 100 + ' %';
				pingLayer.replaceChildren();
				const group = element('g', {transform:'translate(' + state.ping.x / 10000 * scene.width + ',' + state.ping.y / 10000 * scene.height + ')'});
				group.append(element('circle', {r:radius * 1.7,class:'pspp-map-ping'}), element('text', {y:-radius * 2,'text-anchor':'middle','font-size':radius * 0.8,class:'pspp-map-token-name'}, state.ping.name));
				pingLayer.append(group);
				window.clearTimeout(pingTimer);
				pingTimer = window.setTimeout(function () {pingLayer.replaceChildren();}, state.ping.expires_in * 1000);
			} else if (changed) {pingLayer.replaceChildren();pingId = '';}
		}
		async function refresh() {
			if (preview || busy || loading || stopped || pointers.size || document.hidden) return;
			loading = true;
			refreshPromise = (async function () {
				try {
					const next = await request(root.dataset.mapEndpoint);
					if (!pointers.size) await render(next);
				}
				catch (error) {reportError(error);}
				finally {loading = false;}
			}());
			await refreshPromise;
		}
		async function action(operation, position, tokenId) {
			if (preview || stopped || !state.scene) return;
			if (busy) {feedback.textContent = labels.wait;await render(state);return;}
			const expectedScene = state.scene.id;
			const expectedPublication = state.scene.publication;
			busy = true;
			if (form) form.querySelectorAll('input,select,button').forEach(function (input) {input.disabled = true;});
			try {
				await refreshPromise;
				if (stopped || !state.scene || state.scene.id !== expectedScene || state.scene.publication !== expectedPublication) throw new Error(labels.wait);
				await render(await request(root.dataset.mapEndpoint, {operation:operation,scene_id:state.scene.id,revision:state.scene.revision,publication:state.scene.publication,token_id:tokenId,x:Math.max(0,Math.min(10000,Math.round(position.x))),y:Math.max(0,Math.min(10000,Math.round(position.y)))}));
				feedback.textContent = operation === 'move' ? labels.moved : labels.ping;
			} catch (error) {
				feedback.textContent = error.message || labels.error;
				if (!stopped) {
					try {await render(await request(root.dataset.mapEndpoint));}
					catch (refreshError) {feedback.textContent += ' ' + (refreshError.message || labels.error);}
				}
			} finally {
				busy = false;
				if (!stopped && form) form.querySelectorAll('input,select,button').forEach(function (input) {input.disabled = !state.scene || !state.scene.tokens.some(function (token) {return token.can_move;});});
			}
		}
		root.querySelectorAll('[data-map-zoom]').forEach(function (button) {button.addEventListener('click', function () {if (button.dataset.mapZoom === 'fit') fit();else zoom(button.dataset.mapZoom === 'in' ? 1.4 : 1 / 1.4);});});
		if (pingButton) pingButton.addEventListener('click', function () {pingMode = !pingMode;pingButton.setAttribute('aria-pressed', String(pingMode));});
		if (form) {
			form.elements.token.addEventListener('change', function () {select(form.elements.token.value);});
			form.addEventListener('submit', function (event) {event.preventDefault();action('move',{x:Number(form.elements.x.value) * 100,y:Number(form.elements.y.value) * 100},form.elements.token.value);});
		}
		svg.addEventListener('error', function (event) {if (event.target === image) feedback.textContent = labels.image;}, true);
		svg.addEventListener('wheel', function (event) {if (!state.scene) return;event.preventDefault();zoom(event.deltaY < 0 ? 1.15 : 1 / 1.15, point(event));}, {passive:false});
		svg.addEventListener('keydown', function (event) {
			const node = event.target.closest('[data-token-id]');
			if (node && node.dataset.movable === 'true' && (event.key === 'Enter' || event.key === ' ')) {event.preventDefault();select(node.dataset.tokenId);if (form) {form.closest('details').open = true;form.elements.x.focus();}}
		});
		svg.addEventListener('pointerdown', function (event) {
			if (!state.scene || event.button > 0) return;
			if (busy) {feedback.textContent = labels.wait;return;}
			svg.setPointerCapture(event.pointerId);
			pointers.set(event.pointerId,{x:event.clientX,y:event.clientY});
			if (pointers.size > 1) {drag = null;pinchDistance = 0;return;}
			const node = event.target.closest('[data-token-id]');
			const movable = node && node.dataset.movable === 'true' && !preview;
			if (movable) select(node.dataset.tokenId);
			drag = {id:event.pointerId,x:event.clientX,y:event.clientY,view:{...view},scale:svg.getScreenCTM().a,token:movable ? node.dataset.tokenId : '',moved:false};
		});
		svg.addEventListener('pointermove', function (event) {
			if (!pointers.has(event.pointerId) || !state.scene) return;
			pointers.set(event.pointerId,{x:event.clientX,y:event.clientY});
			if (pointers.size === 2) {
				const pair = Array.from(pointers.values());
				const distance = Math.hypot(pair[0].x - pair[1].x,pair[0].y - pair[1].y);
				if (pinchDistance) zoom(distance / pinchDistance,point({clientX:(pair[0].x + pair[1].x) / 2,clientY:(pair[0].y + pair[1].y) / 2}));
				pinchDistance = distance;return;
			}
			if (!drag) return;
			if (Math.hypot(event.clientX - drag.x,event.clientY - drag.y) > 4) drag.moved = true;
			if (!drag.moved) return;
			if (drag.token) {
				const p = point(event);
				const token = tokenLayer.querySelector('[data-token-id="' + drag.token + '"]');
				if (token) token.setAttribute('transform','translate(' + p.x + ',' + p.y + ')');
			} else {
				view.x = Math.max(0,Math.min(state.scene.width - view.w,drag.view.x - (event.clientX - drag.x) / drag.scale));
				view.y = Math.max(0,Math.min(state.scene.height - view.h,drag.view.y - (event.clientY - drag.y) / drag.scale));
				applyView();
			}
		});
		function endPointer(event) {
			pointers.delete(event.pointerId);
			pinchDistance = 0;
			const ended = drag;
			drag = null;
			if (!ended || !state.scene) return;
			if (event.type === 'pointercancel') {render(state).catch(function (error) {feedback.textContent = error.message || labels.error;});return;}
			const p = point(event);
			if (p.x < 0 || p.y < 0 || p.x > state.scene.width || p.y > state.scene.height) {render(state).catch(function (error) {feedback.textContent = error.message || labels.error;});return;}
			const position = {x:p.x / state.scene.width * 10000,y:p.y / state.scene.height * 10000};
			if (pingMode && !ended.moved) {pingMode = false;pingButton.setAttribute('aria-pressed','false');action('ping',position);}
			else if (ended.token && ended.moved) action('move',position,ended.token);
			else if (!ended.token && !ended.moved && selected && !preview) action('move',position,selected);
		}
		svg.addEventListener('pointerup',endPointer);
		svg.addEventListener('pointercancel',endPointer);
		fit();
		render(state).catch(reportError);
		if (!preview) {timer = window.setInterval(refresh,5000);refresh();}
	});
}());
