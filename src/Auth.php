<?php
declare(strict_types=1);

/**
 * Autenticação com sessões PHP e senhas com password_hash().
 */
final class Auth
{
    public static function user(): ?array
    {
        static $cache = null;
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        if ($cache !== null && (int) $cache['id'] === (int) $id) {
            return $cache;
        }
        $stmt = db()->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            // usuário removido: encerra a sessão
            unset($_SESSION['user_id']);
            return null;
        }
        return $cache = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && $user['role'] === 'admin';
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    /** Valida os dados de cadastro e devolve os erros por campo. */
    public static function validateRegistration(array $data): array
    {
        $errors = [];
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (mb_strlen($name) < 3) {
            $errors['name'] = 'Informe seu nome completo.';
        } elseif (mb_strlen($name) > 120) {
            $errors['name'] = 'O nome pode ter até 120 caracteres.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors['email'] = 'Informe um e-mail válido.';
        } elseif (self::emailExists($email)) {
            $errors['email'] = 'Este e-mail já tem cadastro. Entre com sua senha.';
        }
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors['password'] = 'A senha precisa de 8 caracteres ou mais, com letras e números.';
        } elseif (strlen($password) > 72) {
            $errors['password'] = 'A senha pode ter até 72 caracteres.';
        }
        if (($data['password_confirm'] ?? '') !== $password) {
            $errors['password_confirm'] = 'As senhas não conferem.';
        }
        return $errors;
    }

    public static function emailExists(string $email): bool
    {
        $stmt = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower($email)]);
        return (bool) $stmt->fetchColumn();
    }

    public static function register(string $name, string $email, string $password): int
    {
        $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT), 'customer']);
        return (int) db()->lastInsertId();
    }

    public static function attempt(string $email, string $password): bool
    {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->execute([mb_strtolower($email)]);
        $row = $stmt->fetch();

        // Mesmo custo de verificação para e-mail inexistente (não revela quem tem conta)
        $hash = $row['password_hash'] ?? '$2y$10$.208uPbOydc0kFCQvxNPzuANwNE1/DOeKnzepCS2qHmKwGYgFGlua';
        if (!password_verify($password, $hash) || !$row) {
            return false;
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $up = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $up->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        self::login((int) $row['id']);
        return true;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        Csrf::rotate();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    /** Exige login; guarda a página de origem para voltar depois. */
    public static function requireLogin(string $next = ''): void
    {
        if (self::check()) {
            return;
        }
        flash('info', 'Entre na sua conta para continuar.');
        redirect('conta/login.php', ['next' => $next ?: ($_SERVER['REQUEST_URI'] ?? '')]);
    }
}
