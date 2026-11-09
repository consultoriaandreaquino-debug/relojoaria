<?php
require __DIR__ . '/_guard.php';

// Ações (POST + CSRF já verificado pela guarda)
if (is_post()) {
    $id = int_param($_POST['id'] ?? null);
    $action = input($_POST, 'action', 20);
    $product = $id ? ProductRepository::find($id) : null;
    if (!$product) {
        flash('error', 'Peça não encontrada.');
        redirect('admin/produtos.php');
    }
    switch ($action) {
        case 'stock':
            $raw = input($_POST, 'stock', 6);
            if (!preg_match('/^\d{1,6}$/', $raw) || (int) $raw > 100000) {
                flash('error', 'Estoque inválido para ' . $product['name'] . '. Use um número inteiro de 0 a 100000.');
                break;
            }
            ProductAdmin::setStock($id, (int) $raw);
            flash('success', 'Estoque do ' . $product['name'] . ' agora é ' . (int) $raw . '.');
            break;
        case 'toggle':
            ProductAdmin::setActive($id, !(int) $product['is_active']);
            flash('success', $product['name'] . ((int) $product['is_active'] ? ' foi desativado e saiu da loja.' : ' foi reativado e voltou à loja.'));
            break;
        case 'delete':
            $result = ProductAdmin::delete($id);
            flash($result === 'deleted' ? 'success' : 'info', $result === 'deleted'
                ? $product['name'] . ' foi excluído.'
                : $product['name'] . ' já tem pedidos, então não pode ser apagado (o histórico depende dele). A peça foi desativada.');
            break;
        default:
            flash('error', 'Ação inválida.');
    }
    $back = safe_next(input($_POST, 'back', 300));
    redirect($back !== '' ? $back : 'admin/produtos.php');
}

$q = input($_GET, 'q', 80);
$status = in_array($_GET['status'] ?? '', ['ativos', 'inativos', 'baixo'], true) ? $_GET['status'] : '';
$products = ProductAdmin::listing($q, $status);
$back = url('admin/produtos.php', ['q' => $q, 'status' => $status]);

$title = 'Produtos';
$active = 'produtos';
require BASE_PATH . '/views/admin/top.php';
?>
<header class="admin__head">
    <div>
        <p class="eyebrow">Catálogo</p>
        <h1>Produtos <small><?= count($products) ?></small></h1>
    </div>
    <a class="btn-solid btn-small" href="<?= e(url('admin/produto-form.php')) ?>">Nova peça</a>
</header>

<form class="admin-filters" method="get">
    <input class="field__input" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nome" aria-label="Buscar por nome">
    <select class="field__select" name="status" aria-label="Situação" onchange="this.form.submit()">
        <option value="">Todas</option>
        <option value="ativos" <?= $status === 'ativos' ? 'selected' : '' ?>>Ativas</option>
        <option value="inativos" <?= $status === 'inativos' ? 'selected' : '' ?>>Desativadas</option>
        <option value="baixo" <?= $status === 'baixo' ? 'selected' : '' ?>>Estoque baixo (≤ 3)</option>
    </select>
    <button class="btn-line btn-small" type="submit">Filtrar</button>
</form>

<div class="panel panel--flush">
<table class="table-admin table-admin--products">
    <thead>
        <tr><th>Peça</th><th>Coleção</th><th class="num-col">Preço</th><th>Estoque</th><th class="num-col">Vendidas</th><th>Situação</th><th><span class="visually-hidden">Ações</span></th></tr>
    </thead>
    <tbody>
    <?php foreach ($products as $p): ?>
        <tr class="<?= (int) $p['is_active'] ? '' : 'is-inactive' ?>">
            <td>
                <div class="table-product">
                    <img src="<?= e(image_url(ProductRepository::thumb($p['image']))) ?>" alt="" width="44" height="55" loading="lazy">
                    <div>
                        <a href="<?= e(url('admin/produto-form.php', ['id' => $p['id']])) ?>"><?= e($p['name']) ?></a>
                        <small><?= e($p['slug']) ?><?= (int) $p['is_featured'] ? ' · destaque' : '' ?><?= (int) $p['is_hero'] ? ' · topo da home' : '' ?></small>
                    </div>
                </div>
            </td>
            <td><?= e($p['category_name']) ?></td>
            <td class="num-col"><?= e(money($p['price'])) ?></td>
            <td>
                <form method="post" class="stock-form">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="stock">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <input type="hidden" name="back" value="<?= e($back) ?>">
                    <label class="visually-hidden" for="estoque<?= (int) $p['id'] ?>">Estoque de <?= e($p['name']) ?></label>
                    <input class="stock-form__input<?= (int) $p['stock'] <= 3 ? ' is-low' : '' ?>" id="estoque<?= (int) $p['id'] ?>" type="number" name="stock" value="<?= (int) $p['stock'] ?>" min="0" max="100000" required>
                    <button class="stock-form__save" type="submit">Salvar</button>
                </form>
            </td>
            <td class="num-col"><?= (int) $p['sold'] ?></td>
            <td><span class="status <?= (int) $p['is_active'] ? 'status--pago' : 'status--cancelado' ?>"><?= (int) $p['is_active'] ? 'Ativa' : 'Desativada' ?></span></td>
            <td>
                <div class="row-actions">
                    <a href="<?= e(url('admin/produto-form.php', ['id' => $p['id']])) ?>">Editar</a>
                    <form method="post">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <input type="hidden" name="back" value="<?= e($back) ?>">
                        <button type="submit" class="link-plain"><?= (int) $p['is_active'] ? 'Desativar' : 'Reativar' ?></button>
                    </form>
                    <form method="post" data-confirm="<?= (int) $p['sold'] > 0 ? 'Esta peça já tem pedidos e será apenas desativada. Continuar?' : 'Excluir ' . e($p['name']) . ' de vez? Isso não pode ser desfeito.' ?>">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <input type="hidden" name="back" value="<?= e($back) ?>">
                        <button type="submit" class="link-plain link-danger">Excluir</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?><tr><td colspan="7" class="table-admin__empty">Nenhuma peça com esse filtro.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php require BASE_PATH . '/views/admin/bottom.php'; ?>
