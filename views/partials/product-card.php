<?php
/**
 * Cartão de produto. Variáveis: $product, $size ('default' | 'tall' | 'wide'), $eager (bool)
 */
$p = $product;
$status = stock_status((int) $p['stock']);
$size = $size ?? 'default';
$link = url('produto.php', ['slug' => $p['slug']]);
?>
<article class="product-card product-card--<?= e($size) ?>">
    <a class="product-card__media" href="<?= e($link) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e(image_url(ProductRepository::thumb($p['image']))) ?>"
             alt="" width="480" height="600" <?= empty($eager) ? 'loading="lazy"' : '' ?> decoding="async">
    </a>
    <div class="product-card__body">
        <p class="product-card__collection"><?= e($p['category_name']) ?></p>
        <h3 class="product-card__name"><a href="<?= e($link) ?>"><?= e($p['name']) ?></a></h3>
        <p class="product-card__spec"><?= e($p['case_material']) ?> · <?= e(mm($p['diameter_mm'])) ?> mm</p>
        <div class="product-card__foot">
            <span class="product-card__price"><?= e(money($p['price'])) ?></span>
            <span class="stock stock--<?= e($status['key']) ?>"><?= e($status['label']) ?></span>
        </div>
    </div>
</article>
