<?php
declare(strict_types=1);

/**
 * Consultas de produtos da vitrine e do painel. Sempre com prepared statements.
 */
final class ProductRepository
{
    public const PER_PAGE = 9;

    /** Ordenações permitidas (whitelist): chave da URL => trecho SQL fixo. */
    public const SORTS = [
        'novidades'  => ['label' => 'Novidades',        'sql' => 'p.created_at DESC, p.id DESC'],
        'menor-preco' => ['label' => 'Menor preço',     'sql' => 'p.price ASC, p.id ASC'],
        'maior-preco' => ['label' => 'Maior preço',     'sql' => 'p.price DESC, p.id DESC'],
        'nome'       => ['label' => 'Nome (A–Z)',       'sql' => 'p.name ASC'],
    ];

    public const GENDERS = ['masculino', 'feminino', 'unissex'];

    private const SELECT = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug, c.is_special
                            FROM products p
                            JOIN categories c ON c.id = p.category_id';

    /**
     * Busca paginada do catálogo.
     * @return array{items: array, total: int, pages: int, page: int}
     */
    public static function search(array $filters, int $page = 1): array
    {
        [$where, $params] = self::buildWhere($filters);
        $sortKey = array_key_exists($filters['ordem'] ?? '', self::SORTS) ? $filters['ordem'] : 'novidades';
        $orderBy = self::SORTS[$sortKey]['sql'];

        $count = db()->prepare('SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id WHERE ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * self::PER_PAGE;

        $sql = self::SELECT . ' WHERE ' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ? OFFSET ?';
        $stmt = db()->prepare($sql);
        $i = 1;
        foreach ($params as $value) {
            $stmt->bindValue($i++, $value);
        }
        $stmt->bindValue($i++, self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    private static function buildWhere(array $f): array
    {
        $where = ['p.is_active = 1'];
        $params = [];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.case_material LIKE ? OR p.movement_type LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($f['colecao'])) {
            $where[] = 'c.slug = ?';
            $params[] = $f['colecao'];
        }
        if (!empty($f['genero']) && in_array($f['genero'], self::GENDERS, true)) {
            // unissex aparece nos filtros masculino e feminino
            if ($f['genero'] === 'unissex') {
                $where[] = 'p.gender = ?';
                $params[] = 'unissex';
            } else {
                $where[] = 'p.gender IN (?, ?)';
                array_push($params, $f['genero'], 'unissex');
            }
        }
        if (!empty($f['disponivel'])) {
            $where[] = 'p.stock > 0';
        }
        return [implode(' AND ', $where), $params];
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = db()->prepare(self::SELECT . ' WHERE p.slug = ? AND p.is_active = 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(self::SELECT . ' WHERE p.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Vários produtos ativos por id (usado pelo carrinho). */
    public static function findManyActive(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(self::SELECT . " WHERE p.id IN ($marks) AND p.is_active = 1");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['id']] = $row;
        }
        return $out;
    }

    public static function images(int $productId): array
    {
        $stmt = db()->prepare('SELECT id, path, alt, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    }

    public static function featured(int $limit = 6): array
    {
        $stmt = db()->prepare(self::SELECT . ' WHERE p.is_active = 1 AND p.is_featured = 1 ORDER BY p.created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Peça do topo da home: a marcada como hero; se não houver, o destaque mais recente. */
    public static function hero(): ?array
    {
        $stmt = db()->query(self::SELECT . ' WHERE p.is_active = 1 ORDER BY p.is_hero DESC, p.is_featured DESC, p.created_at DESC LIMIT 1');
        return $stmt->fetch() ?: null;
    }

    public static function related(array $product, int $limit = 3): array
    {
        $stmt = db()->prepare(self::SELECT . ' WHERE p.is_active = 1 AND p.category_id = ? AND p.id <> ? ORDER BY p.is_featured DESC, p.created_at DESC LIMIT ?');
        $stmt->bindValue(1, (int) $product['category_id'], PDO::PARAM_INT);
        $stmt->bindValue(2, (int) $product['id'], PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
        if (count($items) < $limit) {
            // completa com outras peças para não deixar a seção vazia
            $ids = array_merge([(int) $product['id']], array_map(static fn($p) => (int) $p['id'], $items));
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $more = db()->prepare(self::SELECT . " WHERE p.is_active = 1 AND p.id NOT IN ($marks) ORDER BY p.is_featured DESC, p.created_at DESC LIMIT ?");
            $ids[] = $limit - count($items);
            foreach ($ids as $i => $value) {
                $more->bindValue($i + 1, $value, PDO::PARAM_INT);
            }
            $more->execute();
            $items = array_merge($items, $more->fetchAll());
        }
        return $items;
    }

    /** Peças de uma coleção (campanha da home). */
    public static function byCategory(int $categoryId, int $limit = 4): array
    {
        $stmt = db()->prepare(self::SELECT . ' WHERE p.is_active = 1 AND p.category_id = ? ORDER BY p.is_featured DESC, p.price DESC LIMIT ?');
        $stmt->bindValue(1, $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Miniatura gerada ao lado da imagem principal, se existir. */
    public static function thumb(?string $path): string
    {
        if ($path && preg_match('#^(assets/img/products/[a-z0-9\-]+)\.webp$#', $path, $m) && is_file(BASE_PATH . '/' . $m[1] . '-thumb.webp')) {
            return $m[1] . '-thumb.webp';
        }
        return (string) $path;
    }

    /** Recorte transparente para o hero, se existir. */
    public static function heroImage(array $product): ?string
    {
        $path = 'assets/img/products/' . $product['slug'] . '-hero.webp';
        return is_file(BASE_PATH . '/' . $path) ? $path : null;
    }
}
