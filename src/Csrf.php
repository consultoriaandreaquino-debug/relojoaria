<?php
declare(strict_types=1);

/**
 * Token CSRF por sessão. Todo formulário POST e todo fetch que altera estado envia o token.
 */
final class Csrf
{
    private const KEY = 'csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    public static function isValid($token): bool
    {
        return is_string($token) && !empty($_SESSION[self::KEY]) && hash_equals($_SESSION[self::KEY], $token);
    }

    /** Interrompe a requisição se o token for inválido. */
    public static function verify(): void
    {
        $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (self::isValid($token)) {
            return;
        }
        if (wants_json()) {
            json_response(['ok' => false, 'message' => 'Sua sessão expirou. Recarregue a página e tente de novo.'], 419);
        }
        http_response_code(419);
        $title = 'Sessão expirada';
        $message = 'Por segurança, o formulário expirou. Volte, recarregue a página e envie de novo.';
        require BASE_PATH . '/views/error.php';
        exit;
    }

    /** Novo token após login e logout. */
    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
