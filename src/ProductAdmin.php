<?php
declare(strict_types=1);

/**
 * Operações do painel sobre produtos, categorias e pedidos.
 */
final class ProductAdmin
{
    public const FIELDS = [
        'name', 'slug', 'category_id', 'description', 'price', 'stock', 'case_material', 'movement_type', 'caliber',
        'power_reserve_h', 'diameter_mm', 'water_resistance_m', 'strap_type', 'dial', 'gender',
        'is_featured', 'is_hero', 'is_active',
    ];

    /** Lê e normaliza o formulário de produto. */
    public static function fromInput(array $post): array
    {
        $d = [];
        foreach (['name' => 120, 'slug' => 140, 'case_material' => 120, 'movement_type' => 80, 'caliber' => 40, 'strap_type' => 120, 'dial' => 80] as $key => $max) {
            $d[$key] = input($post, $key, $max);
        }
        $d['description'] = input($post, 'description', 5000);
        $d['category_id'] = int_param($post['category_id'] ?? null);
        $d['price'] = self::parseMoney(input($post, 'price', 20));
        $d['stock'] = self::parseInt(input($post, 'stock', 6));
        $d['power_reserve_h'] = input($post, 'power_reserve_h', 4) === '' ? null : self::parseInt(input($post, 'power_reserve_h', 4));
        $d['diameter_mm'] = self::parseDecimal(input($post, 'diameter_mm', 6));
        $d['water_resistance_m'] = self::parseInt(input($post, 'water_resistance_m', 5));
        $d['gender'] = input($post, 'gender', 10);
        foreach (['is_featured', 'is_hero', 'is_active'] as $flag) {
            $d[$flag] = !empty($post[$flag]) ? 1 : 0;
        }
        if ($d['slug'] === '' && $d['name'] !== '') {
            $d['slug'] = slugify($d['name']);
        } else {
            $d['slug'] = slugify($d['slug']);
        }
        return $d;
    }

    public static function validate(array $d, ?int $id = null): array
    {
        $e = [];
        if (mb_strlen($d['name']) < 2) {
            $e['name'] = 'Informe o nome da peça.';
        }
        if ($d['slug'] === '') {
            $e['slug'] = 'Informe um endereço (slug) válido.';
        } else {
            $stmt = db()->prepare('SELECT id FROM products WHERE slug = ? AND id <> ?');
            $stmt->execute([$d['slug'], $id ?? 0]);
            if ($stmt->fetch()) {
                $e['slug'] = 'Já existe uma peça com este endereço.';
            }
        }
        if (!$d['category_id'] || !CategoryRepository::find($d['category_id'])) {
            $e['category_id'] = 'Escolha a coleção.';
        }
        if (mb_strlen($d['description']) < 20) {
            $e['description'] = 'Escreva uma descrição com pelo menos 20 caracteres.';
        }
        if ($d['price'] === null || bccomp($d['price'], '0', 2) < 0 || bccomp($d['price'], '99999999.99', 2) > 0) {
            $e['price'] = 'Preço inválido. Exemplo: 14900,00';
        }
        if ($d['stock'] === null || $d['stock'] > 100000) {
            $e['stock'] = 'Estoque deve ser um número inteiro de 0 a 100000.';
        }
        if ($d['diameter_mm'] === null || (float) $d['diameter_mm'] < 20 || (float) $d['diameter_mm'] > 60) {
            $e['diameter_mm'] = 'Diâmetro entre 20 e 60 mm.';
        }
        if ($d['water_resistance_m'] === null || $d['water_resistance_m'] > 2000) {
            $e['water_resistance_m'] = 'Resistência em metros (0 a 2000).';
        }
        if ($d['power_reserve_h'] !== null && ($d['power_reserve_h'] < 1 || $d['power_reserve_h'] > 1000)) {
            $e['power_reserve_h'] = 'Reserva de marcha em horas (1 a 1000) ou vazio.';
        }
        foreach (['case_material' => 'Informe o material da caixa.', 'movement_type' => 'Informe o mecanismo.', 'strap_type' => 'Informe a pulseira.'] as $k => $msg) {
            if ($d[$k] === '') {
                $e[$k] = $msg;
            }
        }
        if (!in_array($d['gender'], ProductRepository::GENDERS, true)) {
            $e['gender'] = 'Escolha para quem é a peça.';
        }
        return $e;
    }

