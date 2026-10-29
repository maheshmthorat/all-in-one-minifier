/* All in one Minifier — admin interactions (no dependencies). */
(function () {
	'use strict';
	var cfg = window.aiomAdmin || {};

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', cfg.nonce);
		Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
		return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); });
	}

	function format(str) {
		var args = [].slice.call(arguments, 1);
		return str.replace(/%(\d)\$d/g, function (m, i) { return args[i - 1]; });
	}

	// Confirmation for destructive or global actions.
	document.addEventListener('click', function (e) {
		var el = e.target.closest('[data-aiom-confirm]');
		if (el && !window.confirm(el.getAttribute('data-aiom-confirm'))) {
			e.preventDefault();
		}
	});

	// Reports.
	var form = document.querySelector('.aiom-report-form');
	if (form) {
		var select = form.querySelector('#aiom-report-select');
		var input = form.querySelector('#aiom-report-url');
		var status = document.querySelector('.aiom-report-status');
		var output = document.querySelector('.aiom-report-output');
		var button = form.querySelector('button');
		var sync = function () { input.hidden = select.value !== ''; };
		select.addEventListener('change', sync);
		sync();
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var url = select.value || input.value.trim();
			if (!url) { input.focus(); return; }
			button.disabled = true;
			status.className = 'aiom-report-status is-busy';
			status.textContent = cfg.i18n.running;
			post('aiom_run_report', { url: url }).then(function (res) {
				if (res && res.success) {
					status.className = 'aiom-report-status';
					status.textContent = '';
					output.insertAdjacentHTML('afterbegin', res.data.html);
				} else {
					status.className = 'aiom-report-status is-error';
					status.textContent = (res && res.data && res.data.message) || cfg.i18n.failed;
				}
			}).catch(function () {
				status.className = 'aiom-report-status is-error';
				status.textContent = cfg.i18n.failed;
			}).then(function () { button.disabled = false; });
		});
	}

	// Cache preloading: one anonymous server-side request per URL, sequentially.
	var preload = document.querySelector('.aiom-preload');
	if (preload) {
		var start = preload.querySelector('.aiom-preload-start');
		var bar = preload.querySelector('.aiom-preload-progress');
		var msg = preload.querySelector('.aiom-preload-status');
		start.addEventListener('click', function () {
			var urls = JSON.parse(preload.getAttribute('data-urls') || '[]');
			var done = 0, errors = 0;
			start.disabled = true;
			bar.hidden = false;
			bar.max = urls.length;
			(function next() {
				if (!urls.length) {
					msg.textContent = format(cfg.i18n.preloadD, done, errors);
					start.disabled = false;
					return;
				}
				msg.textContent = format(cfg.i18n.preload, done + 1, done + urls.length);
				post('aiom_preload', { url: urls.shift() }).then(function (res) {
					if (!res || !res.success) { errors++; }
				}).catch(function () { errors++; }).then(function () {
					done++;
					bar.value = done;
					next();
				});
			})();
		});
	}
})();
