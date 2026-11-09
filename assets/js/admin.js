/**
 * Painel: confirmação de ações destrutivas, slug automático e avisos.
 */
(function () {
    'use strict';

    // Formulários com data-confirm pedem confirmação antes de enviar
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });

    // Slug sugerido a partir do nome, enquanto o campo slug não foi editado à mão
    var source = document.querySelector('[data-slug-source]');
    var target = document.querySelector('[data-slug-target]');
    if (source && target) {
        var touched = target.value !== '';
        target.addEventListener('input', function () { touched = true; });
        source.addEventListener('input', function () {
            if (touched) return;
            target.value = source.value.toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-flash-close]');
        if (btn) btn.parentElement.remove();
    });

    // Destaca o campo de estoque alterado ainda não salvo
    document.querySelectorAll('.stock-form__input').forEach(function (input) {
        var initial = input.value;
        input.addEventListener('input', function () {
            input.classList.toggle('is-dirty', input.value !== initial);
        });
    });
})();
