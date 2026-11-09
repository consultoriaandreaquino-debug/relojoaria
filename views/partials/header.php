<?php
/**
 * Cabeçalho da loja. Transparente sobre o hero (variant overlay), sólido nas demais páginas.
 */
$variant = $headerVariant ?? 'light';
$user = Auth::user();
$cartCount = Cart::count();
$navCollections = CategoryRepository::all();
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$currentGender = $_GET['genero'] ?? '';
?>
<header class="site-header site-header--<?= e($variant) ?>" data-header data-variant="<?= e($variant) ?>">
    <div class="site-header__bar">
        <div class="site-header__left">
            <button class="icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#menuMobile" aria-controls="menuMobile" aria-label="Abrir menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8h18M3 16h18"/></svg>
            </button>
            <nav class="primary-nav d-none d-lg-flex" aria-label="Principal">
                <div class="nav-collections">
                    <button class="primary-nav__link" type="button" aria-expanded="false" aria-controls="painelColecoes" data-collections-toggle>Coleções</button>
                    <div class="collections-panel" id="painelColecoes" hidden data-collections-panel>
                        <div class="collections-panel__inner">
                            <ul class="collections-panel__list">
                                <?php foreach ($navCollections as $c): ?>
                                <li>
                                    <a href="<?= e(url('catalogo.php', ['colecao' => $c['slug']])) ?>">
                                        <span class="collections-panel__name"><?= e($c['name']) ?></span>
                                        <span class="collections-panel__count"><?= (int) $c['product_count'] ?> peças</span>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <a class="link-arrow" href="<?= e(url('catalogo.php')) ?>">Ver todas as peças</a>
                        </div>
                    </div>
                </div>
                <a class="primary-nav__link<?= $currentGender === 'masculino' ? ' is-current' : '' ?>" href="<?= e(url('catalogo.php', ['genero' => 'masculino'])) ?>">Masculino</a>
                <a class="primary-nav__link<?= $currentGender === 'feminino' ? ' is-current' : '' ?>" href="<?= e(url('catalogo.php', ['genero' => 'feminino'])) ?>">Feminino</a>
                <a class="primary-nav__link" href="<?= e(url('catalogo.php', ['ordem' => 'novidades'])) ?>">Novidades</a>
            </nav>
        </div>

        <a class="site-header__brand" href="<?= e(url('')) ?>" aria-label="Morvane, página inicial">
            <?php partial('logo'); ?>
        </a>

        <div class="site-header__actions">
            <button class="icon-btn" type="button" data-search-open aria-label="Pesquisar" aria-controls="busca">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5 21 21"/></svg>
            </button>
            <?php if ($user): ?>
            <div class="dropdown d-none d-md-block">
                <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Minha conta">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8.5" r="4"/><path d="M4 21c1.2-4 4.3-6 8-6s6.8 2 8 6"/></svg>
                </button>
                <div class="dropdown-menu dropdown-menu-end account-menu">
                    <p class="account-menu__hello">Olá, <?= e(strtok($user['name'], ' ')) ?></p>
                    <a class="dropdown-item" href="<?= e(url('conta/pedidos.php')) ?>">Meus pedidos</a>
                    <?php if ($user['role'] === 'admin'): ?>
                    <a class="dropdown-item" href="<?= e(url('admin/')) ?>">Painel administrativo</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('conta/logout.php')) ?>">
                        <?= Csrf::field() ?>
                        <button class="dropdown-item" type="submit">Sair</button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <a class="icon-btn d-none d-md-inline-flex" href="<?= e(url('conta/login.php')) ?>" aria-label="Entrar na conta">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8.5" r="4"/><path d="M4 21c1.2-4 4.3-6 8-6s6.8 2 8 6"/></svg>
            </a>
            <?php endif; ?>
            <button class="icon-btn bag-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#miniSacola" aria-controls="miniSacola" aria-label="Sacola, <?= $cartCount ?> itens">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1 13H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                <span class="bag-btn__count" data-cart-count <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span>
            </button>
        </div>
    </div>
