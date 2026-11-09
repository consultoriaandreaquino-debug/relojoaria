<?php
require __DIR__ . '/config/app.php';

$slug = input($_GET, 'slug', 140);
$product = ($slug !== '' && preg_match('/^[a-z0-9\-]+$/', $slug)) ? ProductRepository::findBySlug($slug) : null;
if (!$product) {
    not_found('Esta peça não está no catálogo. Ela pode ter saído de linha.');
}

$images = ProductRepository::images((int) $product['id']);
if (!$images && $product['image']) {
    $images = [['path' => $product['image'], 'alt' => $product['name']]];
}
$related = ProductRepository::related($product, 3);
$status = stock_status((int) $product['stock']);
$inCart = Cart::raw()[(int) $product['id']] ?? 0;
$maxQty = max(0, min((int) $product['stock'], Cart::MAX_PER_ITEM) - $inCart);
$paragraphs = preg_split('/\R{2,}/', trim((string) $product['description']));

$specs = [
    'Caixa' => $product['case_material'],
    'Diâmetro' => mm($product['diameter_mm']) . ' mm',
    'Mecanismo' => $product['movement_type'] . ($product['caliber'] ? ', calibre ' . $product['caliber'] : ''),
    'Reserva de marcha' => $product['power_reserve_h'] ? $product['power_reserve_h'] . ' horas' : null,
    'Mostrador' => $product['dial'],
    'Resistência à água' => $product['water_resistance_m'] . ' metros',
    'Pulseira' => $product['strap_type'],
    'Para' => gender_label($product['gender']),
];

