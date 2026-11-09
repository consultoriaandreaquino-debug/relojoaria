/**
 * Validação no cliente (conveniência: o servidor valida tudo de novo).
 * Mostra a mensagem do campo ao sair dele e bloqueia o envio com erros.
 */
(function () {
    'use strict';

    var messages = {
        valueMissing: 'Preencha este campo.',
        typeMismatch: 'Formato inválido.',
        tooShort: 'Texto curto demais.',
        patternMismatch: 'Formato inválido.'
    };

    function messageFor(input) {
        var v = input.validity;
        if (input.dataset.match) {
            var other = input.form.querySelector('[name="' + input.dataset.match + '"]');
            if (other && other.value !== input.value) return 'As senhas não conferem.';
        }
        if (v.valid) return '';
        if (input.name === 'email' && v.typeMismatch) return 'Informe um e-mail válido.';
        if (input.name === 'password' && (v.tooShort || v.patternMismatch)) return 'Use 8 caracteres ou mais, com letras e números.';
        if (input.dataset.msg) return input.dataset.msg;
        for (var key in messages) { if (v[key]) return messages[key]; }
        return 'Confira este campo.';
    }

    function show(input) {
        var field = input.closest('.field');
        if (!field) return true;
        var msg = messageFor(input);
        var el = field.querySelector('.field__error[data-client]');
        field.querySelectorAll('.field__error:not([data-client])').forEach(function (n) { n.remove(); });
        if (msg) {
            if (!el) {
                el = document.createElement('p');
                el.className = 'field__error';
                el.setAttribute('data-client', '');
                el.id = input.id + 'Erro';
                field.appendChild(el);
            }
            el.textContent = msg;
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('aria-describedby', el.id);
            field.classList.add('has-error');
            return false;
        }
        if (el) el.remove();
        input.removeAttribute('aria-invalid');
        field.classList.remove('has-error');
        return true;
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.querySelectorAll('input, select, textarea').forEach(function (input) {
            if (input.type === 'hidden') return;
            input.addEventListener('blur', function () { if (input.value !== '') show(input); });
            input.addEventListener('input', function () { if (input.getAttribute('aria-invalid')) show(input); });
        });
        form.addEventListener('submit', function (e) {
            var firstBad = null;
            form.querySelectorAll('input, select, textarea').forEach(function (input) {
                if (input.type === 'hidden' || input.disabled || input.closest('[hidden]')) return;
                if (!show(input) && !firstBad) firstBad = input;
            });
            if (firstBad) {
                e.preventDefault();
                firstBad.focus();
                return;
            }
            var btn = form.querySelector('[type="submit"]');
            if (btn && btn.classList.contains('btn-solid')) btn.classList.add('is-loading');
        });
    });
})();
