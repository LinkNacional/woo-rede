(function () {
    'use strict';

    var cfg = window.LknRedeProUpdate || {};

    // Insere o card junto das demais notices do admin (antes do .wp-header-end),
    // fora de qualquer card/wrap de conteúdo — padrão do woo-better.
    function mountCard(card) {
        var host = document.querySelector('.wp-header-end') || document.querySelector('#wpbody-content');
        if (!host) {
            return;
        }
        host.insertAdjacentElement('beforebegin', card);
    }

    function buildCard(kind, message) {
        var data = cfg[kind] || {};

        var card = document.createElement('div');
        card.className = 'notice notice-' + (kind === 'error' ? 'error' : 'info') +
            ' inline is-dismissible lkn-pro-notice lkn-pro-notice--' + kind + ' lkn-pro-' + kind + '-card';

        var icon = document.createElement('div');
        icon.className = 'lkn-pro-notice__icon';
        var img = document.createElement('img');
        img.src = cfg.iconUrl || '';
        img.alt = data.title || '';
        icon.appendChild(img);
        card.appendChild(icon);

        var content = document.createElement('div');
        content.className = 'lkn-pro-notice__content';

        var title = document.createElement('p');
        title.className = 'lkn-pro-notice__title';
        var strong = document.createElement('strong');
        strong.textContent = data.title || '';
        var badge = document.createElement('span');
        badge.className = 'lkn-pro-notice__badge';
        badge.textContent = data.badge || '';
        title.appendChild(strong);
        title.appendChild(badge);

        var body = document.createElement('p');
        body.textContent = message || '';

        content.appendChild(title);
        content.appendChild(body);
        card.appendChild(content);

        return card;
    }

    function showCard(kind, message) {
        var existing = document.querySelector('.lkn-pro-success-card, .lkn-pro-error-card');
        if (existing) {
            existing.remove();
        }
        mountCard(buildCard(kind, message));
    }

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
            if (data && data.success) {
                if (bar) {
                    bar.style.transition = 'width 0.4s ease';
                    bar.style.width = '100%';
                }
                btn.classList.remove('is-loading');
                btn.classList.add('is-success');
                if (text) {
                    text.textContent = cfg.successText || 'Atualizado!';
                }

                // O card de sucesso é exibido após o redirect (via transient).
                setTimeout(function () {
                    window.location.href = cfg.redirectUrl || '/wp-admin/plugins.php';
                }, 1200);
                return;
            }

            // Erro no servidor: o transient de erro foi gravado; recarrega para o card.
            btn.classList.remove('is-loading');
            btn.classList.add('is-error');
            if (text) {
                text.textContent = (data && data.data && data.data.message) ? data.data.message : 'Erro ao atualizar.';
            }
            window.location.reload();
        }).catch(function () {
            // Falha de rede: nenhum transient foi gravado — exibe o erro inline.
            btn.classList.remove('is-loading');
            btn.classList.add('is-error');
            btn.removeAttribute('data-updating');
            if (text) {
                text.textContent = 'Erro ao atualizar.';
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

        // Isola por plugin: nesta página podem coexistir os avisos de mais de um
        // gateway LKN, todos com o mesmo seletor de botão. Sem isto, clicar no
        // botão de um dispara o handler do outro (o primeiro registrado vence).
        var owner = btn.closest('[data-lkn-pro-screen]');
        if (owner && owner.getAttribute('data-lkn-pro-screen') !== cfg.screen) {
            return;
        }

        event.preventDefault();
        start(btn);
    });

    // Dispensa o aviso de atualização (persiste via AJAX), sem depender de jQuery.
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

        // Isola por plugin (ver comentário no handler do botão de update).
        if (notice.getAttribute('data-lkn-pro-screen') !== cfg.screen) {
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

    // Exibe o card de sucesso/erro ao carregar a página (após o reload/redirect).
    if (cfg.showOnLoad === 'error') {
        showCard('error', cfg.errorMessage || '');
    } else if (cfg.showOnLoad === 'success') {
        showCard('success', (cfg.success && cfg.success.message) || '');
    }
})();
