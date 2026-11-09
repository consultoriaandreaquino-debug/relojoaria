<?php
/**
 * Bootstrap da aplicação: incluído no topo de toda página e ação.
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/config/env.php';
load_env(BASE_PATH . '/.env');

define('APP_DEBUG', env_bool('APP_DEBUG'));
define('APP_URL', rtrim((string) env('APP_URL', ''), '/'));
define('APP_NAME', 'Morvane');

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);

require BASE_PATH . '/config/database.php';

// Classes de src/ carregadas sob demanda (Auth -> src/Auth.php)
spl_autoload_register(static function (string $class): void {
    $file = BASE_PATH . '/src/' . basename($class) . '.php';
    if (preg_match('/^[A-Za-z]+$/', $class) && is_file($file)) {
        require $file;
    }
});
require BASE_PATH . '/src/helpers.php';

// Sessão com cookie HttpOnly e SameSite=Lax
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('morvane_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// Erros não tratados: em produção, mensagem neutra sem detalhes do banco
set_exception_handler(static function (Throwable $e): void {
    error_log('[morvane] ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (APP_DEBUG) {
        echo '<pre style="padding:2rem;white-space:pre-wrap">' . e(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
        return;
    }
    $title = 'Algo não saiu como esperado';
    require BASE_PATH . '/views/error.php';
});
