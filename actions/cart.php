<?php
/**
 * Ações da sacola: add, update, remove, clear.
 * Só POST, com CSRF. Responde JSON quando chamada por fetch(); senão redireciona (PRG).
 */
require dirname(__DIR__) . '/config/app.php';

require_post();
Csrf::verify();

$action = input($_POST, 'action', 20);
$productId = int_param($_POST['product_id'] ?? null);
$qtyRaw = $_POST['quantity'] ?? '1';
$qty = (is_string($qtyRaw) && preg_match('/^\d{1,3}$/', $qtyRaw)) ? (int) $qtyRaw : -1;
$back = safe_next(input($_POST, 'back', 300)) ?: url('sacola.php');

$ok = true;
$message = '';

try {
    switch ($action) {
        case 'add':
            if (!$productId || $qty < 1) {
                throw new DomainException('Escolha uma quantidade válida.');
            }
            $final = Cart::add($productId, $qty);
            $message = $qty === 1 ? 'Peça adicionada à sacola.' : "{$qty} unidades adicionadas à sacola.";
            break;
        case 'update':
            if (!$productId || $qty < 0) {
                throw new DomainException('Quantidade inválida.');
            }
            Cart::update($productId, $qty);
            $message = $qty === 0 ? 'Peça removida da sacola.' : 'Quantidade atualizada.';
            break;
        case 'remove':
            if (!$productId) {
                throw new DomainException('Item inválido.');
            }
            Cart::remove($productId);
            $message = 'Peça removida da sacola.';
            break;
        case 'clear':
            Cart::clear();
            $message = 'Sacola esvaziada.';
            break;
        default:
            throw new DomainException('Ação desconhecida.');
    }
} catch (DomainException $e) {
    $ok = false;
    $message = $e->getMessage();
}

if (wants_json()) {
    $cart = Cart::details();
    ob_start();
    partial('mini-cart', ['cart' => $cart]);
    $html = ob_get_clean();
    json_response([
        'ok' => $ok,
        'message' => $message,
        'count' => $cart['count'],
        'total' => money($cart['total']),
        'miniCart' => $html,
    ], $ok ? 200 : 422);
}

flash($ok ? 'success' : 'error', $message);
redirect($back);
