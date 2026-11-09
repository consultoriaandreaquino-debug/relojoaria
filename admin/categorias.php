<?php
require __DIR__ . '/_guard.php';

if (is_post()) {
    $action = input($_POST, 'action', 20);
    $id = int_param($_POST['id'] ?? null);
    $name = input($_POST, 'name', 80);
    $slug = slugify(input($_POST, 'slug', 90) ?: $name);
    $description = input($_POST, 'description', 400);
    $special = !empty($_POST['is_special']) ? 1 : 0;
    $order = (int) (preg_match('/^\d{1,4}$/', input($_POST, 'sort_order', 4)) ? input($_POST, 'sort_order', 4) : 0);

    if ($action === 'delete' && $id) {
        $count = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $count->execute([$id]);
        if ((int) $count->fetchColumn() > 0) {
            flash('error', 'Esta coleção tem peças. Mova as peças para outra coleção antes de excluir.');
        } else {
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            flash('success', 'Coleção excluída.');
        }
        redirect('admin/categorias.php');
    }

    if (in_array($action, ['create', 'update'], true)) {
        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors[] = 'Informe o nome da coleção.';
        }
        if ($slug === '') {
            $errors[] = 'Endereço (slug) inválido.';
        } else {
            $dup = db()->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ?');
            $dup->execute([$slug, $id ?? 0]);
            if ($dup->fetch()) {
                $errors[] = 'Já existe uma coleção com o endereço "' . $slug . '".';
            }
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
        } elseif ($action === 'create') {
            db()->prepare('INSERT INTO categories (name, slug, description, is_special, sort_order) VALUES (?, ?, ?, ?, ?)')
                ->execute([$name, $slug, $description ?: null, $special, $order]);
            flash('success', 'Coleção ' . $name . ' criada.');
        } elseif ($id) {
            db()->prepare('UPDATE categories SET name = ?, slug = ?, description = ?, is_special = ?, sort_order = ? WHERE id = ?')
                ->execute([$name, $slug, $description ?: null, $special, $order, $id]);
            flash('success', 'Coleção ' . $name . ' atualizada.');
        }
    }
    redirect('admin/categorias.php');
}

$rows = db()->query('SELECT c.*, COUNT(p.id) AS total, SUM(p.is_active) AS active FROM categories c
                     LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.sort_order, c.name')->fetchAll();

$title = 'Coleções';
$active = 'categorias';
require BASE_PATH . '/views/admin/top.php';
?>
<header class="admin__head">
    <div><p class="eyebrow">Catálogo</p><h1>Coleções <small><?= count($rows) ?></small></h1></div>
</header>

<div class="panel panel--flush">
<table class="table-admin">
    <thead><tr><th>Ordem</th><th>Nome e descrição</th><th>Endereço</th><th>Campanha</th><th class="num-col">Peças</th><th><span class="visually-hidden">Ações</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $c): $fid = 'cat' . (int) $c['id']; ?>
    <tr>
        <td><input form="<?= $fid ?>" class="stock-form__input" type="number" name="sort_order" value="<?= (int) $c['sort_order'] ?>" min="0" max="9999" aria-label="Ordem de <?= e($c['name']) ?>"></td>
        <td>
            <input form="<?= $fid ?>" class="field__input field__input--compact" name="name" value="<?= e($c['name']) ?>" maxlength="80" required aria-label="Nome">
            <input form="<?= $fid ?>" class="field__input field__input--compact mt-1" name="description" value="<?= e($c['description']) ?>" maxlength="400" aria-label="Descrição de <?= e($c['name']) ?>">
        </td>
        <td><input form="<?= $fid ?>" class="field__input field__input--compact" name="slug" value="<?= e($c['slug']) ?>" maxlength="90" aria-label="Endereço de <?= e($c['name']) ?>"></td>
        <td><label class="check"><input form="<?= $fid ?>" type="checkbox" name="is_special" value="1" <?= (int) $c['is_special'] ? 'checked' : '' ?>><span>Especial</span></label></td>
        <td class="num-col"><?= (int) $c['active'] ?>/<?= (int) $c['total'] ?></td>
        <td>
            <div class="row-actions">
                <form id="<?= $fid ?>" method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="link-plain">Salvar</button>
                </form>
                <form method="post" data-confirm="Excluir a coleção <?= e($c['name']) ?>?">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="link-plain link-danger" <?= (int) $c['total'] > 0 ? 'disabled title="Tem peças vinculadas"' : '' ?>>Excluir</button>
                </form>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<section class="panel mt-4">
    <h2 class="panel__title">Nova coleção</h2>
    <form method="post" class="row g-3 align-items-end">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="create">
        <div class="col-md-3"><label class="field__label" for="novoNome">Nome</label><input class="field__input" id="novoNome" name="name" maxlength="80" required></div>
        <div class="col-md-5"><label class="field__label" for="novaDescricao">Descrição</label><input class="field__input" id="novaDescricao" name="description" maxlength="400"></div>
        <div class="col-md-2"><label class="field__label" for="novaOrdem">Ordem</label><input class="field__input" id="novaOrdem" name="sort_order" type="number" min="0" value="<?= count($rows) + 1 ?>"></div>
        <div class="col-md-2"><button type="submit" class="btn-solid btn-small w-100">Criar</button></div>
        <div class="col-12"><label class="check"><input type="checkbox" name="is_special" value="1"><span>Coleção especial (aparece na campanha da home)</span></label></div>
    </form>
</section>
<?php require BASE_PATH . '/views/admin/bottom.php'; ?>
