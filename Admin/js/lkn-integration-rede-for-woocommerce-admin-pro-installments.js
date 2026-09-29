(function ($) {
    'use strict';

    // Dependência de exibição dos campos de parcelamento (juros x desconto + limite),
    // cobrindo campos reais (PRO ativo) e "fake" (showcase do free).
    //
    // Atenção: o layout admin move os campos com `merge-top` para dentro de um
    // container (`.lkn-rede-container-campos`) e oculta o <tr> original. Por isso,
    // para os campos mesclados controlamos a visibilidade do próprio <fieldset> —
    // usar o <tr> acabaria ocultando o bloco-pai inteiro.
    $(window).on('load', function () {
        var match = window.location.search.match(/[?&]section=([^&]+)/);
        var section = match ? decodeURIComponent(match[1]) : '';
        if (!section) {
            return;
        }

        var base = 'woocommerce_' + section + '_';
        var baseEsc = base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var suffixes = ['', '_fake'];

        var isChecked = function (key) {
            var $radios = $('input[name="' + key + '-control"]');
            if ($radios.length) {
                return $radios.filter(':checked').map(function () { return this.value; }).get().indexOf('1') !== -1;
            }
            var el = document.getElementById(key);
            return !!(el && el.checked);
        };

        var toggleField = function (el, show) {
            if (!el) {
                return;
            }
            var $el = $(el);
            // Campo movido por merge-top: alterna o fieldset (o tr original está oculto
            // e o tr mais próximo seria o do campo-pai).
            if ($el.closest('.lkn-rede-container-campos').length) {
                $el.closest('fieldset').toggle(show);
            } else {
                $el.closest('tr').toggle(show);
            }
        };

        var apply = function () {
            suffixes.forEach(function (suffix) {
                var $sel = $('#' + base + 'interest_or_discount' + suffix);
                if (!$sel.length) {
                    return;
                }

                var mode = $sel.val();
                var interestChecked = isChecked(base + 'installment_interest' + suffix);
                var discountChecked = isChecked(base + 'installment_discount' + suffix);
                var $limit = $('#' + base + 'max_parcels_number' + suffix);
                var limit = $limit.length ? (parseInt($limit.val(), 10) || 18) : 18;

                toggleField(document.getElementById(base + 'installment_interest' + suffix), mode === 'interest');
                toggleField(document.getElementById(base + 'installment_discount' + suffix), mode === 'discount');

                var nxRe = new RegExp('^' + baseEsc + '(\\d+)x' + suffix + '$');
                var nxDiscRe = new RegExp('^' + baseEsc + '(\\d+)x_discount' + suffix + '$');

                document.querySelectorAll('input[id^="' + base + '"]').forEach(function (el) {
                    var mDisc = el.id.match(nxDiscRe);
                    var mInt = el.id.match(nxRe);
                    if (mDisc) {
                        toggleField(el, parseInt(mDisc[1], 10) <= limit && mode === 'discount' && discountChecked);
                    } else if (mInt) {
                        toggleField(el, parseInt(mInt[1], 10) <= limit && mode === 'interest' && interestChecked);
                    }
                });
            });
        };

        // O select2 dispara 'change' via jQuery; a delegação cobre o select2 e os rádios.
        $(document).on('change select2:select select2:unselect', function (e) {
            var t = e.target;
            if (!t) {
                return;
            }
            var name = t.name || t.id || '';
            if (name.indexOf(base + 'interest_or_discount') === 0 ||
                name.indexOf(base + 'installment_interest') === 0 ||
                name.indexOf(base + 'installment_discount') === 0 ||
                name.indexOf(base + 'max_parcels_number') === 0) {
                apply();
            }
        });

        // Reaplica após o layout admin terminar de mover os campos (merge-top).
        setTimeout(apply, 250);
        setTimeout(apply, 800);
    });
})(jQuery);
