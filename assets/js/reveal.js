/**
 * Revelação do relógio no hero.
 * - Ao carregar, a luz abre parcialmente sobre a peça.
 * - O controle "Arraste para revelar" (input range) funciona com mouse, toque e teclado.
 * - Com prefers-reduced-motion, a peça aparece direto e o controle não é exibido.
 */
(function () {
    'use strict';

    var hero = document.querySelector('[data-reveal]');
    if (!hero) return;

    var lit = hero.querySelector('.hero__img--lit');
    var control = hero.querySelector('[data-reveal-control]');
    var range = hero.querySelector('[data-reveal-range]');
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduce || !lit || !range || !('CSS' in window) || !CSS.supports('(mask-image: none) or (-webkit-mask-image: none)')) {
        document.documentElement.classList.remove('js-reveal');
        hero.classList.add('is-lit', 'is-revealed');
        return;
    }

    control.hidden = false;

    var value = 0;
    var anim = null;

    function set(v) {
        value = Math.max(0, Math.min(100, v));
        lit.style.setProperty('--r', value.toFixed(2));
        range.value = Math.round(value);
        range.style.setProperty('--fill', value + '%');
        range.setAttribute('aria-valuetext', value >= 100 ? 'Peça revelada' : 'Revelado ' + Math.round(value) + '%');
        hero.classList.toggle('is-lit', value > 45);
        if (value >= 100) hero.classList.add('is-revealed');
    }

    function animateTo(target, duration, done) {
        cancelAnimationFrame(anim);
        var from = value;
        var start = performance.now();
        function step(now) {
            var t = Math.min(1, (now - start) / duration);
            var eased = 1 - Math.pow(1 - t, 3);
            set(from + (target - from) * eased);
            if (t < 1) anim = requestAnimationFrame(step);
            else if (done) done();
        }
        anim = requestAnimationFrame(step);
    }

    set(0);
    // a luz entra sozinha e para numa fresta
    var intro = setTimeout(function () { animateTo(52, 1800); }, 350);

    range.addEventListener('input', function () {
        clearTimeout(intro);
        cancelAnimationFrame(anim);
        set(parseFloat(range.value));
    });

    // soltar depois de 70% termina a revelação
    function release() {
        if (value >= 70 && value < 100) animateTo(100, 700);
    }
    range.addEventListener('change', release);
    range.addEventListener('pointerup', release);
    range.addEventListener('keyup', function (e) {
        if (e.key === 'End' || value >= 70) release();
    });
})();
