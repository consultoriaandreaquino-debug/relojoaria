<?php
require __DIR__ . '/_guard.php';

if (is_post()) {
    $id = int_param($_POST['id'] ?? null);
    $status = input($_POST, 'status', 20);
    if ($id && in_array($status, OrderService::STATUSES, true)) {
        db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
        flash('success', 'Pedido ' . order_code($id) . ' agora está como "' . status_label($status) . '".');
    } else {
        flash('error', 'Status inválido.');
    }
    redirect('admin/pedidos.php', ['id' => $id]);
}

$id = int_param($_GET['id'] ?? null);
$order = $id ? OrderService::find($id) : null;

if ($order) {
    $items = OrderService::items((int) $order['id']);
} else {
    $status = in_array($_GET['status'] ?? '', OrderService::STATUSES, true) ? $_GET['status'] : '';
    $sql = "SELECT o.*, u.name AS customer, u.email,
                   (SELECT SUM(quantity) FROM order_items i WHERE i.order_id = o.id) AS units,
                   (SELECT GROUP_CONCAT(CONCAT(i.quantity, '× ', i.product_name) SEPARATOR ', ') FROM order_items i WHERE i.order_id = o.id) AS summary
            FROM orders o JOIN users u ON u.id = o.user_id";
    if ($status !== '') {
        $stmt = db()->prepare($sql . ' WHERE o.status = ? ORDER BY o.created_at DESC, o.id DESC');
        $stmt->execute([$status]);
    } else {
        $stmt = db()->query($sql . ' ORDER BY o.created_at DESC, o.id DESC');
    }
    $orders = $stmt->fetchAll();
}

$title = $order ? 'Pedido ' . order_code((int) $order['id']) : 'Pedidos';
$active = 'pedidos';
require BASE_PATH . '/views/admin/top.php';
?>
<?php if ($order): ?>
<header class="admin__head">
    <div><p class="eyebrow"><a href="<?= e(url('admin/pedidos.php')) ?>">Pedidos</a></p><h1><?= e(order_code((int) $order['id'])) ?></h1></div>
    <form method="post" class="status-form">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
        <label class="visually-hidden" for="statusPedido">Status do pedido</label>
        <select class="field__select" id="statusPedido" name="status">
            <?php foreach (OrderService::STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn-solid btn-small">Atualizar status</button>
    </form>
</header>
<div class="admin-grid">
    <section class="panel panel--flush">
        <table class="table-admin">
            <thead><tr><th>Peça</th><th class="num-col">Qtd.</th><th class="num-col">Preço unitário (congelado)</th><th class="num-col">Subtotal</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><div class="table-product"><img src="<?= e(image_url(ProductRepository::thumb($it['image']))) ?>" alt="" width="44" height="55"><a href="<?= e(url('admin/produto-form.php', ['id' => $it['product_id']])) ?>"><?= e($it['product_name']) ?></a></div></td>
                    <td class="num-col"><?= (int) $it['quantity'] ?></td>
                    <td class="num-col"><?= e(money($it['unit_price'])) ?></td>
                    <td class="num-col"><?= e(money($it['subtotal'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="3">Total do pedido</td><td class="num-col"><strong><?= e(money($order['total'])) ?></strong></td></tr></tfoot>
        </table>
    </section>
    <section class="panel">
        <dl class="order-facts">
            <div><dt>Cliente</dt><dd><?= e($order['customer_name']) ?><br><small><?= e($order['customer_email']) ?></small></dd></div>
            <div><dt>Data</dt><dd><?= e(format_date($order['created_at'], true)) ?></dd></div>
            <div><dt>Pagamento</dt><dd><?= e(payment_label($order['payment_method'])) ?><?= $order['card_last4'] ? ' final ' . e($order['card_last4']) : '' ?> (simulado)</dd></div>
            <div><dt>Status</dt><dd><span class="status status--<?= e($order['status']) ?>"><?= e(status_label($order['status'])) ?></span></dd></div>
            <div class="order-facts__wide"><dt>Entrega</dt><dd><?= e($order['shipping_name']) ?> · <?= e($order['shipping_address']) ?> · <?= e($order['shipping_city']) ?>/<?= e($order['shipping_state']) ?> · <?= e($order['shipping_zip']) ?></dd></div>
        </dl>
    </section>
</div>
<?php else: ?>
<header class="admin__head">
    <div><p class="eyebrow">Vendas</p><h1>Pedidos <small><?= count($orders) ?></small></h1></div>
    <form method="get" class="admin-filters admin-filters--inline">
        <select class="field__select" name="status" aria-label="Filtrar por status" onchange="this.form.submit()">
            <option value="">Todos os status</option>
            <?php foreach (OrderService::STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option><?php endforeach; ?>
        </select>
        <noscript><button class="btn-line btn-small" type="submit">Filtrar</button></noscript>
    </form>
</header>
<div class="panel panel--flush">
<table class="table-admin">
    <thead><tr><th>Pedido</th><th>Cliente</th><th>Itens</th><th>Pagamento</th><th>Data</th><th>Status</th><th class="num-col">Total</th></tr></thead>
    <tbody>
    <?php foreach ($orders as $o): ?>
        <tr>
            <td><a href="<?= e(url('admin/pedidos.php', ['id' => $o['id']])) ?>"><?= e(order_code((int) $o['id'])) ?></a></td>
            <td><?= e($o['customer']) ?><br><small class="text-secondary"><?= e($o['email']) ?></small></td>
            <td class="table-admin__summary"><?= e($o['summary']) ?></td>
            <td><?= e(payment_label($o['payment_method'])) ?></td>
            <td><?= e(format_date($o['created_at'])) ?></td>
            <td><span class="status status--<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
            <td class="num-col"><?= e(money($o['total'])) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="7" class="table-admin__empty">Nenhum pedido com esse status.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
<?php require BASE_PATH . '/views/admin/bottom.php'; ?>
