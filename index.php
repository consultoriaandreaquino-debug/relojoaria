<?php
require __DIR__ . '/config/app.php';

$hero = ProductRepository::hero();
$collections = CategoryRepository::all();
$featured = ProductRepository::featured(5);
$special = CategoryRepository::special();
$specialProducts = $special ? ProductRepository::byCategory((int) $special['id'], 3) : [];

$heroImage = $hero ? (ProductRepository::heroImage($hero) ?? $hero['image']) : null;
$bySlug = [];
foreach ($collections as $c) {
    $bySlug[$c['slug']] = $c;
}
// Ordem editorial das coleções na grade assimétrica
$layout = ['automaticos' => 'a', 'cronografos' => 'b', 'classicos' => 'c', 'esportivos' => 'd', 'minimalistas' => 'e', 'edicoes-especiais' => 'f'];

$title = '';
$bodyClass = 'page-home';
$headerVariant = 'overlay';
$preloadImage = $heroImage ? image_url($heroImage) : null;
$scripts = ['reveal.js'];
require __DIR__ . '/views/partials/head.php';
require __DIR__ . '/views/partials/header.php';

?>
<main id="conteudo">

<?php if ($hero): [$first, $last] = split_name($hero['name']); ?>
<!-- Hero: a peça sai da sombra -->
<section class="hero" data-reveal aria-labelledby="heroTitulo">
    <div class="hero__stage">
        <figure class="hero__watch" data-reveal-stage>
            <img class="hero__img hero__img--veiled" src="<?= e(image_url($heroImage)) ?>" alt="" width="1000" height="1250" aria-hidden="true">
            <img class="hero__img hero__img--lit" src="<?= e(image_url($heroImage)) ?>" alt="<?= e($hero['name']) ?>, mostrador <?= e(mb_strtolower((string) $hero['dial'])) ?>" width="1000" height="1250" fetchpriority="high">
            <svg class="hero__ring" viewBox="0 0 400 400" aria-hidden="true">
                <circle class="hero__ring-line" cx="200" cy="200" r="196"/>
                <circle class="hero__ring-ticks" cx="200" cy="200" r="188"/>
                <circle class="hero__ring-arc" cx="200" cy="200" r="196" pathLength="360"/>
                <g class="hero__ring-sweep"><circle cx="200" cy="4" r="2.6"/></g>
            </svg>
            <ul class="hero__points" aria-label="Detalhes da peça">
                <?php if ($hero['caliber']): ?>
                <li class="hero__point hero__point--a"><span class="hero__dot"></span><span class="hero__label">Calibre <?= e($hero['caliber']) ?><small><?= e($hero['movement_type']) ?></small></span></li>
                <?php endif; ?>
                <li class="hero__point hero__point--b"><span class="hero__dot"></span><span class="hero__label"><?= e(mm($hero['diameter_mm'])) ?> mm<small><?= e($hero['case_material']) ?></small></span></li>
                <?php if ($hero['power_reserve_h']): ?>
                <li class="hero__point hero__point--c"><span class="hero__dot"></span><span class="hero__label"><?= (int) $hero['power_reserve_h'] ?> horas<small>de reserva de marcha</small></span></li>
                <?php endif; ?>
            </ul>
        </figure>
    </div>

    <div class="hero__copy">
        <p class="eyebrow eyebrow--light">Coleção <?= e($hero['category_name']) ?></p>
        <h1 class="hero__title" id="heroTitulo"><?= e($first) ?> <em><?= e($last) ?></em></h1>
        <p class="hero__line"><?= e($hero['dial']) ?>. <?= e($hero['strap_type']) ?>.</p>
        <div class="hero__buy">
            <span class="hero__price"><?= e(money($hero['price'])) ?></span>
            <a class="btn-line btn-line--light" href="<?= e(url('produto.php', ['slug' => $hero['slug']])) ?>">Descobrir</a>
        </div>
    </div>

    <div class="hero__control" data-reveal-control hidden>
        <label class="hero__control-label" for="revelar">Arraste para revelar</label>
        <input class="hero__range" id="revelar" type="range" min="0" max="100" step="1" value="0" data-reveal-range aria-valuetext="Peça coberta">
    </div>
</section>
<?php endif; ?>

<!-- Coleções em composição assimétrica -->
<section class="section collections" aria-labelledby="colecoesTitulo">
    <div class="section__head section__head--split">
        <h2 class="section-title" id="colecoesTitulo">Seis coleções, <em>um mesmo cuidado</em></h2>
        <p class="section__intro">Cada coleção nasce de um uso: o dia a dia, a pista, o mergulho, a ocasião. Todas saem da mesma oficina.</p>
    </div>
    <div class="collections__grid">
        <?php foreach ($layout as $slug => $area):
            $c = $bySlug[$slug] ?? null;
            if (!$c) { continue; } ?>
        <a class="collection-tile collection-tile--<?= e($area) ?><?= $c['is_special'] ? ' collection-tile--special' : '' ?>" href="<?= e(url('catalogo.php', ['colecao' => $c['slug']])) ?>">
            <?php if ($c['cover'] && $area !== 'e'): ?>
            <span class="collection-tile__media">
                <img src="<?= e(image_url(ProductRepository::thumb($c['cover']))) ?>" alt="" width="480" height="600" loading="lazy">
            </span>
            <?php endif; ?>
            <span class="collection-tile__text">
                <span class="collection-tile__index"><?= sprintf('%02d', (int) $c['sort_order']) ?></span>
                <span class="collection-tile__name"><?= e($c['name']) ?></span>
                <?php if (in_array($area, ['a', 'c', 'e'], true)): ?>
                <span class="collection-tile__desc"><?= e($c['description']) ?></span>
                <?php endif; ?>
                <span class="collection-tile__meta"><?= (int) $c['product_count'] ?> peças<?= $c['min_price'] ? ' · a partir de ' . e(money($c['min_price'])) : '' ?></span>
            </span>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Detalhes aproximados -->