$title = $product['name'];
$description = mb_substr(strip_tags($paragraphs[0] ?? ''), 0, 155);
$bodyClass = 'page-light page-product';
$headerVariant = 'light';
$scripts = ['product.js'];
$preloadImage = image_url($images[0]['path'] ?? $product['image']);
require __DIR__ . '/views/partials/head.php';
require __DIR__ . '/views/partials/header.php';
?>
<main id="conteudo" class="product">
    <div class="product__layout">
        <section class="gallery" aria-label="Fotos do <?= e($product['name']) ?>" data-gallery>
            <div class="gallery__main">
                <?php foreach ($images as $i => $img): ?>
                <figure class="gallery__slide<?= $i === 0 ? ' is-active' : '' ?>" data-slide="<?= $i ?>" <?= $i === 0 ? '' : 'hidden' ?>>
                    <div class="gallery__zoom" data-zoom tabindex="0" role="img" aria-label="<?= e($img['alt'] ?? $product['name']) ?>. Passe o mouse para ampliar.">
                        <img src="<?= e(image_url($img['path'])) ?>" alt="" width="1000" height="1250" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                    </div>
                </figure>
                <?php endforeach; ?>
                <button type="button" class="gallery__expand icon-btn" data-gallery-open aria-label="Ver foto em tela cheia">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>
                </button>
            </div>
            <?php if (count($images) > 1): ?>
            <div class="gallery__thumbs" role="tablist" aria-label="Escolher foto">
                <?php foreach ($images as $i => $img): ?>
                <button type="button" class="gallery__thumb<?= $i === 0 ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-thumb="<?= $i ?>" aria-label="<?= e($img['alt'] ?? 'Foto ' . ($i + 1)) ?>">
                    <img src="<?= e(image_url($img['path'])) ?>" alt="" width="120" height="150" loading="lazy">
                </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <section class="product__info" aria-labelledby="produtoNome">
            <ol class="crumbs">
                <li><a href="<?= e(url('catalogo.php')) ?>">Catálogo</a></li>
                <li><a href="<?= e(url('catalogo.php', ['colecao' => $product['category_slug']])) ?>"><?= e($product['category_name']) ?></a></li>
            </ol>
            <h1 class="product__name" id="produtoNome"><?= e($product['name']) ?></h1>
            <p class="product__sub"><?= e($product['case_material']) ?> · <?= e(mm($product['diameter_mm'])) ?> mm · <?= e($product['movement_type']) ?></p>

            <div class="product__price-row">
                <p class="product__price num"><?= e(money($product['price'])) ?></p>
                <span class="stock stock--<?= e($status['key']) ?>"><?= e($status['label']) ?></span>
            </div>
            <p class="product__installments">Em até 10× de <?= e(money(bcdiv((string) $product['price'], '10', 2))) ?> sem juros no cartão</p>

            <?php if ((int) $product['stock'] > 0): ?>
            <form class="buy" method="post" action="<?= e(url('actions/cart.php')) ?>" data-cart-form data-open-mini="1" data-bag-url="<?= e(url('sacola.php')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="back" value="<?= e(url('produto.php', ['slug' => $product['slug']])) ?>">
                <?php if ($maxQty > 0): ?>
                <div class="buy__row">
                    <div class="qty" data-qty>
                        <button type="button" data-qty-step="-1" aria-label="Diminuir quantidade">−</button>
                        <label class="visually-hidden" for="quantidade">Quantidade</label>
                        <input id="quantidade" type="number" name="quantity" value="1" min="1" max="<?= (int) $maxQty ?>" inputmode="numeric" required>
                        <button type="button" data-qty-step="1" aria-label="Aumentar quantidade">+</button>
                    </div>
                    <button type="submit" class="btn-solid buy__submit">Adicionar à sacola</button>
                </div>
                <p class="buy__note"><?= $inCart ? 'Você já tem ' . (int) $inCart . ' na sacola. ' : '' ?>Até <?= (int) $maxQty ?> <?= $maxQty === 1 ? 'unidade disponível' : 'unidades disponíveis' ?> para esta compra.</p>
                <?php else: ?>
                <p class="buy__note">Você já colocou na sacola todas as unidades disponíveis desta peça.</p>
                <a class="btn-line" href="<?= e(url('sacola.php')) ?>">Ver sacola</a>
                <?php endif; ?>
            </form>
            <?php else: ?>
            <div class="buy buy--out">
                <p>Esta peça esgotou. A oficina monta novas unidades em pequenos lotes; a boutique avisa quando houver reposição pelo (11) 3000-1968.</p>
                <button type="button" class="btn-solid" disabled>Esgotado</button>
            </div>
            <?php endif; ?>

            <ul class="product__service">
                <li>Entrega segurada e gratuita</li>
                <li>Garantia de 5 anos</li>
                <li>Gravação no fundo, sem custo</li>
            </ul>

            <div class="product__text">
                <?php foreach ($paragraphs as $para): ?>
                <p><?= e($para) ?></p>
                <?php endforeach; ?>
            </div>

            <h2 class="product__spec-title">Ficha técnica</h2>
            <dl class="spec-list">
                <?php foreach ($specs as $label => $value): if ($value === null || $value === '') { continue; } ?>
                <div class="spec-list__row">
                    <dt><?= e($label) ?></dt>
                    <dd><?= e($value) ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
        </section>
    </div>

    <?php if ($related): ?>
    <section class="related" aria-labelledby="relacionadosTitulo">
        <div class="section__head section__head--row">
            <h2 class="section-title" id="relacionadosTitulo">Da mesma coleção</h2>
            <a class="link-arrow" href="<?= e(url('catalogo.php', ['colecao' => $product['category_slug']])) ?>">Ver <?= e($product['category_name']) ?></a>
        </div>
        <div class="related__grid">
            <?php foreach ($related as $item): ?>
                <?php partial('product-card', ['product' => $item]); ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>

<!-- Foto em tela cheia: no celular, permite ampliar com pinça -->
<div class="lightbox" hidden data-lightbox role="dialog" aria-modal="true" aria-label="Foto ampliada">
    <button type="button" class="icon-btn lightbox__close" data-lightbox-close aria-label="Fechar foto">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg>
    </button>
    <div class="lightbox__scroller"><img src="" alt="" data-lightbox-img></div>
</div>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
