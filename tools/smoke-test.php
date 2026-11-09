<?php
/**
 * Teste de fumaça de ponta a ponta contra o servidor real (Apache do XAMPP).
 *
 * Uso:   C:\xampp\php\php.exe tools\smoke-test.php [http://localhost/relojoaria]
 * Antes: importe database/schema.sql (o teste cria pedidos e altera estoque).
 * Depois: importe o schema de novo para voltar aos dados de demonstração.
 *
 * Só roda pela linha de comando; a pasta tools/ é bloqueada no Apache.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Somente pela linha de comando.\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__) . '/config/env.php';
load_env(dirname(__DIR__) . '/.env');
$base = rtrim($argv[1] ?? (string) env('APP_URL'), '/');
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('DB_HOST'), env('DB_PORT'), env('DB_NAME')), env('DB_USER'), env('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$q = static fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); })();

$pass = 0;
$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? "  ok    " : "  FALHA ") . $label . ($detail !== '' ? "  ($detail)" : '') . "\n";
}

/** Cliente HTTP com cookie próprio (uma "pessoa" por instância). */
final class Browser
{
    private string $jar;
    public int $status = 0;
    public string $location = '';
    public string $body = '';

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'mvjar');
    }

    public function get(string $path): string
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $data, array $headers = []): string
    {
        return $this->request('POST', $path, $data, $headers);
    }

    public function token(string $path = 'catalogo.php'): string
    {
        $html = $this->get($path);
        preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $html, $m);
        return $m[1] ?? '';
    }

    public function flash(string $path): string
    {
        $html = $this->get($path);
        preg_match_all('/class="flash flash--(\w+)">\s*<span>([^<]*)/', $html, $m, PREG_SET_ORDER);
        return implode(' | ', array_map(static fn($x) => $x[1] . ': ' . html_entity_decode($x[2]), $m));
    }

    private function request(string $method, string $path, array $data = [], array $headers = []): string
    {
        $ch = curl_init($this->base . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $raw = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $headerSize);
        $this->body = substr($raw, $headerSize);
        $this->location = preg_match('/^Location:\s*(\S+)/mi', $head, $m) ? $m[1] : '';
        return $this->body;
    }
}

echo "Morvane · teste de fumaça em $base\n\n";

echo "Proteções do servidor\n";
$v = new Browser($base);
foreach (['.env', 'config/app.php', 'src/Auth.php', 'database/schema.sql', 'views/partials/head.php', 'tools/smoke-test.php'] as $p) {
    $v->get($p);
    check("bloqueia /$p", $v->status === 403, (string) $v->status);
}

echo "\nCatálogo\n";
$v->get('');
check('home responde', $v->status === 200);
check('hero mostra preço vindo do banco', str_contains($v->body, money_br((string) $q('SELECT price FROM products WHERE is_hero = 1'))));
$v->get('catalogo.php');
check('catálogo lista peças ativas', substr_count($v->body, 'class="product-card ') === min(9, (int) $q('SELECT COUNT(*) FROM products WHERE is_active = 1')));
$v->get('catalogo.php?colecao=cronografos');
check('filtro por coleção', substr_count($v->body, 'class="product-card ') === (int) $q("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE c.slug='cronografos' AND p.is_active=1"));
$v->get('catalogo.php?genero=feminino');
check('filtro por gênero inclui unissex', substr_count($v->body, 'class="product-card ') === min(9, (int) $q("SELECT COUNT(*) FROM products WHERE gender IN ('feminino','unissex') AND is_active=1")));
$v->get('catalogo.php?ordem=menor-preco');
preg_match_all('/product-card__price">R\$ ([\d\.,]+)/', $v->body, $m);
$prices = array_map(static fn($p) => (float) str_replace(['.', ','], ['', '.'], $p), $m[1]);
$sorted = $prices; sort($sorted);
check('ordenação por menor preço', $prices === $sorted && count($prices) > 1);
$v->get('catalogo.php?pagina=2');
check('paginação (página 2)', str_contains($v->body, 'aria-current="page">2'));
$v->get('catalogo.php?q=' . rawurlencode("' OR 1=1 -- "));
check('SQL injection na busca não retorna nada', str_contains($v->body, '>0 peças'));
$v->get('catalogo.php?q=' . rawurlencode('<script>alert(1)</script>'));
check('XSS na busca é escapado', !str_contains($v->body, '<script>alert(1)') && str_contains($v->body, '&lt;script&gt;'));
$v->get('catalogo.php?ordem=' . rawurlencode('price; DROP TABLE products'));
check('ordenação fora da whitelist é ignorada', $v->status === 200 && (int) $q('SELECT COUNT(*) FROM products') > 0);
$v->get('produto.php?slug=ondina-39');
check('página do produto', $v->status === 200 && str_contains($v->body, 'Ficha técnica'));
$v->get('produto.php?slug=nao-existe');
check('produto inexistente dá 404', $v->status === 404);

