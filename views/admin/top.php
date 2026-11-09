<?php
/** Abertura das páginas do painel. Variáveis: $title, $active */
$isAdmin = true;
$bodyClass = 'page-admin';
require BASE_PATH . '/views/partials/head.php';
$admin = Auth::user();
$nav = [
    'index' => ['Visão geral', 'admin/'],
    'produtos' => ['Produtos', 'admin/produtos.php'],
    'categorias' => ['Coleções', 'admin/categorias.php'],
    'pedidos' => ['Pedidos', 'admin/pedidos.php'],
    'clientes' => ['Clientes', 'admin/clientes.php'],
];
?>
<div class="admin">
    <aside class="admin__side" aria-label="Menu do painel">
        <a class="admin__brand" href="<?= e(url('admin/')) ?>"><?php partial('logo'); ?><span>Painel</span></a>
        <nav>
            <ul class="admin__nav">
                <?php foreach ($nav as $key => [$label, $href]): ?>
                <li><a href="<?= e(url($href)) ?>"<?= ($active ?? '') === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="admin__user">
            <p><?= e($admin['name']) ?><small><?= e($admin['email']) ?></small></p>
            <a href="<?= e(url('')) ?>">Ver a loja</a>
            <form method="post" action="<?= e(url('conta/logout.php')) ?>"><?= Csrf::field() ?><button type="submit" class="link-plain">Sair</button></form>
        </div>
    </aside>
    <main class="admin__main" id="conteudo">
        <?php partial('flash'); ?>
