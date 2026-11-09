<?php
/**
 * Loader simples de .env (sem Composer).
 * Lê pares CHAVE=valor, ignora comentários e linhas vazias,
 * aceita valores entre aspas e não sobrescreve variáveis já definidas no servidor.
 */
declare(strict_types=1);

function load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Arquivo .env não encontrado. Copie .env.example para .env.');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            continue;
        }
        // Remove aspas externas: "valor" ou 'valor'
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        } else {
            // Comentário no fim da linha (só fora de aspas)
            $hash = strpos($value, ' #');
            if ($hash !== false) {
                $value = rtrim(substr($value, 0, $hash));
            }
        }

        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

/** Lê uma variável de ambiente com valor padrão. */
function env(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV)) {
        return (string) $_ENV[$key];
    }
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}
