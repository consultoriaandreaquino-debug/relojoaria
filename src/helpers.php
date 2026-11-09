<?php
/**
 * Funções utilitárias usadas em páginas, ações e views.
 */
declare(strict_types=1);

/** Escapa qualquer saída para HTML (proteção contra XSS). */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Caminho base da aplicação, derivado de APP_URL (ex.: /relojoaria). */
function base_path(): string
{
    static $path = null;
    if ($path === null) {
        $path = rtrim((string) parse_url(APP_URL, PHP_URL_PATH), '/');
    }
    return $path;
}

/** Monta uma URL interna com query string opcional. */
function url(string $path = '', array $query = []): string
{
    $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
    $out = base_path() . '/' . ltrim($path, '/');
    return $query ? $out . '?' . http_build_query($query) : $out;
}

/** URL de arquivo estático com versão para cache. */
function asset(string $path): string
{
    $file = BASE_PATH . '/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $version;
}

/** URL de imagem de produto (aceita caminho relativo salvo no banco). */
function image_url(?string $path): string
{
    if ($path === null || $path === '') {
        return url('assets/img/placeholder.svg');
    }
    return url($path);
}

/** Valor monetário em reais: R$ 12.900,00 */
function money($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function redirect(string $path, array $query = []): void
{
    $target = str_starts_with($path, '/') ? $path : url($path, $query);
    header('Location: ' . $target, true, 303);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Ações que alteram estado só aceitam POST. */
function require_post(): void
{
    if (!is_post()) {
        http_response_code(405);
        header('Allow: POST');
        exit('Método não permitido.');
    }
}

/** A requisição veio de fetch() esperando JSON? */
function wants_json(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Mensagens de uma requisição para a próxima (padrão PRG). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Guarda dados do formulário e erros para reexibir após redirect. */
function remember_form(array $old, array $errors): void
{
    unset($old['password'], $old['password_confirm'], $old['csrf_token'], $old['card_number'], $old['card_cvv']);
    $_SESSION['old'] = $old;
    $_SESSION['errors'] = $errors;
}

function old(string $key, $default = ''): string
{
    $value = $_SESSION['old'][$key] ?? $default;
    return is_scalar($value) ? (string) $value : '';
}

function field_error(string $key): ?string
{
    return $_SESSION['errors'][$key] ?? null;
}

/** Limpa dados de formulário depois que a view os exibiu. */
function forget_form(): void
{
    unset($_SESSION['old'], $_SESSION['errors']);
}

/** Inteiro positivo vindo de input externo, ou null. */
function int_param($value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (is_string($value) && $value !== '' && ctype_digit($value) && strlen($value) < 10) {
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }
    return null;
}

/** Texto de entrada limpo (string, sem espaços nas pontas). */
function input(array $source, string $key, int $max = 255): string
{
    $value = $source[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return mb_substr(trim($value), 0, $max);
}

/** Só permite redirecionar para caminhos internos da aplicação (evita open redirect). */
function safe_next(?string $next): string
{
    $next = (string) $next;
    if ($next === '' || str_starts_with($next, '//') || !preg_match('#^/[A-Za-z0-9/_\-\.]*(\?[A-Za-z0-9=&_\-%\.]*)?$#', $next)) {
        return '';
    }
    $base = base_path();
    if ($base !== '' && $next !== $base && !str_starts_with($next, $base . '/')) {
        return '';
    }
    return $next;
}

/** Disponibilidade a partir do estoque. */
function stock_status(int $stock): array
{
    if ($stock <= 0) {
        return ['key' => 'out', 'label' => 'Esgotado'];
    }
    if ($stock <= 3) {
        return ['key' => 'low', 'label' => $stock === 1 ? 'Última unidade' : "Últimas {$stock} unidades"];
    }
    return ['key' => 'in', 'label' => 'Disponível'];
}

function gender_label(string $gender): string
{
    return ['masculino' => 'Masculino', 'feminino' => 'Feminino', 'unissex' => 'Unissex'][$gender] ?? $gender;
}

function payment_label(string $method): string
{
    return ['pix' => 'PIX', 'boleto' => 'Boleto', 'cartao' => 'Cartão de crédito'][$method] ?? $method;
}

function status_label(string $status): string
{
    return [
        'pago' => 'Pago',
        'em_separacao' => 'Em separação',
        'enviado' => 'Enviado',
        'entregue' => 'Entregue',
        'cancelado' => 'Cancelado',
    ][$status] ?? $status;
}

function format_date(string $datetime, bool $withTime = false): string
{
    $ts = strtotime($datetime);
    if (!$ts) {
        return '';
    }
    return $withTime ? date('d/m/Y', $ts) . ' às ' . date('H:i', $ts) : date('d/m/Y', $ts);
}

/** Número do pedido exibido ao cliente: MV-000123 */
function order_code(int $id): string
{
    return 'MV-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}

/** Gera slug ASCII a partir de um texto em português. */
function slugify(string $text): string
{
    $text = strtr(mb_strtolower($text), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e',
        'í' => 'i', 'î' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ú' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ]);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-');
}

/** Renderiza uma view parcial com variáveis locais. */
function partial(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require BASE_PATH . '/views/partials/' . $name . '.php';
}

/** Interrompe com a página 404 da loja. */
function not_found(string $message = 'Não encontramos esta página.'): void
{
    http_response_code(404);
    $title = 'Página não encontrada';
    require BASE_PATH . '/views/error.php';
    exit;
}
