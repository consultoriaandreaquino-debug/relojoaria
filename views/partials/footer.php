<?php
/** Rodapé institucional + scripts. Variável opcional: $scripts (lista de arquivos em assets/js). */
?>
<footer class="site-footer">
    <div class="site-footer__top">
        <div class="site-footer__brand">
            <?php partial('logo', ['logoClass' => 'logo--footer']); ?>
            <p>Relojoaria desde 1968. Oficina e boutique na Rua Oscar Freire, São Paulo.</p>
        </div>
        <nav class="site-footer__col" aria-label="Coleções">
            <h2>Coleções</h2>
            <ul>
                <?php foreach (CategoryRepository::all() as $c): ?>
                <li><a href="<?= e(url('catalogo.php', ['colecao' => $c['slug']])) ?>"><?= e($c['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <nav class="site-footer__col" aria-label="Atendimento">
            <h2>Atendimento</h2>
            <ul>
                <li><a href="<?= e(url('atendimento.php')) ?>#contato">Fale com a boutique</a></li>
                <li><a href="<?= e(url('atendimento.php')) ?>#entrega">Entrega e prazos</a></li>
                <li><a href="<?= e(url('atendimento.php')) ?>#garantia">Garantia e revisão</a></li>
                <li><a href="<?= e(url('conta/pedidos.php')) ?>">Meus pedidos</a></li>
            </ul>
        </nav>
        <nav class="site-footer__col" aria-label="Institucional">
            <h2>A casa</h2>
            <ul>
                <li><a href="<?= e(url('')) ?>#historia">Nossa história</a></li>
                <li><a href="<?= e(url('atendimento.php')) ?>#trocas">Trocas e devoluções</a></li>
                <li><a href="<?= e(url('atendimento.php')) ?>#privacidade">Privacidade</a></li>
                <li><a href="<?= e(url('atendimento.php')) ?>#termos">Termos de uso</a></li>
            </ul>
        </nav>
        <div class="site-footer__col">
            <h2>Acompanhe</h2>
            <ul class="site-footer__social">
                <li><a href="https://www.instagram.com/" rel="noopener" target="_blank">Instagram</a></li>
                <li><a href="https://www.youtube.com/" rel="noopener" target="_blank">YouTube</a></li>
                <li><a href="https://www.pinterest.com/" rel="noopener" target="_blank">Pinterest</a></li>
            </ul>
            <p class="site-footer__contact">(11) 3000-1968<br>atendimento@morvane.com.br</p>
        </div>
    </div>
    <div class="site-footer__bottom">
        <p>© <?= date('Y') ?> Morvane Relojoaria. Projeto acadêmico: marca fictícia, pagamentos simulados.</p>
        <p>Imagens de produto renderizadas pela própria casa.</p>
    </div>
</footer>

<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/cart.js')) ?>" defer></script>
<?php foreach (($scripts ?? []) as $script): ?>
<script src="<?= e(asset('assets/js/' . $script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
