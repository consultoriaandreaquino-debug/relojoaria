<?php
/**
 * Login e cadastro. Só POST, com CSRF e validação no servidor.
 */
require dirname(__DIR__) . '/config/app.php';

require_post();
Csrf::verify();

$action = input($_POST, 'action', 20);
$next = safe_next(input($_POST, 'next', 300));

if ($action === 'login') {
    $email = mb_strtolower(input($_POST, 'email', 190));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    // Limite simples de tentativas por sessão: 5 erros bloqueiam por 2 minutos
    $lock = $_SESSION['login_lock_until'] ?? 0;
    if ($lock > time()) {
        remember_form(['email' => $email], ['login' => 'Muitas tentativas. Aguarde ' . ($lock - time()) . ' segundos e tente de novo.']);
        redirect('conta/login.php', ['next' => $next]);
    }

    $errors = [];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Informe um e-mail válido.';
    }
    if ($password === '') {
        $errors['password'] = 'Informe sua senha.';
    }
    if (!$errors && !Auth::attempt($email, $password)) {
        $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
        if ($_SESSION['login_fails'] >= 5) {
            $_SESSION['login_lock_until'] = time() + 120;
            $_SESSION['login_fails'] = 0;
        }
        $errors['login'] = 'E-mail ou senha não conferem.';
    }
    if ($errors) {
        remember_form(['email' => $email], $errors);
        redirect('conta/login.php', ['next' => $next]);
    }

    unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
    $user = Auth::user();
    flash('success', 'Olá, ' . strtok($user['name'], ' ') . '. Que bom ter você de volta.');
    if ($next !== '') {
        redirect($next);
    }
    redirect($user['role'] === 'admin' ? 'admin/' : '');
}

if ($action === 'register') {
    $data = [
        'name' => input($_POST, 'name', 120),
        'email' => mb_strtolower(input($_POST, 'email', 190)),
        'password' => is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
        'password_confirm' => is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '',
    ];
    $errors = Auth::validateRegistration($data);
    if ($errors) {
        remember_form($data, $errors);
        redirect('conta/cadastro.php', ['next' => $next]);
    }
    try {
        $id = Auth::register($data['name'], $data['email'], $data['password']);
    } catch (PDOException $e) {
        // corrida rara: o mesmo e-mail cadastrado entre a validação e o insert (UNIQUE no banco)
        if ($e->getCode() === '23000') {
            remember_form($data, ['email' => 'Este e-mail já tem cadastro. Entre com sua senha.']);
            redirect('conta/cadastro.php', ['next' => $next]);
        }
        throw $e;
    }
    Auth::login($id);
    flash('success', 'Conta criada. Bem-vindo à Morvane, ' . strtok($data['name'], ' ') . '.');
    redirect($next !== '' ? $next : 'conta/pedidos.php');
}

http_response_code(400);
exit('Ação inválida.');
