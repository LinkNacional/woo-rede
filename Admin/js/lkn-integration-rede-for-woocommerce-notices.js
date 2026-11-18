/**
 * Dispensa dos avisos do woo-rede no wp-admin.
 *
 * Qualquer aviso que exponha data-dismissible + data-action + data-nonce é
 * dispensado via AJAX e removido da tela — sem jQuery e sem script inline
 * no PHP. Os avisos do plugin PRO têm script próprio (data-lkn-pro-screen) e
 * são ignorados aqui para não haver dismiss duplicado.
 */
(function () {
	'use strict';

	document.addEventListener('click', function (event) {
		var target = event.target;

		if (!target || typeof target.closest !== 'function') {
			return;
		}

		var dismiss = target.closest('.notice-dismiss');

		if (!dismiss) {
			return;
		}

		var notice = dismiss.closest('[data-dismissible][data-action]');

		if (!notice || notice.hasAttribute('data-lkn-pro-screen')) {
			return;
		}

		var action = notice.getAttribute('data-action');

		if (!action) {
			return;
		}

		event.preventDefault();

		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', notice.getAttribute('data-nonce') || '');

		fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function () {
			notice.remove();
		}).catch(function () {
			notice.remove();
		});
	});
})();
