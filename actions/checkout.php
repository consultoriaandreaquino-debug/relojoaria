<?php
/**
 * Finaliza o pedido. Só POST, CSRF, usuário autenticado.
 * A transação (estoque com FOR UPDATE, pedido, itens, baixa) fica em OrderService::place().
 */
require dirname(__DIR__) . '/config/app.php';

require_post();
Csrf::verify();
Auth::requireLogin(url('checkout.php'));

$in = [
    'shipping_name' => input($_POST, 'shipping_name', 120),
    'shipping_zip' => input($_POST, 'shipping_zip', 9),
    'shipping_address' => input($_POST, 'shipping_address', 255),
    'shipping_city' => input($_POST, 'shipping_city', 100),
    'shipping_state' => strtoupper(input($_POST, 'shipping_state', 2)),
    'payment_method' => input($_POST, 'payment_method', 10),
    'card_number' => input($_POST, 'card_number', 23),
    'card_name' => input($_POST, 'card_name', 80),
    'card_expiry' => input($_POST, 'card_expiry', 5),
    'card_cvv' => input($_POST, 'card_cvv', 4),
];

$errors = OrderService::validateCheckout($in);
if ($errors) {
    remember_form($in, $errors);
    redirect('checkout.php');
}

$cart = Cart::raw();
if (!$cart) {
    flash('info', 'Sua sacola está vazia.');
    redirect('catalogo.php');
}

try {
    $orderId = OrderService::place((int) Auth::id(), $cart, $in);
} catch (DomainException $e) {
    // nada foi gravado (rollBack); a sacola continua como estava
    remember_form($in, ['checkout' => $e->getMessage()]);
    redirect('checkout.php');
}

Cart::clear();
$_SESSION['last_order'] = $orderId;
redirect('pedido.php', ['id' => $orderId]);
