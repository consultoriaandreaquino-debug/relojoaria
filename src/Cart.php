<?php
declare(strict_types=1);

/**
 * Sacola em $_SESSION['cart'] = [product_id => quantidade].
 * Preços sempre lidos do banco; estoque revalidado em toda operação.
 */
final class Cart
{
    public const MAX_PER_ITEM = 10;

    private static function &items(): array
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return $_SESSION['cart'];
    }

    public static function raw(): array
    {
        return self::items();
    }

    public static function count(): int
    {
        return array_sum(self::items());
    }

    public static function isEmpty(): bool
    {
        return self::count() === 0;
    }

    /**
     * Adiciona unidades. Lança DomainException com mensagem para o cliente se não houver estoque.
     * @return int quantidade final do item na sacola
     */
    public static function add(int $productId, int $qty): int
    {
        if ($qty < 1) {
            throw new DomainException('Quantidade inválida.');
        }
        $product = self::product($productId);
        $items = &self::items();
        $current = $items[$productId] ?? 0;
        $wanted = $current + $qty;
        $limit = min((int) $product['stock'], self::MAX_PER_ITEM);

        if ((int) $product['stock'] <= 0) {
            throw new DomainException('O ' . $product['name'] . ' está esgotado no momento.');
        }
        if ($wanted > $limit) {
            $msg = $current > 0
                ? "Você já tem {$current} na sacola. Temos {$product['stock']} em estoque."
                : "Temos só {$product['stock']} em estoque.";
            if ($limit === self::MAX_PER_ITEM && (int) $product['stock'] > self::MAX_PER_ITEM) {
                $msg = 'O limite é de ' . self::MAX_PER_ITEM . ' unidades por peça.';
            }
            throw new DomainException($msg);
        }
        $items[$productId] = $wanted;
        return $wanted;
    }

    /** Define a quantidade exata (0 remove). */
    public static function update(int $productId, int $qty): void
    {
        $items = &self::items();
        if (!isset($items[$productId])) {
            throw new DomainException('Este item não está mais na sacola.');
        }
        if ($qty <= 0) {
            unset($items[$productId]);
            return;
        }
        $product = self::product($productId);
        $limit = min((int) $product['stock'], self::MAX_PER_ITEM);
        if ($qty > $limit) {
            throw new DomainException((int) $product['stock'] <= 0
                ? 'O ' . $product['name'] . ' esgotou.'
                : "Temos só {$limit} unidade(s) do {$product['name']}.");
        }
        $items[$productId] = $qty;
    }

    public static function remove(int $productId): void
    {
        $items = &self::items();
        unset($items[$productId]);
    }

    public static function clear(): void
    {
        $_SESSION['cart'] = [];
    }

    /**
     * Itens com dados atuais do banco, subtotais e total calculados no servidor.
     * Produtos desativados saem da sacola; quantidades acima do estoque são ajustadas e avisadas.
     */
    public static function details(): array
    {
        $items = &self::items();
        $products = ProductRepository::findManyActive(array_keys($items));
        $lines = [];
        $notices = [];
        $total = '0.00';

        foreach ($items as $id => $qty) {
            $product = $products[$id] ?? null;
            if (!$product) {
                unset($items[$id]);
                $notices[] = 'Uma peça saiu do catálogo e foi removida da sacola.';
                continue;
            }
            $stock = (int) $product['stock'];
            if ($stock <= 0) {
                unset($items[$id]);
                $notices[] = "O {$product['name']} esgotou e foi removido da sacola.";
                continue;
            }
            if ($qty > $stock) {
                $items[$id] = $qty = $stock;
                $notices[] = "Ajustamos o {$product['name']} para {$stock} unidade(s), o que temos em estoque.";
            }
            $subtotal = bcmul((string) $product['price'], (string) $qty, 2);
            $total = bcadd($total, $subtotal, 2);
            $lines[] = [
                'product' => $product,
                'quantity' => (int) $qty,
                'unit_price' => (string) $product['price'],
                'subtotal' => $subtotal,
                'max' => min($stock, self::MAX_PER_ITEM),
            ];
        }

        return [
            'lines' => $lines,
            'count' => array_sum(array_column($lines, 'quantity')),
            'total' => $total,
            'notices' => $notices,
        ];
    }

    private static function product(int $productId): array
    {
        $products = ProductRepository::findManyActive([$productId]);
        if (!isset($products[$productId])) {
            throw new DomainException('Esta peça não está disponível.');
        }
        return $products[$productId];
    }
}
