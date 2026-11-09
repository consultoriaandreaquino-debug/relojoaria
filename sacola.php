<?php
require __DIR__ . '/config/app.php';

$cart = Cart::details(); // recalcula com preço e estoque atuais do banco

$title = 'Sacola';
$bodyClass = 'page-light';
$headerVariant = 'light';
$scripts = ['bag.js'];
require __DIR__ . '/views/partials/head.php';
require __DIR__ . '/views/partials/header.php';
?>
<main id="conteudo" class="bag">
    <h1 class="page-title">Sacola</h1>

    <?php foreach ($cart['notices'] as $notice): ?>
    <p class="notice" role="status"><?= e($notice) ?></p>
    <?php endforeach; ?>

    <?php if (!$cart['lines']): ?>
    <div class="empty-state">
        <h2>Sua sacola está vazia</h2>
        <p>As peças que você escolher ficam guardadas aqui enquanto navega.</p>
        <a class="btn-line" href="<?= e(url('catalogo.php')) ?>">Ver o catálogo</a>
    </div>
    <?php else: ?>
    <div class="bag__layout">
        <div>
            <?php foreach ($cart['lines'] as $line): $p = $line['product']; $pid = (int) $p['id']; ?>
            <article class="bag-line">
                <a class="bag-line__img" href="<?= e(url('produto.php', ['slug' => $p['slug']])) ?>">
                    <img src="<?= e(image_url(ProductRepository::thumb($p['image']))) ?>" alt="<?= e($p['name']) ?>" width="120" height="150" loading="lazy">
                </a>
                <div>
                    <p class="eyebrow mb-1"><?= e($p['category_name']) ?></p>
                    <h2 class="bag-line__name"><a href="<?= e(url('produto.php', ['slug' => $p['slug']])) ?>"><?= e($p['name']) ?></a></h2>
                    <p class="bag-line__meta"><?= e($p['case_material']) ?> · <?= e(mm($p['diameter_mm'])) ?> mm · <?= e(stock_status((int) $p['stock'])['label']) ?></p>
                    <div class="bag-line__controls">
                        <form method="post" action="<?= e(url('actions/cart.php')) ?>" class="d-flex align-items-center gap-2" data-bag-update>
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                            <div class="qty qty--small" data-qty>
                                <button type="button" data-qty-step="-1" aria-label="Diminuir quantidade de <?= e($p['name']) ?>">−</button>
                                <label class="visually-hidden" for="qtd<?= $pid ?>">Quantidade de <?= e($p['name']) ?></label>
                                <input id="qtd<?= $pid ?>" type="number" name="quantity" value="<?= (int) $line['quantity'] ?>" min="1" max="<?= (int) $line['max'] ?>" inputmode="numeric">
                                <button type="button" data-qty-step="1" aria-label="Aumentar quantidade de <?= e($p['name']) ?>">+</button>
                            </div>
                            <button type="submit" class="btn-line btn-small" data-bag-submit>Atualizar</button>
                        </form>
                        <form method="post" action="<?= e(url('actions/cart.php')) ?>">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                            <button type="submit" class="bag-line__remove">Remover</button>
                        </form>
                    </div>
                </div>
                <p class="bag-line__price">
                    <small><?= (int) $line['quantity'] ?> × <?= e(money($line['unit_price'])) ?></small>
                    <strong><?= e(money($line['subtotal'])) ?></strong>
                </p>
            </article>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('actions/cart.php')) ?>" class="mt-3">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="bag-line__remove">Esvaziar sacola</button>
            </form>
        </div>

        <aside class="summary" aria-labelledby="resumoTitulo">
            <h2 class="summary__title" id="resumoTitulo">Resumo</h2>
            <div class="summary__row"><span>Subtotal (<?= (int) $cart['count'] ?> <?= $cart['count'] === 1 ? 'item' : 'itens' ?>)</span><span><?= e(money($cart['total'])) ?></span></div>
            <div class="summary__row"><span>Entrega segurada</span><span>Grátis</span></div>
            <div class="summary__row summary__row--total"><span>Total</span><strong><?= e(money($cart['total'])) ?></strong></div>
            <a class="btn-solid" href="<?= e(url('checkout.php')) ?>">Finalizar compra</a>
            <a class="btn-line" href="<?= e(url('catalogo.php')) ?>">Continuar comprando</a>
            <p class="summary__note">Preços confirmados no fechamento do pedido. Pagamento simulado: nenhuma cobrança é feita.</p>
        </aside>
    </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
