<?php
require __DIR__ . '/config/app.php';

// Filtros vindos da URL (GET). Valores fora da whitelist são ignorados.
$filters = [
    'q' => input($_GET, 'q', 80),
    'colecao' => input($_GET, 'colecao', 90),
    'genero' => in_array($_GET['genero'] ?? '', ProductRepository::GENDERS, true) ? $_GET['genero'] : '',
    'ordem' => array_key_exists($_GET['ordem'] ?? '', ProductRepository::SORTS) ? $_GET['ordem'] : 'novidades',
    'disponivel' => ($_GET['disponivel'] ?? '') === '1' ? '1' : '',
];
$page = int_param($_GET['pagina'] ?? null) ?? 1;

$collection = $filters['colecao'] !== '' ? CategoryRepository::findBySlug($filters['colecao']) : null;
if ($filters['colecao'] !== '' && !$collection) {
    $filters['colecao'] = '';
}

$result = ProductRepository::search($filters, $page);
$collections = CategoryRepository::all();

$heading = $collection['name'] ?? ($filters['genero'] !== '' ? gender_label($filters['genero']) : 'Catálogo');
$intro = $collection['description'] ?? 'Todas as peças da casa. Use os filtros para encontrar a sua.';
if ($filters['q'] !== '') {
    $heading = 'Resultados para “' . $filters['q'] . '”';
}

/** URL atual trocando alguns parâmetros (mantém os outros filtros). */
function catalog_url(array $filters, array $change): string
{
    $params = array_merge($filters, $change);
    if (($params['ordem'] ?? '') === 'novidades') {
        unset($params['ordem']);
    }
    if (($params['pagina'] ?? 1) == 1) {
        unset($params['pagina']);
    }
    return url('catalogo.php', $params);
}

$title = $heading;
$bodyClass = 'page-light page-catalog';
$headerVariant = 'light';
$scripts = ['catalog.js'];
require __DIR__ . '/views/partials/head.php';
require __DIR__ . '/views/partials/header.php';
?>
<main id="conteudo" class="catalog">
    <header class="catalog__head">
        <ol class="crumbs">
            <li><a href="<?= e(url('')) ?>">Início</a></li>
            <li><a href="<?= e(url('catalogo.php')) ?>">Catálogo</a></li>
            <?php if ($collection): ?><li aria-current="page"><?= e($collection['name']) ?></li><?php endif; ?>
        </ol>
        <h1 class="catalog__title"><?= e($heading) ?></h1>
        <p class="catalog__intro"><?= e($intro) ?></p>
    </header>

    <form class="filters" method="get" action="<?= e(url('catalogo.php')) ?>" data-filters role="search" aria-label="Filtrar peças">
        <div class="filters__search">
            <label class="visually-hidden" for="filtroBusca">Pesquisar</label>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg>
            <input id="filtroBusca" type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="80" placeholder="Nome, coleção, material">
        </div>
        <div class="filters__group">
            <label class="visually-hidden" for="filtroColecao">Coleção</label>
            <select class="field__select filters__select" id="filtroColecao" name="colecao" data-autosubmit>
                <option value="">Todas as coleções</option>
                <?php foreach ($collections as $c): ?>
                <option value="<?= e($c['slug']) ?>" <?= $filters['colecao'] === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <fieldset class="filters__chips">
            <legend class="visually-hidden">Para quem</legend>
            <?php foreach (['' => 'Todos', 'masculino' => 'Masculino', 'feminino' => 'Feminino', 'unissex' => 'Unissex'] as $value => $label): ?>
            <label class="chip">
                <input type="radio" name="genero" value="<?= e($value) ?>" <?= $filters['genero'] === $value ? 'checked' : '' ?> data-autosubmit>
                <span><?= e($label) ?></span>
            </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="filters__group filters__group--end">
            <label class="check">
                <input type="checkbox" name="disponivel" value="1" <?= $filters['disponivel'] ? 'checked' : '' ?> data-autosubmit>
                <span>Só disponíveis</span>
            </label>
            <label class="visually-hidden" for="filtroOrdem">Ordenar por</label>
            <select class="field__select filters__select" id="filtroOrdem" name="ordem" data-autosubmit>
                <?php foreach (ProductRepository::SORTS as $key => $sort): ?>
                <option value="<?= e($key) ?>" <?= $filters['ordem'] === $key ? 'selected' : '' ?>><?= e($sort['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-line btn-small filters__submit">Aplicar</button>
        </div>
    </form>

    <div class="catalog__meta">
        <p class="num" aria-live="polite"><?= (int) $result['total'] ?> <?= $result['total'] === 1 ? 'peça' : 'peças' ?></p>
        <?php if ($filters['q'] !== '' || $filters['colecao'] !== '' || $filters['genero'] !== '' || $filters['disponivel']): ?>
        <a class="link-arrow" href="<?= e(url('catalogo.php')) ?>">Limpar filtros</a>
        <?php endif; ?>
    </div>

    <?php if (!$result['items']): ?>
    <div class="empty-state">
        <h2>Nenhuma peça com esses filtros</h2>
        <?php if ($filters['q'] !== ''): ?>
        <p>Não encontramos nada para “<?= e($filters['q']) ?>”. Confira a grafia ou tente um termo mais simples, como “aço” ou “cronógrafo”.</p>
        <?php else: ?>
        <p>Tente outra coleção ou tire o filtro de disponibilidade. Se procura algo específico, a boutique pode ajudar pelo (11) 3000-1968.</p>
        <?php endif; ?>
        <a class="btn-line" href="<?= e(url('catalogo.php')) ?>">Ver todas as peças</a>
    </div>
    <?php else: ?>
    <div class="catalog__grid">
        <?php foreach ($result['items'] as $i => $product): ?>
            <?php partial('product-card', ['product' => $product, 'eager' => $i < 3]); ?>
        <?php endforeach; ?>
    </div>

    <?php if ($result['pages'] > 1): ?>
    <nav aria-label="Páginas do catálogo">
        <ul class="pager">
            <li>
                <?php if ($result['page'] > 1): ?>
                <a href="<?= e(catalog_url($filters, ['pagina' => $result['page'] - 1])) ?>" rel="prev">Anterior</a>
                <?php else: ?><span class="is-disabled">Anterior</span><?php endif; ?>
            </li>
            <?php for ($n = 1; $n <= $result['pages']; $n++): ?>
            <li>
                <?php if ($n === $result['page']): ?>
                <span class="is-current" aria-current="page"><?= $n ?></span>
                <?php else: ?>
                <a href="<?= e(catalog_url($filters, ['pagina' => $n])) ?>"><?= $n ?></a>
                <?php endif; ?>
            </li>
            <?php endfor; ?>
            <li>
                <?php if ($result['page'] < $result['pages']): ?>
                <a href="<?= e(catalog_url($filters, ['pagina' => $result['page'] + 1])) ?>" rel="next">Próxima</a>
                <?php else: ?><span class="is-disabled">Próxima</span><?php endif; ?>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/views/partials/footer.php'; ?>
