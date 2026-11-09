<?php
require dirname(__DIR__) . '/config/app.php';

$next = safe_next(input($_GET, 'next', 300));
if (Auth::check()) {
    redirect($next !== '' ? $next : 'conta/pedidos.php');
}
$fromCheckout = str_contains($next, 'checkout.php');

$title = 'Entrar';
$bodyClass = 'page-light';
$headerVariant = 'light';
$scripts = ['forms.js'];
require BASE_PATH . '/views/partials/head.php';
require BASE_PATH . '/views/partials/header.php';
?>
<main id="conteudo" class="auth">
    <aside class="auth__aside" aria-hidden="true">
        <img src="<?= e(url('assets/img/editorial/macro-coroa.webp')) ?>" alt="" width="1200" height="1500">
        <div class="auth__aside-text">
            <p>Seus pedidos, suas revisões e a garantia de cada peça, num só lugar.</p>
        </div>
    </aside>
    <div class="auth__main">
        <form class="auth__form" method="post" action="<?= e(url('actions/auth.php')) ?>" novalidate data-validate>
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <h1 class="auth__title">Entrar</h1>
            <p class="auth__lead"><?= $fromCheckout ? 'Entre para finalizar sua compra. A sacola continua guardada.' : 'Use o e-mail e a senha do seu cadastro.' ?></p>

            <?php if ($err = field_error('login')): ?>
            <p class="notice" role="alert"><?= e($err) ?></p>
            <?php endif; ?>

            <div class="field<?= field_error('email') ? ' has-error' : '' ?>">
                <label class="field__label" for="email">E-mail</label>
                <input class="field__input" id="email" type="email" name="email" value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email" autofocus>
                <?php if ($err = field_error('email')): ?><p class="field__error"><?= e($err) ?></p><?php endif; ?>
            </div>
            <div class="field<?= field_error('password') ? ' has-error' : '' ?>">
                <label class="field__label" for="password">Senha</label>
                <input class="field__input" id="password" type="password" name="password" required autocomplete="current-password">
                <?php if ($err = field_error('password')): ?><p class="field__error"><?= e($err) ?></p><?php endif; ?>
            </div>
            <button type="submit" class="btn-solid w-100">Entrar</button>
            <p class="auth__switch">Primeira compra? <a href="<?= e(url('conta/cadastro.php', ['next' => $next])) ?>">Crie sua conta</a></p>

            <?php if (APP_DEBUG): ?>
            <div class="auth__demo">
                <strong>Ambiente de demonstração</strong><br>
                Cliente: cliente@morvane.com.br / Morvane@Cliente26<br>
                Admin: admin@morvane.com.br / Morvane@Admin26
            </div>
            <?php endif; ?>
        </form>
    </div>
</main>
<?php forget_form(); ?>
<?php require BASE_PATH . '/views/partials/footer.php'; ?>
