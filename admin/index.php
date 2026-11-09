<?php
require __DIR__ . '/_guard.php';

$pdo = db();
$stats = $pdo->query("SELECT COUNT(*) AS orders,
                             COALESCE(SUM(CASE WHEN status <> 'cancelado' THEN total END), 0) AS revenue,
                             COALESCE(AVG(CASE WHEN status <> 'cancelado' THEN total END), 0) AS ticket,
                             COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status <> 'cancelado' THEN total END), 0) AS revenue30
                      FROM orders")->fetch();
$customers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$activeProducts = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
$lowStock = $pdo->query('SELECT p.id, p.name, p.stock, c.name AS category_name FROM products p JOIN categories c ON c.id = p.category_id
                         WHERE p.is_active = 1 AND p.stock <= 3 ORDER BY p.stock, p.name')->fetchAll();
$latest = $pdo->query('SELECT o.id, o.total, o.status, o.payment_method, o.created_at, u.name AS customer
                       FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC, o.id DESC LIMIT 6')->fetchAll();
$top = $pdo->query("SELECT i.product_name, SUM(i.quantity) AS units, SUM(i.subtotal) AS amount
                    FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.status <> 'cancelado'
                    GROUP BY i.product_id, i.product_name ORDER BY units DESC, amount DESC LIMIT 5")->fetchAll();

$title = 'Painel';
$active = 'index';
require BASE_PATH . '/views/admin/top.php';
?>
<header class="admin__head">
    <div>
        <p class="eyebrow">Visão geral</p>
        <h1>Olá, <?= e(strtok(Auth::user()['name'], ' ')) ?></h1>
    </div>
    <a class="btn-solid btn-small" href="<?= e(url('admin/produto-form.php')) ?>">Nova peça</a>
</header>

<section class="kpis" aria-label="Indicadores">
    <div class="kpi kpi--main">
        <span class="kpi__label">Faturamento total</span>
        <strong class="kpi__value"><?= e(money($stats['revenue'])) ?></strong>
        <span class="kpi__foot">últimos 30 dias: <?= e(money($stats['revenue30'])) ?></span>
    </div>
    <div class="kpi"><span class="kpi__label">Pedidos</span><strong class="kpi__value"><?= (int) $stats['orders'] ?></strong><span class="kpi__foot">ticket médio <?= e(money($stats['ticket'])) ?></span></div>
    <div class="kpi"><span class="kpi__label">Clientes</span><strong class="kpi__value"><?= $customers ?></strong><span class="kpi__foot">cadastrados</span></div>
    <div class="kpi"><span class="kpi__label">Peças ativas</span><strong class="kpi__value"><?= $activeProducts ?></strong><span class="kpi__foot"><?= count($lowStock) ?> com estoque baixo</span></div>
</section>

<div class="admin-grid">
    <section class="panel" aria-labelledby="ultimosTitulo">
        <header class="panel__head"><h2 id="ultimosTitulo">Últimos pedidos</h2><a class="link-arrow" href="<?= e(url('admin/pedidos.php')) ?>">Todos</a></header>
        <table class="table-admin">
            <thead><tr><th>Pedido</th><th>Cliente</th><th>Data</th><th>Status</th><th class="num-col">Total</th></tr></thead>
            <tbody>
            <?php foreach ($latest as $o): ?>
                <tr>
                    <td><a href="<?= e(url('admin/pedidos.php', ['id' => $o['id']])) ?>"><?= e(order_code((int) $o['id'])) ?></a></td>
                    <td><?= e($o['customer']) ?></td>
                    <td><?= e(format_date($o['created_at'])) ?></td>
                    <td><span class="status status--<?= e($o['status']) ?>"><?= e(status_label($o['status'])) ?></span></td>
                    <td class="num-col"><?= e(money($o['total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$latest): ?><tr><td colspan="5" class="table-admin__empty">Nenhum pedido ainda.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel" aria-labelledby="estoqueTitulo">
        <header class="panel__head"><h2 id="estoqueTitulo">Estoque baixo</h2><a class="link-arrow" href="<?= e(url('admin/produtos.php', ['status' => 'baixo'])) ?>">Ajustar</a></header>
        <ul class="stock-list">
            <?php foreach ($lowStock as $p): ?>
            <li>
                <a href="<?= e(url('admin/produto-form.php', ['id' => $p['id']])) ?>"><?= e($p['name']) ?><small><?= e($p['category_name']) ?></small></a>
                <span class="stock-pill<?= (int) $p['stock'] === 0 ? ' stock-pill--zero' : '' ?>"><?= (int) $p['stock'] ?></span>
            </li>
            <?php endforeach; ?>
            <?php if (!$lowStock): ?><li class="table-admin__empty">Tudo com estoque confortável.</li><?php endif; ?>
        </ul>

        <header class="panel__head mt-4"><h2>Mais vendidas</h2></header>
        <ol class="stock-list stock-list--ranked">
            <?php foreach ($top as $t): ?>
            <li><span><?= e($t['product_name']) ?><small><?= e(money($t['amount'])) ?></small></span><span class="num"><?= (int) $t['units'] ?> un.</span></li>
            <?php endforeach; ?>
        </ol>
    </section>
</div>
<?php require BASE_PATH . '/views/admin/bottom.php'; ?>
