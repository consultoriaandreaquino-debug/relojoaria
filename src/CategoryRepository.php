<?php
declare(strict_types=1);

final class CategoryRepository
{
    /** Coleções com a contagem de peças ativas. */
    public static function all(): array
    {
        $sql = 'SELECT c.*, COUNT(p.id) AS product_count, MIN(p.price) AS min_price,
                       (SELECT p2.image FROM products p2 WHERE p2.category_id = c.id AND p2.is_active = 1
                        ORDER BY p2.is_featured DESC, p2.created_at DESC LIMIT 1) AS cover
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
                GROUP BY c.id
                ORDER BY c.sort_order, c.name';
        return db()->query($sql)->fetchAll();
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = db()->prepare('SELECT * FROM categories WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function special(): ?array
    {
        return db()->query('SELECT * FROM categories WHERE is_special = 1 ORDER BY sort_order LIMIT 1')->fetch() ?: null;
    }
}