echo "\nSacola\n";
$t = $v->token();
$v->post('actions/cart.php', ['csrf_token' => $t, 'action' => 'add', 'product_id' => 1, 'quantity' => 2, 'price' => '1.00']);
check('adiciona item', ($_SESSION_CART = $v->flash('sacola.php')) !== '' && str_contains($_SESSION_CART, 'success'), $_SESSION_CART);
$v->get('sacola.php');
check('total vem do banco (ignora preço enviado)', str_contains($v->body, money_br(bcmul((string) $q('SELECT price FROM products WHERE id=1'), '2', 2))));
$stock = (int) $q('SELECT stock FROM products WHERE id=1');
$v->post('actions/cart.php', ['csrf_token' => $t, 'action' => 'update', 'product_id' => 1, 'quantity' => $stock + 1]);
check('bloqueia quantidade acima do estoque', str_contains($v->flash('sacola.php'), 'error'));
$v->post('actions/cart.php', ['csrf_token' => $t, 'action' => 'update', 'product_id' => 1, 'quantity' => 1]);
check('altera quantidade', str_contains($v->flash('sacola.php'), 'Quantidade atualizada'));
$v->post('actions/cart.php', ['csrf_token' => $t, 'action' => 'add', 'product_id' => 6, 'quantity' => 1]);
check('bloqueia peça esgotada', str_contains($v->flash('sacola.php'), 'esgotado'));
$v->post('actions/cart.php', ['csrf_token' => 'invalido', 'action' => 'add', 'product_id' => 2, 'quantity' => 1]);
check('CSRF inválido é rejeitado', $v->status === 403);
$v->get('actions/cart.php');
check('ação por GET é recusada', $v->status === 405);
$v->post('actions/cart.php', ['csrf_token' => $t, 'action' => 'add', 'product_id' => 3, 'quantity' => 1]);
$v->post('actions/cart.php', ['csrf_token' => $t, 'action' => 'remove', 'product_id' => 3]);
check('remove item', str_contains($v->flash('sacola.php'), 'removida'));

echo "\nConta\n";
$v->get('checkout.php');
check('checkout exige login', $v->status === 303 && str_contains($v->location, 'conta/login.php'));
$email = 'smoke' . bin2hex(random_bytes(3)) . '@exemplo.com';
$t = $v->token('conta/cadastro.php');
$v->post('actions/auth.php', ['csrf_token' => $t, 'action' => 'register', 'name' => 'Teste Fumaça', 'email' => 'cliente@morvane.com.br', 'password' => 'Senha1234', 'password_confirm' => 'Senha1234']);
check('e-mail duplicado é recusado', str_contains($v->get('conta/cadastro.php'), 'já tem cadastro'));
$v->post('actions/auth.php', ['csrf_token' => $t, 'action' => 'register', 'name' => 'Teste Fumaça', 'email' => $email, 'password' => 'Senha1234', 'password_confirm' => 'Senha1234', 'next' => parse_url($base, PHP_URL_PATH) . '/checkout.php']);
check('cadastro volta ao checkout', str_contains($v->location, 'checkout.php'));
check('senha salva com hash bcrypt', str_starts_with((string) $q('SELECT password_hash FROM users WHERE email=?', [$email]), '$2y$'));
$t = $v->token('sacola.php');
$v->post('conta/logout.php', ['csrf_token' => $t]);
$v->get('conta/pedidos.php');
check('logout encerra a sessão', $v->status === 303);
$t = $v->token('conta/login.php');
$v->post('actions/auth.php', ['csrf_token' => $t, 'action' => 'login', 'email' => $email, 'password' => 'errada']);
check('senha errada é recusada', str_contains($v->location, 'login.php'));
$v->post('actions/auth.php', ['csrf_token' => $t, 'action' => 'login', 'email' => $email, 'password' => 'Senha1234']);
check('login', $v->status === 303 && !str_contains($v->location, 'login.php'));

