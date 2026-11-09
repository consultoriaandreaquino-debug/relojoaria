<?php
require dirname(__DIR__) . '/config/app.php';

$next = safe_next(input($_GET, 'next', 300));
if (Auth::check()) {
    redirect($next !== '' ? $next : 'conta/pedidos.php');
}

$title = 'Criar conta';
$bodyClass = 'page-light';
$headerVariant = 'light';
$scripts = ['forms.js'];
require BASE_PATH . '/views/partials/head.php';
require BASE_PATH . '/views/partials/header.php';

/** Campo de formulário com erro do servidor. */
$field = static function (string $name, string $label, string $type = 'text', array $attrs = []) {
    $error = field_error($name);
    $value = in_array($type, ['password'], true) ? '' : old($name);
    $extra = '';
    foreach ($attrs as $k => $v) {
        $extra .= ' ' . $k . '="' . e($v) . '"';
    }
    echo '<div class="field' . ($error ? ' has-error' : '') . '">';
    echo '<label class="field__label" for="' . e($name) . '">' . e($label) . '</label>';
    echo '<input class="field__input" id="' . e($name) . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $extra . '>';
    if (!empty($attrs['data-hint'])) {
        echo '<p class="field__hint">' . e($attrs['data-hint']) . '</p>';
    }
    if ($error) {
        echo '<p class="field__error">' . e($error) . '</p>';
    }
    echo '</div>';
};
?>
<main id="conteudo" class="auth">
    <aside class="auth__aside" aria-hidden="true">
        <img src="<?= e(url('assets/img/editorial/macro-mostrador.webp')) ?>" alt="" width="1200" height="1500">
        <div class="auth__aside-text">
            <p>Com a conta, você acompanha pedidos e guarda o histórico de cada peça.</p>
        </div>
    </aside>
    <div class="auth__main">
        <form class="auth__form" method="post" action="<?= e(url('actions/auth.php')) ?>" novalidate data-validate>
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <h1 class="auth__title">Criar conta</h1>
            <p class="auth__lead">Leva um minuto. Pedimos só o necessário.</p>

            <?php
            $field('name', 'Nome completo', 'text', ['required' => 'required', 'minlength' => '3', 'maxlength' => '120', 'autocomplete' => 'name']);
            $field('email', 'E-mail', 'email', ['required' => 'required', 'maxlength' => '190', 'autocomplete' => 'email']);
            $field('password', 'Senha', 'password', ['required' => 'required', 'minlength' => '8', 'maxlength' => '72', 'autocomplete' => 'new-password', 'pattern' => '(?=.*[A-Za-z])(?=.*\d).{8,}', 'data-hint' => 'Pelo menos 8 caracteres, com letras e números.']);
            $field('password_confirm', 'Confirme a senha', 'password', ['required' => 'required', 'autocomplete' => 'new-password', 'data-match' => 'password']);
            ?>
            <button type="submit" class="btn-solid w-100">Criar conta</button>
            <p class="auth__switch">Já tem cadastro? <a href="<?= e(url('conta/login.php', ['next' => $next])) ?>">Entrar</a></p>
        </form>
    </div>
</main>
<?php forget_form(); ?>
<?php require BASE_PATH . '/views/partials/footer.php'; ?>
