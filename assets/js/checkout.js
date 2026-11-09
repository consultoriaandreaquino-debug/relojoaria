/**
 * Checkout: mostra os campos da forma de pagamento escolhida e aplica máscaras simples.
 */
(function () {
    'use strict';
    var form = document.querySelector('[data-checkout]');
    if (!form) return;

    var options = form.querySelectorAll('[data-pay-option]');
    var panels = form.querySelectorAll('[data-pay-panel]');

    function update() {
        var chosen = form.querySelector('[data-pay-option]:checked');
        var value = chosen ? chosen.value : '';
        panels.forEach(function (p) {
            var on = p.dataset.payPanel === value;
            p.hidden = !on;
            p.querySelectorAll('[data-pay-required]').forEach(function (input) {
                input.required = on; // só valida os campos do cartão quando o cartão está escolhido
            });
        });
    }
    options.forEach(function (o) { o.addEventListener('change', update); });
    update();

    var masks = {
        cep: function (v) { v = v.replace(/\D/g, '').slice(0, 8); return v.length > 5 ? v.slice(0, 5) + '-' + v.slice(5) : v; },
        card: function (v) { return v.replace(/\D/g, '').slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 '); },
        expiry: function (v) { v = v.replace(/\D/g, '').slice(0, 4); return v.length > 2 ? v.slice(0, 2) + '/' + v.slice(2) : v; }
    };
    form.querySelectorAll('[data-mask]').forEach(function (input) {
        var fn = masks[input.dataset.mask];
        if (!fn) return;
        input.addEventListener('input', function () { input.value = fn(input.value); });
    });
})();
