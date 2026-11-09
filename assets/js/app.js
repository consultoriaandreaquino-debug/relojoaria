/**
 * Comportamentos gerais: cabeçalho, painel de coleções, pesquisa em tela cheia e avisos.
 */
(function () {
    'use strict';

    /* ---------- Cabeçalho transparente que fica sólido ao rolar ---------- */
    var header = document.querySelector('[data-header]');
    if (header && header.dataset.variant === 'overlay') {
        var onScroll = function () {
            header.classList.toggle('is-solid', window.scrollY > 40);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* ---------- Painel de coleções (clique, teclado e Esc) ---------- */
    var toggle = document.querySelector('[data-collections-toggle]');
    var panel = document.querySelector('[data-collections-panel]');
    if (toggle && panel) {
        var close = function () {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        };
        toggle.addEventListener('click', function () {
            var open = panel.hidden;
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            if (open) {
                var first = panel.querySelector('a');
                if (first) first.focus();
            }
        });
        document.addEventListener('click', function (e) {
            if (!panel.hidden && !panel.contains(e.target) && e.target !== toggle) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.hidden) { close(); toggle.focus(); }
        });
    }

    /* ---------- Pesquisa em tela cheia ---------- */
    var search = document.querySelector('[data-search]');
    var lastFocus = null;
    function openSearch() {
        lastFocus = document.activeElement;
        search.hidden = false;
        document.body.style.overflow = 'hidden';
        var input = search.querySelector('input[type="search"]');
        input.focus();
    }
    function closeSearch() {
        search.hidden = true;
        document.body.style.overflow = '';
        if (lastFocus) lastFocus.focus();
    }
    if (search) {
        document.querySelectorAll('[data-search-open]').forEach(function (btn) {
            btn.addEventListener('click', openSearch);
        });
        search.querySelector('[data-search-close]').addEventListener('click', closeSearch);
        search.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSearch();
            if (e.key === 'Tab') { // mantém o foco dentro do diálogo
                var items = search.querySelectorAll('button, input, a[href]');
                var first = items[0], last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        });
    }

    /* ---------- Avisos ---------- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-flash-close]');
        if (btn) btn.parentElement.remove();
    });
    document.querySelectorAll('.flash').forEach(function (el) {
        setTimeout(function () { if (el.isConnected) el.remove(); }, 7000);
    });

    window.Morvane = window.Morvane || {};
    /** Mostra um aviso curto no canto da tela. */
    window.Morvane.toast = function (message, type, link) {
        var region = document.querySelector('[data-toasts]');
        if (!region) return;
        var el = document.createElement('div');
        el.className = 'toast-msg toast-msg--' + (type || 'info');
        var text = document.createElement('span');
        text.textContent = message;
        el.appendChild(text);
        if (link) {
            var a = document.createElement('a');
            a.href = link.href;
            a.textContent = link.label;
            el.appendChild(a);
        }
        region.appendChild(el);
        setTimeout(function () { el.remove(); }, 5000);
    };
})();
