<?php
require __DIR__ . '/config/app.php';

Auth::requireLogin();
$id = int_param($_GET['id'] ?? null);
$order = $id ? OrderService::find($id) : null;

// O cliente só vê os próprios pedidos (admin vê pelo painel)
if (!$order || (int) $order['user_id'] !== Auth::id()) {
    not_found('Não encontramos este pedido na sua conta.');
}
$items = OrderService::items((int) $order['id']);
$justPlaced = ($_SESSION['last_order'] ?? null) === (int) $order['id'];
unset($_SESSION['last_order']);

$title = 'Pedido ' . order_code((int) $order['id']);
$bodyClass = 'page-light';
$headerVariant = 'light';
require __DIR__ . '/views/partials/head.php';
require __DIR__ . '/views/partials/header.php';
?>
<main id="conteudo" class="order-page">
    <header class="order-hero">
        <svg class="order-hero__mark" viewBox="0 0 84 84" aria-hidden="true">
            <circle cx="42" cy="42" r="40"/>
            <path d="M27 43l10 10 20-22" pathLength="1"/>
        </svg>
        <div>
            <p class="eyebrow"><?= e(order_code((int) $order['id'])) ?></p>
            <h1><?= $justPlaced ? 'Pedido confirmado' : 'Pedido ' . e(order_code((int) $order['id'])) ?></h1>
            <p class="lead-text mb-0">
                <?php if ($justPlaced): ?>
                Obrigado, <?= e(strtok($order['customer_name'], ' ')) ?>. O pagamento foi aprovado e a oficina já separa sua peça. Você acompanha o envio em Meus pedidos.
                <?php else: ?>
                Feito em <?= e(format_date($order['created_at'], true)) ?>.
                <?php endif; ?>
            </p>
        </div>
    </header>

    <div class="order-grid">
        <section aria-labelledby="itensTitulo">
            <h2 class="form-section__title" id="itensTitulo">Peças</h2>
            <?php foreach ($items as $item): ?>
            <article class="bag-line">
                <a class="bag-line__img" href="<?= e(url('produto.php', ['slug' => $item['slug']])) ?>">
                    <img src="<?= e(image_url(ProductRepository::thumb($item['image']))) ?>" alt="<?= e($item['product_name']) ?>" width="120" height="150" loading="lazy">
                </a>
                <div>
                    <h3 class="bag-line__name"><?= e($item['product_name']) ?></h3>
                    <p class="bag-line__meta"><?= (int) $item['quantity'] ?> × <?= e(money($item['unit_price'])) ?> (preço na data da compra)</p>
                </div>
                <p class="bag-line__price"><strong><?= e(money($item['subtotal'])) ?></strong></p>
            </article>
            <?php endforeach; ?>
            <div class="summary__row summary__row--total"><span>Total pago</span><strong><?= e(money($order['total'])) ?></strong></div>
        </section>

        <aside class="summary">
            <dl class="order-facts">
                <div><dt>Status</dt><dd><span class="status status--<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span></dd></div>
                <div><dt>Data</dt><dd><?= e(format_date($order['created_at'], true)) ?></dd></div>
                <div><dt>Pagamento</dt><dd><?= e(payment_label($order['payment_method'])) ?><?= $order['card_last4'] ? ' final ' . e($order['card_last4']) : '' ?> <small class="d-block text-secondary">simulado</small></dd></div>
                <div><dt>Entrega</dt><dd><?= e($order['shipping_name']) ?><br><?= e($order['shipping_address']) ?><br><?= e($order['shipping_city']) ?> / <?= e($order['shipping_state']) ?> · <?= e($order['shipping_zip']) ?></dd></div>
            </dl>
            <a class="btn-line" href="<?= e(url('conta/pedidos.php')) ?>">Meus pedidos</a>
            <a class="btn-solid" href="<?= e(url('catalogo.php')) ?>">Continuar comprando</a>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
