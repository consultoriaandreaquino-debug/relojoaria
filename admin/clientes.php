<?php
require __DIR__ . '/_guard.php';

$q = input($_GET, 'q', 80);
$sql = "SELECT u.id, u.name, u.email, u.role, u.created_at,
               COUNT(o.id) AS orders,
               COALESCE(SUM(CASE WHEN o.status <> 'cancelado' THEN o.total END), 0) AS spent,
               MAX(o.created_at) AS last_order
        FROM users u LEFT JOIN orders o ON o.user_id = u.id";
if ($q !== '') {
    $stmt = db()->prepare($sql . ' WHERE u.name LIKE ? OR u.email LIKE ? GROUP BY u.id ORDER BY u.created_at DESC');
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $stmt->execute([$like, $like]);
} else {
    $stmt = db()->query($sql . ' GROUP BY u.id ORDER BY u.created_at DESC');
}
$users = $stmt->fetchAll();

$title = 'Clientes';
$active = 'clientes';
require BASE_PATH . '/views/admin/top.php';
?>
<header class="admin__head">
    <div><p class="eyebrow">Relacionamento</p><h1>Clientes <small><?= count($users) ?></small></h1></div>
    <form method="get" class="admin-filters admin-filters--inline">
        <input class="field__input" type="search" name="q" value="<?= e($q) ?>" placeholder="Nome ou e-mail" aria-label="Buscar cliente">
        <button class="btn-line btn-small" type="submit">Buscar</button>
    </form>
</header>
<div class="panel panel--flush">
<table class="table-admin">
    <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Desde</th><th class="num-col">Pedidos</th><th class="num-col">Total comprado</th><th>Último pedido</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= $u['role'] === 'admin' ? 'Equipe' : 'Cliente' ?></td>
            <td><?= e(format_date($u['created_at'])) ?></td>
            <td class="num-col"><?= (int) $u['orders'] ?></td>
            <td class="num-col"><?= e(money($u['spent'])) ?></td>
            <td><?= $u['last_order'] ? e(format_date($u['last_order'])) : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$users): ?><tr><td colspan="7" class="table-admin__empty">Ninguém encontrado.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php require BASE_PATH . '/views/admin/bottom.php'; ?>
