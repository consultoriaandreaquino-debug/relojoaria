/**
 * Catálogo: aplica o filtro assim que o campo muda (o botão "Aplicar" segue funcionando sem JS).
 */
(function () {
    'use strict';
    var form = document.querySelector('[data-filters]');
    if (!form) return;

    form.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { form.requestSubmit ? form.requestSubmit() : form.submit(); });
    });

    // não envia parâmetros vazios: a URL fica limpa e compartilhável
    form.addEventListener('submit', function () {
        form.querySelectorAll('input, select').forEach(function (el) {
            if ((el.type === 'radio' || el.type === 'checkbox') ? false : el.value === '') el.disabled = true;
            if (el.name === 'genero' && el.checked && el.value === '') el.disabled = true;
            if (el.name === 'ordem' && el.value === 'novidades') el.disabled = true;
        });
    });

    var submit = form.querySelector('.filters__submit');
    if (submit) submit.classList.add('visually-hidden-focusable');
})();
