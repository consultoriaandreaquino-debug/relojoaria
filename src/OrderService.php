<?php
declare(strict_types=1);

/**
 * Finalização de pedidos em transação e consultas de pedidos.
 */
final class OrderService
{
    public const METHODS = ['pix', 'boleto', 'cartao'];
    public const STATUSES = ['pago', 'em_separacao', 'enviado', 'entregue', 'cancelado'];
    public const STATES = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

    /** Valida endereço e pagamento simulado. Nunca guarda número de cartão. */
    public static function validateCheckout(array $in): array
    {
        $errors = [];
        if (mb_strlen($in['shipping_name']) < 3) {
            $errors['shipping_name'] = 'Informe o nome de quem vai receber.';
        }
        if (!preg_match('/^\d{5}-?\d{3}$/', $in['shipping_zip'])) {
            $errors['shipping_zip'] = 'CEP no formato 00000-000.';
        }
        if (mb_strlen($in['shipping_address']) < 6) {
            $errors['shipping_address'] = 'Informe rua, número e complemento.';
        }
        if (mb_strlen($in['shipping_city']) < 2) {
            $errors['shipping_city'] = 'Informe a cidade.';
        }
        if (!in_array($in['shipping_state'], self::STATES, true)) {
            $errors['shipping_state'] = 'Escolha o estado.';
        }
        if (!in_array($in['payment_method'], self::METHODS, true)) {
            $errors['payment_method'] = 'Escolha uma forma de pagamento.';
        }
        if ($in['payment_method'] === 'cartao') {
            $digits = preg_replace('/\D/', '', $in['card_number']);
            if (strlen($digits) < 13 || strlen($digits) > 19 || !self::luhn($digits)) {
                $errors['card_number'] = 'Número de cartão inválido. Use o cartão de teste 4242 4242 4242 4242.';
            }
            if (mb_strlen($in['card_name']) < 3) {
                $errors['card_name'] = 'Nome como está no cartão.';
            }
            if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $in['card_expiry'], $m) || self::expired((int) $m[1], (int) $m[2])) {
                $errors['card_expiry'] = 'Validade no formato MM/AA, ainda não vencida.';
            }
            if (!preg_match('/^\d{3,4}$/', $in['card_cvv'])) {
                $errors['card_cvv'] = 'CVV com 3 ou 4 dígitos.';
            }
        }
        return $errors;
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }
        return $sum % 10 === 0;
    }

    private static function expired(int $month, int $year): bool
    {
        $year += 2000;
        return $year < (int) date('Y') || ($year === (int) date('Y') && $month < (int) date('n'));
    }

    /**
     * Cria o pedido em uma transação:
     * trava os produtos (FOR UPDATE), revalida estoque e preço, grava pedido e itens
     * com preço congelado, baixa o estoque e confirma. Em qualquer falha, desfaz tudo.
     *
     * @param array<int,int> $cart product_id => quantidade
     * @throws DomainException com mensagem para o cliente
     */
    public static function place(int $userId, array $cart, array $checkout): int
    {
        if (!$cart) {
            throw new DomainException('Sua sacola está vazia.');
        }
        ksort($cart); // ordem fixa de travamento evita deadlock entre pedidos simultâneos
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $ids = array_keys($cart);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, name, price, stock, is_active FROM products WHERE id IN ($marks) ORDER BY id FOR UPDATE");
            $stmt->execute($ids);
            $products = [];
            foreach ($stmt->fetchAll() as $row) {
                $products[(int) $row['id']] = $row;
            }

            $total = '0.00';
            $lines = [];
            foreach ($cart as $productId => $qty) {
                $p = $products[$productId] ?? null;
                if (!$p || !(int) $p['is_active']) {
                    throw new DomainException('Uma das peças da sacola não está mais disponível.');
                }
                if ($qty < 1 || (int) $p['stock'] < $qty) {
                    throw new DomainException(sprintf(
                        'Enquanto você finalizava, o estoque do %s mudou: temos %d unidade(s). Ajuste a sacola e tente de novo.',
                        $p['name'],
                        (int) $p['stock']
                    ));
                }
                $subtotal = bcmul((string) $p['price'], (string) $qty, 2);
                $total = bcadd($total, $subtotal, 2);
                $lines[] = [$productId, $p['name'], $qty, (string) $p['price'], $subtotal];
            }

            $last4 = null;
            if ($checkout['payment_method'] === 'cartao') {
                $last4 = substr(preg_replace('/\D/', '', $checkout['card_number']), -4);
            }

            $order = $pdo->prepare('INSERT INTO orders (user_id, payment_method, card_last4, status, total, shipping_name, shipping_zip, shipping_address, shipping_city, shipping_state)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $order->execute([
                $userId,
                $checkout['payment_method'],
                $last4,
                'pago',
                $total,
                $checkout['shipping_name'],
                self::formatZip($checkout['shipping_zip']),
                $checkout['shipping_address'],
                $checkout['shipping_city'],
                $checkout['shipping_state'],
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $item = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
            $stock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($lines as [$productId, $name, $qty, $price, $subtotal]) {
                $item->execute([$orderId, $productId, $name, $qty, $price, $subtotal]);
                $stock->execute([$qty, $productId, $qty]);
                if ($stock->rowCount() !== 1) {
                    throw new DomainException('Não foi possível reservar o estoque. Tente de novo.');
                }
            }

            self::failpoint();
            $pdo->commit();
            return $orderId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof DomainException) {
                throw $e;
            }
            error_log('[morvane] falha no checkout: ' . $e->getMessage());
            throw new DomainException('Não conseguimos concluir o pedido agora. Nada foi cobrado e sua sacola continua salva. Tente de novo em instantes.');
        }
    }

    /**
     * Ponto de falha para o teste de rollback: só age com APP_DEBUG=true
     * e a variável CHECKOUT_FAILPOINT=1 no .env. Em produção, não faz nada.
     */
    private static function failpoint(): void
    {
        if (APP_DEBUG && env_bool('CHECKOUT_FAILPOINT')) {
            throw new RuntimeException('Falha simulada antes do commit (CHECKOUT_FAILPOINT).');
        }
    }

    private static function formatZip(string $zip): string
    {
        $d = preg_replace('/\D/', '', $zip);
        return substr($d, 0, 5) . '-' . substr($d, 5, 3);
    }

    public static function forUser(int $userId): array
    {
        $stmt = db()->prepare('SELECT o.*, COUNT(i.id) AS item_count, SUM(i.quantity) AS units
                               FROM orders o JOIN order_items i ON i.order_id = o.id
                               WHERE o.user_id = ? GROUP BY o.id ORDER BY o.created_at DESC, o.id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function find(int $orderId): ?array
    {
        $stmt = db()->prepare('SELECT o.*, u.name AS customer_name, u.email AS customer_email
                               FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
        $stmt->execute([$orderId]);
        return $stmt->fetch() ?: null;
    }

    public static function items(int $orderId): array
    {
        $stmt = db()->prepare('SELECT i.*, p.slug, p.image FROM order_items i
                               JOIN products p ON p.id = i.product_id WHERE i.order_id = ? ORDER BY i.id');
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }
}
