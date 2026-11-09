/**
 * Sacola: envia o formulário "Adicionar à sacola" por fetch (com o token CSRF),
 * atualiza o contador e a mini-sacola sem recarregar. Sem JS, o formulário funciona normalmente.
 */
(function () {
    'use strict';

    var token = document.querySelector('meta[name="csrf-token"]');

    function updateCount(count) {
        document.querySelectorAll('[data-cart-count]').forEach(function (el) {
            el.textContent = count;
            el.hidden = count < 1;
            el.classList.remove('is-bumped');
            void el.offsetWidth; // reinicia a animação
            el.classList.add('is-bumped');
            var btn = el.closest('.bag-btn');
            if (btn) btn.setAttribute('aria-label', 'Sacola, ' + count + ' itens');
        });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-cart-form]');
        if (!form || !window.fetch) return;
        e.preventDefault();

        var button = form.querySelector('[type="submit"]');
        if (button) { button.classList.add('is-loading'); button.disabled = true; }

        // getAttribute: o campo name="action" sombreia a propriedade form.action
        fetch(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'fetch',
                'Accept': 'application/json',
                'X-CSRF-Token': token ? token.content : ''
            },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var mini = document.querySelector('[data-mini-cart]');
                if (mini && data.miniCart) mini.innerHTML = data.miniCart;
                if (typeof data.count === 'number') updateCount(data.count);
                if (window.Morvane && window.Morvane.toast) {
                    window.Morvane.toast(data.message, data.ok ? 'success' : 'error',
                        data.ok ? { href: form.dataset.bagUrl || '#', label: 'Ver sacola' } : null);
                }
                if (data.ok && form.dataset.openMini === '1' && window.bootstrap) {
                    var el = document.getElementById('miniSacola');
                    if (el) bootstrap.Offcanvas.getOrCreateInstance(el).show();
                }
            })
            .catch(function () {
                form.removeAttribute('data-cart-form');
                form.submit(); // se o fetch falhar, envia do jeito tradicional
            })
            .finally(function () {
                if (button) { button.classList.remove('is-loading'); button.disabled = false; }
            });
    });
})();