echo "\nCheckout\n";
$addr = ['shipping_name' => 'Teste Fumaça', 'shipping_zip' => '01310-100', 'shipping_address' => 'Av. Paulista, 1000', 'shipping_city' => 'São Paulo', 'shipping_state' => 'SP'];
foreach (['pix' => [], 'boleto' => [], 'cartao' => ['card_number' => '4242 4242 4242 4242', 'card_name' => 'TESTE', 'card_expiry' => '12/30', 'card_cvv' => '123']] as $method => $extra) {
    $t = $v->token();
    $before = (int) $q('SELECT stock FROM products WHERE id=3');
    $price = (string) $q('SELECT price FROM products WHERE id=3');
    $v->post('actions/cart.php', ['csrf_token' => $t, 'action' => 'add', 'product_id' => 3, 'quantity' => 2]);
    $v->post('actions/checkout.php', ['csrf_token' => $t, 'payment_method' => $method] + $addr + $extra);
    preg_match('/pedido\.php\?id=(\d+)/', $v->location, $m);
    $orderId = (int) ($m[1] ?? 0);
    check("pedido com $method", $orderId > 0);
    check("  itens com preço congelado ($method)", $q('SELECT unit_price FROM order_items WHERE order_id=? AND product_id=3', [$orderId]) === $price);
    check("  baixa de estoque ($method)", (int) $q('SELECT stock FROM products WHERE id=3') === $before - 2);
    check("  total = soma dos itens ($method)", $q('SELECT total FROM orders WHERE id=?', [$orderId]) === $q('SELECT SUM(subtotal) FROM order_items WHERE order_id=?', [$orderId]));
    $v->get('sacola.php');
    check("  sacola limpa ($method)", str_contains($v->body, 'Sua sacola está vazia'));
}
check('cartão: só os 4 últimos dígitos', $q("SELECT card_last4 FROM orders WHERE payment_method='cartao' ORDER BY id DESC LIMIT 1") === '4242');
$t = $v->token();
$v->post('actions/cart.php', ['csrf_token' => $t, 'action' => 'add', 'product_id' => 2, 'quantity' => 2]);
$pdo->exec('UPDATE products SET stock = 1 WHERE id = 2');
$orders = (int) $q('SELECT COUNT(*) FROM orders');
$v->post('actions/checkout.php', ['csrf_token' => $t, 'payment_method' => 'pix'] + $addr);
check('estoque alterado durante a compra: pedido recusado', (int) $q('SELECT COUNT(*) FROM orders') === $orders && str_contains($v->location, 'checkout.php'));
check('  estoque intacto', (int) $q('SELECT stock FROM products WHERE id=2') === 1);
$pdo->exec('UPDATE products SET stock = 3 WHERE id = 2');
$oid = (int) $q("SELECT MAX(id) FROM orders");
$pdo->exec('UPDATE products SET price = price + 1000 WHERE id = 3');
check('reajuste não altera pedido antigo', $q('SELECT unit_price FROM order_items WHERE order_id=? AND product_id=3', [$oid]) !== $q('SELECT price FROM products WHERE id=3'));
$pdo->exec('UPDATE products SET price = price - 1000 WHERE id = 3');
$other = new Browser($base);
$t2 = $other->token('conta/login.php');
$other->post('actions/auth.php', ['csrf_token' => $t2, 'action' => 'login', 'email' => 'cliente@morvane.com.br', 'password' => 'Morvane@Cliente26']);
$other->get('pedido.php?id=' . $oid);
check('cliente não vê pedido de outro', $other->status === 404);

