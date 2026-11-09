/**
 * Página do produto: galeria com miniaturas, zoom ao passar o mouse,
 * foto em tela cheia (pinça no celular) e seletor de quantidade limitado ao estoque.
 */
(function () {
    'use strict';

    var gallery = document.querySelector('[data-gallery]');
    if (gallery) {
        var slides = gallery.querySelectorAll('[data-slide]');
        var thumbs = gallery.querySelectorAll('[data-thumb]');
        var current = 0;

        var show = function (i) {
            current = (i + slides.length) % slides.length;
            slides.forEach(function (s, n) { s.hidden = n !== current; s.classList.toggle('is-active', n === current); });
            thumbs.forEach(function (t, n) {
                t.classList.toggle('is-active', n === current);
                t.setAttribute('aria-selected', String(n === current));
            });
        };
        thumbs.forEach(function (t) {
            t.addEventListener('click', function () { show(parseInt(t.dataset.thumb, 10)); });
            t.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                    e.preventDefault();
                    show(current + (e.key === 'ArrowRight' ? 1 : -1));
                    thumbs[current].focus();
                }
            });
        });

        // Zoom: a imagem acompanha o ponteiro (só em dispositivos com mouse)
        var fine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        gallery.querySelectorAll('[data-zoom]').forEach(function (box) {
            var img = box.querySelector('img');
            if (!fine) return;
            box.addEventListener('pointermove', function (e) {
                var r = box.getBoundingClientRect();
                img.style.transformOrigin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
                box.classList.add('is-zoomed');
            });
            box.addEventListener('pointerleave', function () { box.classList.remove('is-zoomed'); });
            box.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openLightbox(); }
            });
        });

        // Tela cheia
        var lightbox = document.querySelector('[data-lightbox]');
        var lbImg = lightbox && lightbox.querySelector('[data-lightbox-img]');
        var opener = null;
        var openLightbox = function () {
            if (!lightbox) return;
            opener = document.activeElement;
            var img = slides[current].querySelector('img');
            lbImg.src = img.currentSrc || img.src;
            lbImg.alt = slides[current].querySelector('[data-zoom]').getAttribute('aria-label') || '';
            lightbox.hidden = false;
            document.body.style.overflow = 'hidden';
            lightbox.querySelector('[data-lightbox-close]').focus();
        };
        var closeLightbox = function () {
            lightbox.hidden = true;
            document.body.style.overflow = '';
            if (opener) opener.focus();
        };
        var openBtn = gallery.querySelector('[data-gallery-open]');
        if (openBtn) openBtn.addEventListener('click', openLightbox);
        if (!fine) {
            gallery.querySelectorAll('[data-zoom]').forEach(function (box) { box.addEventListener('click', openLightbox); });
        }
        if (lightbox) {
            lightbox.querySelector('[data-lightbox-close]').addEventListener('click', closeLightbox);
            lightbox.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeLightbox(); });
        }
    }

    // Quantidade: botões − e + respeitam min/max (o servidor valida de novo)
    document.querySelectorAll('[data-qty]').forEach(function (box) {
        var input = box.querySelector('input');
        var dec = box.querySelector('[data-qty-step="-1"]');
        var inc = box.querySelector('[data-qty-step="1"]');
        var min = parseInt(input.min, 10) || 0;
        var max = parseInt(input.max, 10) || 1;
        var sync = function () {
            var v = parseInt(input.value, 10);
            if (isNaN(v)) v = min;
            v = Math.max(min, Math.min(max, v));
            input.value = v;
            dec.disabled = v <= min;
            inc.disabled = v >= max;
        };
        dec.addEventListener('click', function () { input.value = parseInt(input.value, 10) - 1; sync(); input.dispatchEvent(new Event('change', { bubbles: true })); });
        inc.addEventListener('click', function () { input.value = parseInt(input.value, 10) + 1; sync(); input.dispatchEvent(new Event('change', { bubbles: true })); });
        input.addEventListener('blur', sync);
        sync();
    });
})();
