<?php
/** Conteúdo da mini-sacola. Recebe $cart = Cart::details(). */
?>
<?php if (!$cart['lines']): ?>
    <div class="mini-bag__empty">
        <p>Sua sacola está vazia.</p>
        <a class="btn-line" href="<?= e(url('catalogo.php')) ?>">Ver o catálogo</a>
    </div>
<?php else: ?>
    <ul class="mini-bag__list">
        <?php foreach ($cart['lines'] as $line): $p = $line['product']; ?>
        <li class="mini-bag__item">
            <a href="<?= e(url('produto.php', ['slug' => $p['slug']])) ?>" class="mini-bag__img">
                <img src="<?= e(image_url(ProductRepository::thumb($p['image']))) ?>" alt="" width="72" height="90" loading="lazy">
            </a>
            <div class="mini-bag__info">
                <a class="mini-bag__name" href="<?= e(url('produto.php', ['slug' => $p['slug']])) ?>"><?= e($p['name']) ?></a>
                <span class="mini-bag__meta"><?= (int) $line['quantity'] ?> × <?= e(money($line['unit_price'])) ?></span>
            </div>
            <span class="mini-bag__subtotal"><?= e(money($line['subtotal'])) ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <div class="mini-bag__footer">
        <div class="mini-bag__total"><span>Total</span><strong><?= e(money($cart['total'])) ?></strong></div>
        <p class="mini-bag__note">Entrega segurada e gratuita para todo o Brasil.</p>
        <a class="btn-solid w-100" href="<?= e(url('checkout.php')) ?>">Finalizar compra</a>
        <a class="btn-line w-100" href="<?= e(url('sacola.php')) ?>">Ver sacola</a>
    </div>
<?php endif; ?>