<section class="section macro" aria-labelledby="macroTitulo">
    <div class="macro__grid">
        <figure class="macro__item macro__item--dial">
            <img src="<?= e(url('assets/img/editorial/macro-mostrador.webp')) ?>" alt="Mostrador verde com acabamento raios de sol e índices em ouro, visto de muito perto" width="1200" height="1500" loading="lazy">
            <figcaption><span class="eyebrow eyebrow--light">O mostrador</span>Escovado do centro para a borda. A luz corre sobre ele conforme o pulso se move.</figcaption>
        </figure>
        <div class="macro__text">
            <h2 class="section-title section-title--light" id="macroTitulo">Perto o bastante <em>para ver o trabalho</em></h2>
            <p>Cada peça passa por 31 etapas de acabamento antes de sair da oficina. A maior parte delas só aparece a poucos centímetros do relógio.</p>
        </div>
        <figure class="macro__item macro__item--crown">
            <img src="<?= e(url('assets/img/editorial/macro-coroa.webp')) ?>" alt="Perfil da caixa com luneta serrilhada em ouro e coroa gravada" width="1200" height="1500" loading="lazy">
            <figcaption><span class="eyebrow eyebrow--light">A coroa</span>Rosqueada, para vedar a caixa. O símbolo da casa é gravado, não impresso.</figcaption>
        </figure>
        <figure class="macro__item macro__item--clasp">
            <img src="<?= e(url('assets/img/editorial/macro-fecho.webp')) ?>" alt="Fecho dobrável em ouro com elos polidos e escovados" width="1200" height="1500" loading="lazy">
            <figcaption><span class="eyebrow eyebrow--light">O fecho</span>Trava dupla e ajuste fino de 5 mm, sem ferramenta.</figcaption>
        </figure>
    </div>
</section>

<!-- Destaques vindos do banco -->
<?php if ($featured): ?>
<section class="section featured" aria-labelledby="destaquesTitulo">
    <div class="section__head section__head--row">
        <h2 class="section-title" id="destaquesTitulo">Em destaque</h2>
        <a class="link-arrow" href="<?= e(url('catalogo.php')) ?>">Ver o catálogo</a>
    </div>
    <div class="featured__grid">
        <?php foreach ($featured as $i => $product): ?>
            <?php partial('product-card', ['product' => $product, 'size' => $i === 0 ? 'tall' : 'default']); ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Campanha em verde Heritage -->
<?php if ($special && $specialProducts): ?>
<section class="campaign" aria-labelledby="campanhaTitulo">
    <figure class="campaign__media">
        <img src="<?= e(url('assets/img/editorial/campanha-heritage.webp')) ?>" alt="Relógio Heritage Verde sobre almofada de camurça, em mesa de mármore verde, sob luz de vela" width="1600" height="1200" loading="lazy">
    </figure>
    <div class="campaign__copy">
        <p class="eyebrow eyebrow--light"><?= e($special['name']) ?></p>
        <h2 class="campaign__title" id="campanhaTitulo">Heritage, <em>58 anos</em></h2>
        <p>Duas séries numeradas para marcar o aniversário da casa. Verde com ouro, azul-noite com ouro. Quando a última peça sair, a série se encerra.</p>
        <ul class="campaign__list">
            <?php foreach ($specialProducts as $sp): $st = stock_status((int) $sp['stock']); ?>
            <li>
                <a href="<?= e(url('produto.php', ['slug' => $sp['slug']])) ?>">
                    <span class="campaign__name"><?= e($sp['name']) ?></span>
                    <span class="campaign__price"><?= e(money($sp['price'])) ?></span>
                    <span class="campaign__stock"><?= e($st['label']) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <a class="btn-line btn-line--light" href="<?= e(url('catalogo.php', ['colecao' => $special['slug']])) ?>">Ver a coleção</a>
    </div>
</section>
<?php endif; ?>

<!-- História -->
<section class="section story" id="historia" aria-labelledby="historiaTitulo">
    <div class="story__lead">
        <p class="eyebrow">Desde 1968</p>
        <h2 class="section-title" id="historiaTitulo">Começou com <em>relógios dos outros</em></h2>
        <p>Heitor Morvane abriu uma pequena oficina em São Paulo para consertar relógios de bolso da vizinhança. Muitos eram presentes de pais e avós. Ele aprendeu cedo que ninguém traz um relógio para o conserto por causa das horas.</p>
        <p>Em 1974 montou a primeira peça com o próprio nome. Hoje a casa tem 22 relojoeiros e continua fazendo revisão em qualquer Morvane, de qualquer década.</p>
    </div>
    <blockquote class="story__quote">
        <p>“Um relógio bom é aquele que alguém vai querer herdar.”</p>
        <cite>Heitor Morvane, fundador</cite>
    </blockquote>
    <ol class="story__timeline">
        <li><span class="story__year">1968</span><span>Oficina de conserto na Rua Augusta.</span></li>
        <li><span class="story__year">1984</span><span>MV-07, o primeiro calibre de corda manual da casa.</span></li>
        <li><span class="story__year">2009</span><span>MV-24, automático com 70 horas de reserva.</span></li>
        <li><span class="story__year">2026</span><span>58 anos e as séries numeradas Heritage.</span></li>
    </ol>
</section>

</main>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
