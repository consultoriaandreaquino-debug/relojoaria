<?php
/**
 * Guarda do painel: incluída no topo de TODA página do /admin.
 * - visitante: vai para o login e volta depois
 * - cliente: 403
 * - qualquer POST no painel exige token CSRF válido
 */
require dirname(__DIR__) . '/config/app.php';

if (!Auth::check()) {
    flash('info', 'Entre com uma conta da equipe para acessar o painel.');
    redirect('conta/login.php', ['next' => $_SERVER['REQUEST_URI'] ?? '']);
}
if (!Auth::isAdmin()) {
    http_response_code(403);
    $title = 'Acesso restrito';
    $message = 'Esta área é só para a equipe da Morvane.';
    require BASE_PATH . '/views/error.php';
    exit;
}

header('Cache-Control: no-store');

if (is_post()) {
    Csrf::verify();
}
