<?php
require dirname(__DIR__) . '/config/app.php';

Auth::requireLogin();
$user = Auth::user();
$orders = OrderService::forUser((int) $user['id']);

$title = 'Meus pedidos';
$bodyClass = 'page-light';
$headerVariant = 'light';
require BASE_PATH . '/views/partials/head.php';
require BASE_PATH . '/views/partials/header.php';
?>
<main id="conteudo" class="account">
    <p class="eyebrow">Minha conta</p>
    <h1 class="page-title">Olá, <?= e(strtok($user['name'], ' ')) ?></h1>
    <p class="lead-text mb-5"><?= e($user['email']) ?> · cliente desde <?= e(format_date($user['created_at'])) ?></p>

    <h2 class="form-section__title">Meus pedidos <span><?= count($orders) ?></span></h2>
    <?php if (!$orders): ?>
    <div class="empty-state">
        <h2>Você ainda não fez pedidos</h2>
        <p>Quando fizer, eles aparecem aqui com o status de entrega.</p>
        <a class="btn-line" href="<?= e(url('catalogo.php')) ?>">Ver o catálogo</a>
    </div>
    <?php else: ?>
    <table class="orders-table">
        <thead>
            <tr><th scope="col">Pedido</th><th scope="col">Data</th><th scope="col">Peças</th><th scope="col">Pagamento</th><th scope="col">Status</th><th scope="col">Total</th></tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td><a href="<?= e(url('pedido.php', ['id' => $o['id']])) ?>"><?= e(order_code((int) $o['id'])) ?></a></td>
                <td><?= e(format_date($o['created_at'])) ?></td>
                <td><?= (int) $o['units'] ?></td>
                <td><?= e(payment_label($o['payment_method'])) ?></td>
                <td><span class="status status--<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
                <td><?= e(money($o['total'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</main>
<?php require BASE_PATH . '/views/partials/footer.php'; ?>
