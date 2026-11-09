/**
 * Página da sacola: seletor de quantidade e envio automático ao mudar.
 * Sem JS, o botão "Atualizar" envia o formulário normalmente.
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-bag-update]').forEach(function (form) {
        var input = form.querySelector('input[name="quantity"]');
        var dec = form.querySelector('[data-qty-step="-1"]');
        var inc = form.querySelector('[data-qty-step="1"]');
        var submitBtn = form.querySelector('[data-bag-submit]');
        var max = parseInt(input.max, 10) || 1;
        var timer = null;

        if (submitBtn) submitBtn.classList.add('visually-hidden-focusable');

        var sync = function () {
            var v = parseInt(input.value, 10);
            if (isNaN(v) || v < 1) v = 1;
            if (v > max) v = max;
            input.value = v;
            dec.disabled = v <= 1;
            inc.disabled = v >= max;
        };
        var send = function () {
            clearTimeout(timer);
            timer = setTimeout(function () { form.submit(); }, 450);
        };
        dec.addEventListener('click', function () { input.value = parseInt(input.value, 10) - 1; sync(); send(); });
        inc.addEventListener('click', function () { input.value = parseInt(input.value, 10) + 1; sync(); send(); });
        input.addEventListener('change', function () { sync(); send(); });
        sync();
    });
})();