    public static function create(array $d, ?string $image): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($d['is_hero']) {
                $pdo->exec('UPDATE products SET is_hero = 0');
            }
            $stmt = $pdo->prepare('INSERT INTO products (category_id, name, slug, description, price, stock, image, case_material, movement_type, caliber, power_reserve_h, diameter_mm, water_resistance_m, strap_type, dial, gender, is_featured, is_hero, is_active)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $d['category_id'], $d['name'], $d['slug'], $d['description'], $d['price'], $d['stock'], $image,
                $d['case_material'], $d['movement_type'], $d['caliber'] ?: null, $d['power_reserve_h'], $d['diameter_mm'],
                $d['water_resistance_m'], $d['strap_type'], $d['dial'] ?: null, $d['gender'], $d['is_featured'], $d['is_hero'], $d['is_active'],
            ]);
            $id = (int) $pdo->lastInsertId();
            if ($image) {
                $img = $pdo->prepare('INSERT INTO product_images (product_id, path, alt, sort_order) VALUES (?, ?, ?, 0)');
                $img->execute([$id, $image, $d['name'] . ', vista frontal']);
            }
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $d): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($d['is_hero']) {
                $pdo->prepare('UPDATE products SET is_hero = 0 WHERE id <> ?')->execute([$id]);
            }
            $stmt = $pdo->prepare('UPDATE products SET category_id = ?, name = ?, slug = ?, description = ?, price = ?, stock = ?, case_material = ?, movement_type = ?, caliber = ?,
                                   power_reserve_h = ?, diameter_mm = ?, water_resistance_m = ?, strap_type = ?, dial = ?, gender = ?, is_featured = ?, is_hero = ?, is_active = ?
                                   WHERE id = ?');
            $stmt->execute([
                $d['category_id'], $d['name'], $d['slug'], $d['description'], $d['price'], $d['stock'], $d['case_material'], $d['movement_type'], $d['caliber'] ?: null,
                $d['power_reserve_h'], $d['diameter_mm'], $d['water_resistance_m'], $d['strap_type'], $d['dial'] ?: null, $d['gender'],
                $d['is_featured'], $d['is_hero'], $d['is_active'], $id,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Troca a foto principal; mantém a galeria coerente. */
    public static function replaceMainImage(int $id, string $newPath): void
    {
        $product = ProductRepository::find($id);
        $old = $product['image'] ?? null;
        db()->prepare('UPDATE products SET image = ? WHERE id = ?')->execute([$newPath, $id]);
        $stmt = db()->prepare('UPDATE product_images SET path = ? WHERE product_id = ? AND path = ?');
        $stmt->execute([$newPath, $id, (string) $old]);
        if ($stmt->rowCount() === 0) {
            db()->prepare('INSERT INTO product_images (product_id, path, alt, sort_order) VALUES (?, ?, ?, 0)')
                ->execute([$id, $newPath, ($product['name'] ?? 'Peça') . ', vista frontal']);
        }
        Upload::delete($old);
    }

    public static function addGalleryImage(int $id, string $path, string $alt): void
    {
        $next = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_images WHERE product_id = ?');
        $next->execute([$id]);
        db()->prepare('INSERT INTO product_images (product_id, path, alt, sort_order) VALUES (?, ?, ?, ?)')
            ->execute([$id, $path, $alt, (int) $next->fetchColumn()]);
    }

    public static function removeGalleryImage(int $productId, int $imageId): bool
    {
        $stmt = db()->prepare('SELECT path FROM product_images WHERE id = ? AND product_id = ?');
        $stmt->execute([$imageId, $productId]);
        $path = $stmt->fetchColumn();
        if ($path === false) {
            return false;
        }
        $product = ProductRepository::find($productId);
        if ($product && $product['image'] === $path) {
            return false; // a foto principal se troca pelo campo próprio
        }
        db()->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);
        Upload::delete((string) $path);
        return true;
    }

    public static function setActive(int $id, bool $active): void
    {
        db()->prepare('UPDATE products SET is_active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
    }

    public static function setStock(int $id, int $stock): void
    {
        db()->prepare('UPDATE products SET stock = ? WHERE id = ?')->execute([$stock, $id]);
    }

    public static function hasOrders(int $id): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM order_items WHERE product_id = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Exclui se nunca foi vendido. Se já tem pedidos, a FK (RESTRICT) impede:
     * a peça é desativada para preservar o histórico. Devolve 'deleted' ou 'deactivated'.
     */
    public static function delete(int $id): string
    {
        if (self::hasOrders($id)) {
            self::setActive($id, false);
            return 'deactivated';
        }
        $images = ProductRepository::images($id);
        $product = ProductRepository::find($id);
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]); // galeria sai por CASCADE
        foreach ($images as $img) {
            Upload::delete($img['path']);
        }
        Upload::delete($product['image'] ?? null);
        return 'deleted';
    }

    /** Lista do painel com filtros simples. */
    public static function listing(string $q, string $status): array
    {
        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.slug LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if ($status === 'ativos') {
            $where[] = 'p.is_active = 1';
        } elseif ($status === 'inativos') {
            $where[] = 'p.is_active = 0';
        } elseif ($status === 'baixo') {
            $where[] = 'p.stock <= 3';
        }
        $stmt = db()->prepare('SELECT p.*, c.name AS category_name,
                                      (SELECT COALESCE(SUM(i.quantity), 0) FROM order_items i WHERE i.product_id = p.id) AS sold
                               FROM products p JOIN categories c ON c.id = p.category_id
                               WHERE ' . implode(' AND ', $where) . ' ORDER BY p.is_active DESC, p.name');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** "14.900,50" | "14900,50" | "14900.50" | "14900" -> "14900.50" */
    public static function parseMoney(string $raw): ?string
    {
        $raw = str_replace(['R$', ' '], '', $raw);
        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $raw)) {
            return null;
        }
        return bcadd($raw, '0', 2);
    }

    private static function parseInt(string $raw): ?int
    {
        return preg_match('/^\d{1,6}$/', $raw) ? (int) $raw : null;
    }

    private static function parseDecimal(string $raw): ?string
    {
        $raw = str_replace(',', '.', $raw);
        return preg_match('/^\d{1,2}(\.\d)?$/', $raw) ? $raw : null;
    }
}
