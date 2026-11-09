<?php
require __DIR__ . '/_guard.php';

$id = int_param($_GET['id'] ?? ($_POST['id'] ?? null));
$product = $id ? ProductRepository::find($id) : null;
if ($id && !$product) {
    flash('error', 'Peça não encontrada.');
    redirect('admin/produtos.php');
}

/** Reorganiza $_FILES['gallery'] (múltiplo) em uma lista de arquivos. */
function gallery_files(): array
{
    $f = $_FILES['gallery'] ?? null;
    if (!$f || !is_array($f['name'] ?? null)) {
        return [];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        $file = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        if (Upload::present($file)) {
            $out[] = $file;
        }
    }
    return array_slice($out, 0, 4);
}

if (is_post()) {
    $action = input($_POST, 'action', 20);

    if ($action === 'remove_image' && $product) {
        $imageId = int_param($_POST['image_id'] ?? null);
        $ok = $imageId && ProductAdmin::removeGalleryImage((int) $product['id'], $imageId);
        flash($ok ? 'success' : 'error', $ok ? 'Foto removida da galeria.' : 'Não foi possível remover esta foto.');
        redirect('admin/produto-form.php', ['id' => $product['id']]);
    }

    $data = ProductAdmin::fromInput($_POST);
    $errors = ProductAdmin::validate($data, $product ? (int) $product['id'] : null);

    // Valida os uploads antes de gravar qualquer coisa
    $mainFile = $_FILES['image'] ?? null;
    $newMain = null;
    $newGallery = [];
    if (!$errors) {
        try {
            if (Upload::present($mainFile)) {
                $newMain = Upload::image($mainFile);
            } elseif (!$product) {
                $errors['image'] = 'Envie a foto principal da peça.';
            }
            foreach (gallery_files() as $file) {
                $newGallery[] = Upload::image($file);
            }
        } catch (DomainException $e) {
            $errors[Upload::present($mainFile) && !$newMain ? 'image' : 'gallery'] = $e->getMessage();
        }
    }

    if ($errors) {
        // descarta o que já tinha sido salvo nesta tentativa
        Upload::delete($newMain);
        foreach ($newGallery as $path) {
            Upload::delete($path);
        }
        remember_form($_POST, $errors);
        redirect('admin/produto-form.php', $product ? ['id' => $product['id']] : []);
    }

    if ($product) {
        ProductAdmin::update((int) $product['id'], $data);
        $pid = (int) $product['id'];
        if ($newMain) {
            ProductAdmin::replaceMainImage($pid, $newMain);
        }
        flash('success', 'Alterações salvas em ' . $data['name'] . '.');
    } else {
        $pid = ProductAdmin::create($data, $newMain);
        flash('success', $data['name'] . ' foi cadastrado.');
    }
    foreach ($newGallery as $i => $path) {
        ProductAdmin::addGalleryImage($pid, $path, $data['name'] . ', foto ' . ($i + 2));
    }
    redirect('admin/produto-form.php', ['id' => $pid]);
}

// Valores do formulário: dados reenviados após erro > produto salvo > padrão
$values = $product ?? ['is_active' => 1, 'gender' => 'unissex', 'water_resistance_m' => 30, 'stock' => 0];
if (!empty($_SESSION['old'])) {
    $values = array_merge($values, $_SESSION['old']);
    foreach (['is_featured', 'is_hero', 'is_active'] as $flag) {
        $values[$flag] = !empty($_SESSION['old'][$flag]) ? 1 : 0;
    }
}
$v = static fn(string $k): string => (string) ($values[$k] ?? '');
if ($product && empty($_SESSION['old'])) {
    $values['price'] = number_format((float) $product['price'], 2, ',', '');
    $values['diameter_mm'] = str_replace('.', ',', mm($product['diameter_mm']));
}
$categories = CategoryRepository::all();
$images = $product ? ProductRepository::images((int) $product['id']) : [];

$err = static function (string $k): string {
    $m = field_error($k);
    return $m ? '<p class="field__error">' . e($m) . '</p>' : '';
};
$cls = static fn(string $k): string => field_error($k) ? ' has-error' : '';

