(function () {
    'use strict';

    var cfg = window.LknProUpdate || {};

    function start(btn) {
        if (btn.getAttribute('data-updating') === '1') {
            return;
        }
        btn.setAttribute('data-updating', '1');
        btn.classList.remove('is-error');
        btn.classList.add('is-loading');

        // Trava a largura para o texto não alterar o tamanho do botão.
        if (!btn.style.minWidth) {
            btn.style.minWidth = btn.offsetWidth + 'px';
        }

        var bar = btn.querySelector('.lkn-pro-update-button__bar');
        var text = btn.querySelector('.lkn-pro-update-button__text');

        if (bar) {
            bar.style.transition = 'width 6s linear';
            bar.style.width = '90%';
        }

        var body = new URLSearchParams();
        body.append('action', cfg.action || '');
        body.append('nonce', cfg.nonce || '');
        body.append('plugin', cfg.plugin || '');

        fetch(cfg.ajaxurl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (!data || !data.success) {
                throw new Error(data && data.data && data.data.message ? data.data.message : 'Erro ao atualizar.');
            }

            if (bar) {
                bar.style.transition = 'width 0.4s ease';
                bar.style.width = '100%';
            }
            btn.classList.remove('is-loading');
            btn.classList.add('is-success');
            if (text) {
                text.textContent = cfg.successText || 'Atualizado!';
            }

            setTimeout(function () {
                window.location.href = cfg.redirectUrl || '/wp-admin/plugins.php';
            }, 1200);
        }).catch(function (error) {
            btn.classList.remove('is-loading');
            btn.classList.add('is-error');
            btn.removeAttribute('data-updating');
            if (text) {
                text.textContent = (error && error.message) ? error.message : 'Erro ao atualizar.';
            }
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var btn = target.closest('.lkn-pro-update-button');
        if (!btn) {
            return;
        }

        event.preventDefault();
        start(btn);
    });

    // Dispensa o aviso (persiste via AJAX), sem depender de jQuery.
    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var dismiss = target.closest('.notice-dismiss');
        if (!dismiss) {
            return;
        }

        var notice = dismiss.closest('[data-dismissible]');
        if (!notice) {
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

        fetch(cfg.ajaxurl || '/wp-admin/admin-ajax.php', {
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