</header>

<!-- Menu mobile -->
<div class="offcanvas offcanvas-start mobile-menu" tabindex="-1" id="menuMobile" aria-labelledby="menuMobileTitulo">
    <div class="offcanvas-header">
        <p class="visually-hidden" id="menuMobileTitulo">Menu</p>
        <?php partial('logo'); ?>
        <button type="button" class="icon-btn" data-bs-dismiss="offcanvas" aria-label="Fechar menu">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg>
        </button>
    </div>
    <div class="offcanvas-body">
        <p class="eyebrow">Coleções</p>
        <ul class="mobile-menu__list">
            <?php foreach ($navCollections as $c): ?>
            <li><a href="<?= e(url('catalogo.php', ['colecao' => $c['slug']])) ?>"><?= e($c['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <ul class="mobile-menu__list mobile-menu__list--small">
            <li><a href="<?= e(url('catalogo.php', ['genero' => 'masculino'])) ?>">Masculino</a></li>
            <li><a href="<?= e(url('catalogo.php', ['genero' => 'feminino'])) ?>">Feminino</a></li>
            <li><a href="<?= e(url('catalogo.php', ['ordem' => 'novidades'])) ?>">Novidades</a></li>
            <li><a href="<?= e(url('sacola.php')) ?>">Sacola</a></li>
        </ul>
        <div class="mobile-menu__account">
            <?php if ($user): ?>
                <a href="<?= e(url('conta/pedidos.php')) ?>">Meus pedidos</a>
                <?php if ($user['role'] === 'admin'): ?><a href="<?= e(url('admin/')) ?>">Painel</a><?php endif; ?>
                <form method="post" action="<?= e(url('conta/logout.php')) ?>"><?= Csrf::field() ?><button type="submit" class="link-plain">Sair</button></form>
            <?php else: ?>
                <a href="<?= e(url('conta/login.php')) ?>">Entrar</a>
                <a href="<?= e(url('conta/cadastro.php')) ?>">Criar conta</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Pesquisa em tela cheia -->
<div class="search-overlay" id="busca" role="dialog" aria-modal="true" aria-labelledby="buscaTitulo" hidden data-search>
    <div class="search-overlay__inner">
        <button type="button" class="icon-btn search-overlay__close" data-search-close aria-label="Fechar pesquisa">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg>
        </button>
        <form action="<?= e(url('catalogo.php')) ?>" method="get" role="search">
            <label class="search-overlay__label" id="buscaTitulo" for="buscaCampo">O que você procura?</label>
            <div class="search-overlay__field">
                <input id="buscaCampo" type="search" name="q" maxlength="80" placeholder="Nome, coleção ou material" autocomplete="off">
                <button type="submit" class="btn-line">Pesquisar</button>
            </div>
            <p class="search-overlay__hint">Tente: <a href="<?= e(url('catalogo.php', ['q' => 'cronógrafo'])) ?>">cronógrafo</a>, <a href="<?= e(url('catalogo.php', ['q' => 'ouro'])) ?>">ouro</a>, <a href="<?= e(url('catalogo.php', ['q' => 'mergulho'])) ?>">mergulho</a></p>
        </form>
    </div>
</div>

<!-- Mini-sacola -->
<div class="offcanvas offcanvas-end mini-bag" tabindex="-1" id="miniSacola" aria-labelledby="miniSacolaTitulo">
    <div class="offcanvas-header">
        <h2 class="mini-bag__title" id="miniSacolaTitulo">Sacola</h2>
        <button type="button" class="icon-btn" data-bs-dismiss="offcanvas" aria-label="Fechar sacola">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg>
        </button>
    </div>
    <div class="offcanvas-body" data-mini-cart>
        <?php partial('mini-cart', ['cart' => Cart::details()]); ?>
    </div>
</div>

<div class="toast-region" aria-live="polite" aria-atomic="true" data-toasts></div>

<?php partial('flash'); ?>