echo "\nPainel\n";
$v->get('admin/');
check('cliente comum recebe 403 no /admin', $v->status === 403);
$anon = new Browser($base);
$anon->get('admin/produtos.php');
check('visitante vai para o login', $anon->status === 303 && str_contains($anon->location, 'login.php'));
$adm = new Browser($base);
$t = $adm->token('conta/login.php');
$adm->post('actions/auth.php', ['csrf_token' => $t, 'action' => 'login', 'email' => 'admin@morvane.com.br', 'password' => 'Morvane@Admin26']);
foreach (['admin/', 'admin/produtos.php', 'admin/produto-form.php', 'admin/categorias.php', 'admin/pedidos.php', 'admin/clientes.php', "admin/pedidos.php?id=$oid"] as $p) {
    $adm->get($p);
    check("admin abre $p", $adm->status === 200);
}
$t = $adm->token('admin/');
$adm->post('admin/produtos.php', ['action' => 'toggle', 'id' => 2]);
check('POST no painel sem CSRF é rejeitado', $adm->status === 403);
$img = tempnam(sys_get_temp_dir(), 'mvimg') . '.jpg';
file_put_contents($img, '<?php echo "x"; ?>');
$fields = ['csrf_token' => $t, 'name' => 'Peça Smoke', 'category_id' => 1, 'description' => 'Peça criada pelo teste de fumaça do projeto.', 'price' => '1.234,50', 'stock' => '5', 'case_material' => 'Aço', 'movement_type' => 'Automático', 'diameter_mm' => '40', 'water_resistance_m' => '50', 'strap_type' => 'Couro', 'gender' => 'unissex', 'is_active' => '1'];
$adm->post('admin/produto-form.php', $fields + ['image' => new CURLFile($img, 'image/jpeg', 'foto.jpg')]);
check('upload de PHP disfarçado é rejeitado', str_contains($adm->get('admin/produto-form.php'), 'Formato não aceito') && !$q("SELECT COUNT(*) FROM products WHERE name='Peça Smoke'"));
$png = tempnam(sys_get_temp_dir(), 'mvpng') . '.png';
$im = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
file_put_contents($png, $im);
$adm->post('admin/produto-form.php', $fields + ['image' => new CURLFile($png, 'image/png', 'pequena.png')]);
check('imagem pequena demais é rejeitada', str_contains($adm->get('admin/produto-form.php'), 'entre 200 e 6000'));
$valid = dirname(__DIR__) . '/assets/img/products/ondina-39.webp';
$adm->post('admin/produto-form.php', $fields + ['image' => new CURLFile($valid, 'image/webp', 'ondina.webp')]);
$newId = (int) $q("SELECT id FROM products WHERE name='Peça Smoke'");
check('cadastra produto com foto', $newId > 0 && $q('SELECT price FROM products WHERE id=?', [$newId]) === '1234.50');
$adm->post("admin/produto-form.php?id=$newId", ['id' => $newId, 'price' => '2000', 'stock' => '9', 'slug' => 'peca-smoke'] + $fields);
check('edita preço e estoque', $q('SELECT price FROM products WHERE id=?', [$newId]) === '2000.00' && (int) $q('SELECT stock FROM products WHERE id=?', [$newId]) === 9);
$adm->post('admin/produtos.php', ['csrf_token' => $t, 'action' => 'stock', 'id' => $newId, 'stock' => '3']);
check('ajuste rápido de estoque', (int) $q('SELECT stock FROM products WHERE id=?', [$newId]) === 3);
$adm->post('admin/produtos.php', ['csrf_token' => $t, 'action' => 'toggle', 'id' => $newId]);
$v->get('produto.php?slug=peca-smoke');
check('desativar tira da loja', $v->status === 404);
$adm->post('admin/produtos.php', ['csrf_token' => $t, 'action' => 'delete', 'id' => 3]);
check('excluir peça com pedidos vira desativação', (int) $q('SELECT COUNT(*) FROM products WHERE id=3') === 1 && (int) $q('SELECT is_active FROM products WHERE id=3') === 0);
$adm->post('admin/produtos.php', ['csrf_token' => $t, 'action' => 'toggle', 'id' => 3]);
$file = (string) $q('SELECT image FROM products WHERE id=?', [$newId]);
$adm->post('admin/produtos.php', ['csrf_token' => $t, 'action' => 'delete', 'id' => $newId]);
check('excluir peça sem pedidos apaga registro e arquivo', !$q('SELECT COUNT(*) FROM products WHERE id=?', [$newId]) && !is_file(dirname(__DIR__) . '/' . $file));
$adm->post('admin/categorias.php', ['csrf_token' => $t, 'action' => 'delete', 'id' => 1]);
check('coleção com peças não é excluída', (int) $q('SELECT COUNT(*) FROM categories WHERE id=1') === 1);
$adm->post('admin/pedidos.php', ['csrf_token' => $t, 'id' => $oid, 'status' => 'enviado']);
check('altera status do pedido', $q('SELECT status FROM orders WHERE id=?', [$oid]) === 'enviado');

$pdo->prepare('DELETE o FROM orders o JOIN users u ON u.id=o.user_id WHERE u.email=?')->execute([$email]);
$pdo->prepare('DELETE FROM users WHERE email=?')->execute([$email]);

echo "\nResultado: $pass ok, $fail falha(s).\n";
exit($fail ? 1 : 0);

function money_br(string $v): string
{
    return 'R$ ' . number_format((float) $v, 2, ',', '.');
}