$title = $product ? 'Editar ' . $product['name'] : 'Nova peça';
$active = 'produtos';
require BASE_PATH . '/views/admin/top.php';
?>
<header class="admin__head">
    <div>
        <p class="eyebrow"><a href="<?= e(url('admin/produtos.php')) ?>">Produtos</a></p>
        <h1><?= $product ? e($product['name']) : 'Nova peça' ?></h1>
    </div>
    <?php if ($product && (int) $product['is_active']): ?>
    <a class="btn-line btn-small" href="<?= e(url('produto.php', ['slug' => $product['slug']])) ?>" target="_blank" rel="noopener">Ver na loja</a>
    <?php endif; ?>
</header>

<?php if (!empty($_SESSION['errors'])): ?>
<p class="notice" role="alert">Confira os campos marcados abaixo.</p>
<?php endif; ?>

<form class="admin-form" method="post" enctype="multipart/form-data" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= Upload::MAX_BYTES ?>">
    <?php if ($product): ?><input type="hidden" name="id" value="<?= (int) $product['id'] ?>"><?php endif; ?>

    <div class="admin-form__grid">
        <section class="panel">
            <h2 class="panel__title">Identificação</h2>
            <div class="row g-3">
                <div class="col-md-7"><div class="field<?= $cls('name') ?>"><label class="field__label" for="name">Nome</label><input class="field__input" id="name" name="name" value="<?= e($v('name')) ?>" maxlength="120" required data-slug-source><?= $err('name') ?></div></div>
                <div class="col-md-5"><div class="field<?= $cls('slug') ?>"><label class="field__label" for="slug">Endereço (slug)</label><input class="field__input" id="slug" name="slug" value="<?= e($v('slug')) ?>" maxlength="140" pattern="[a-z0-9\-]+" data-slug-target><p class="field__hint">Gerado a partir do nome se ficar vazio.</p><?= $err('slug') ?></div></div>
                <div class="col-md-6"><div class="field<?= $cls('category_id') ?>"><label class="field__label" for="category_id">Coleção</label>
                    <select class="field__select" id="category_id" name="category_id" required>
                        <option value="">Escolha</option>
                        <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $v('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select><?= $err('category_id') ?></div></div>
                <div class="col-md-6"><div class="field<?= $cls('gender') ?>"><label class="field__label" for="gender">Para</label>
                    <select class="field__select" id="gender" name="gender">
                        <?php foreach (ProductRepository::GENDERS as $g): ?><option value="<?= e($g) ?>" <?= $v('gender') === $g ? 'selected' : '' ?>><?= e(gender_label($g)) ?></option><?php endforeach; ?>
                    </select><?= $err('gender') ?></div></div>
                <div class="col-12"><div class="field<?= $cls('description') ?>"><label class="field__label" for="description">Descrição</label><textarea class="field__textarea" id="description" name="description" rows="7" maxlength="5000" required><?= e($v('description')) ?></textarea><p class="field__hint">Fatos da peça: material, calibre, reserva, acabamento. Linha em branco separa parágrafos.</p><?= $err('description') ?></div></div>
            </div>
        </section>

        <section class="panel">
            <h2 class="panel__title">Venda</h2>
            <div class="field<?= $cls('price') ?>"><label class="field__label" for="price">Preço (R$)</label><input class="field__input" id="price" name="price" value="<?= e($v('price')) ?>" inputmode="decimal" placeholder="14900,00" required><?= $err('price') ?></div>
            <div class="field<?= $cls('stock') ?>"><label class="field__label" for="stock">Estoque</label><input class="field__input" id="stock" name="stock" type="number" min="0" max="100000" value="<?= e($v('stock')) ?>" required><?= $err('stock') ?></div>
            <label class="check mb-2"><input type="checkbox" name="is_active" value="1" <?= (int) $v('is_active') ? 'checked' : '' ?>><span>Ativa na loja</span></label><br>
            <label class="check mb-2"><input type="checkbox" name="is_featured" value="1" <?= (int) $v('is_featured') ? 'checked' : '' ?>><span>Em destaque na home</span></label><br>
            <label class="check"><input type="checkbox" name="is_hero" value="1" <?= (int) $v('is_hero') ? 'checked' : '' ?>><span>Peça do topo da home</span></label>
        </section>

        <section class="panel">
            <h2 class="panel__title">Ficha técnica</h2>
            <div class="row g-3">
                <div class="col-md-6"><div class="field<?= $cls('case_material') ?>"><label class="field__label" for="case_material">Material da caixa</label><input class="field__input" id="case_material" name="case_material" value="<?= e($v('case_material')) ?>" maxlength="120" required><?= $err('case_material') ?></div></div>
                <div class="col-md-6"><div class="field<?= $cls('movement_type') ?>"><label class="field__label" for="movement_type">Mecanismo</label><input class="field__input" id="movement_type" name="movement_type" value="<?= e($v('movement_type')) ?>" maxlength="80" placeholder="Automático" required><?= $err('movement_type') ?></div></div>
                <div class="col-md-4"><div class="field<?= $cls('caliber') ?>"><label class="field__label" for="caliber">Calibre</label><input class="field__input" id="caliber" name="caliber" value="<?= e($v('caliber')) ?>" maxlength="40"><?= $err('caliber') ?></div></div>
                <div class="col-md-4"><div class="field<?= $cls('power_reserve_h') ?>"><label class="field__label" for="power_reserve_h">Reserva (horas)</label><input class="field__input" id="power_reserve_h" name="power_reserve_h" type="number" min="1" max="1000" value="<?= e($v('power_reserve_h')) ?>"><?= $err('power_reserve_h') ?></div></div>
                <div class="col-md-4"><div class="field<?= $cls('diameter_mm') ?>"><label class="field__label" for="diameter_mm">Diâmetro (mm)</label><input class="field__input" id="diameter_mm" name="diameter_mm" value="<?= e($v('diameter_mm')) ?>" inputmode="decimal" required><?= $err('diameter_mm') ?></div></div>
                <div class="col-md-4"><div class="field<?= $cls('water_resistance_m') ?>"><label class="field__label" for="water_resistance_m">Resistência à água (m)</label><input class="field__input" id="water_resistance_m" name="water_resistance_m" type="number" min="0" max="2000" value="<?= e($v('water_resistance_m')) ?>" required><?= $err('water_resistance_m') ?></div></div>
                <div class="col-md-4"><div class="field<?= $cls('strap_type') ?>"><label class="field__label" for="strap_type">Pulseira</label><input class="field__input" id="strap_type" name="strap_type" value="<?= e($v('strap_type')) ?>" maxlength="120" required><?= $err('strap_type') ?></div></div>
                <div class="col-md-4"><div class="field<?= $cls('dial') ?>"><label class="field__label" for="dial">Mostrador</label><input class="field__input" id="dial" name="dial" value="<?= e($v('dial')) ?>" maxlength="80"><?= $err('dial') ?></div></div>
            </div>
        </section>

        <section class="panel">
            <h2 class="panel__title">Fotos</h2>
            <div class="field<?= $cls('image') ?>">
                <label class="field__label" for="image"><?= $product ? 'Trocar foto principal' : 'Foto principal' ?></label>
                <input class="field__input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" <?= $product ? '' : 'required' ?>>
                <p class="field__hint">JPG, PNG ou WebP, até 2 MB, mínimo 200 px. Proporção 4:5 fica melhor.</p>
                <?= $err('image') ?>
            </div>
            <div class="field<?= $cls('gallery') ?>">
                <label class="field__label" for="gallery">Adicionar à galeria (até 4)</label>
                <input class="field__input" id="gallery" name="gallery[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
                <?= $err('gallery') ?>
            </div>
            <?php if ($images): ?>
            <ul class="gallery-admin">
                <?php foreach ($images as $img): ?>
                <li>
                    <img src="<?= e(image_url($img['path'])) ?>" alt="<?= e($img['alt'] ?? '') ?>" width="96" height="120" loading="lazy">
                    <?php if ($img['path'] === $product['image']): ?>
                    <span class="gallery-admin__tag">Principal</span>
                    <?php else: ?>
                    <button type="submit" class="link-plain link-danger" form="remover<?= (int) $img['id'] ?>">Remover</button>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>

    <div class="admin-form__actions">
        <a class="btn-line btn-small" href="<?= e(url('admin/produtos.php')) ?>">Cancelar</a>
        <button type="submit" class="btn-solid btn-small"><?= $product ? 'Salvar alterações' : 'Cadastrar peça' ?></button>
    </div>
</form>

<?php foreach ($images as $img): if ($img['path'] === ($product['image'] ?? null)) { continue; } ?>
<form id="remover<?= (int) $img['id'] ?>" method="post" data-confirm="Remover esta foto da galeria?" hidden>
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="remove_image">
    <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
    <input type="hidden" name="image_id" value="<?= (int) $img['id'] ?>">
</form>
<?php endforeach; ?>
<?php forget_form(); ?>
<?php require BASE_PATH . '/views/admin/bottom.php'; ?>
