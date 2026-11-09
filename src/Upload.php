<?php
declare(strict_types=1);

/**
 * Upload de imagens do painel, com validação completa:
 * erro do PHP, tamanho, MIME real (finfo), extensão, getimagesize e nome aleatório.
 */
final class Upload
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const DIR = 'assets/uploads/products';
    private const TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    /** Há arquivo enviado neste campo? */
    public static function present(?array $file): bool
    {
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Valida e grava a imagem. Devolve o caminho relativo salvo.
     * @throws DomainException com a mensagem para o admin
     */
    public static function image(array $file): string
    {
        if (is_array($file['error'] ?? null)) {
            throw new DomainException('Envie um arquivo por vez.');
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new DomainException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A imagem passa de 2 MB.',
                UPLOAD_ERR_PARTIAL => 'O envio foi interrompido. Tente de novo.',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado.',
                default => 'Não foi possível receber o arquivo.',
            });
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw new DomainException('Arquivo inválido.');
        }
        $size = (int) $file['size'];
        if ($size <= 0 || $size > self::MAX_BYTES || filesize($tmp) > self::MAX_BYTES) {
            throw new DomainException('A imagem precisa ter até 2 MB.');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!isset(self::TYPES[$mime]) || !in_array($ext, self::TYPES[$mime], true)) {
            throw new DomainException('Formato não aceito. Envie JPG, PNG ou WebP.');
        }
        $info = @getimagesize($tmp);
        if ($info === false || $info[0] < 200 || $info[1] < 200 || $info[0] > 6000 || $info[1] > 6000) {
            throw new DomainException('A imagem precisa ter entre 200 e 6000 pixels de lado.');
        }
        if (($info['mime'] ?? '') !== $mime) {
            throw new DomainException('O conteúdo do arquivo não corresponde ao formato.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime][0];
        $dir = BASE_PATH . '/' . self::DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new DomainException('Pasta de uploads indisponível.');
        }
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            throw new DomainException('Não foi possível salvar a imagem.');
        }
        return self::DIR . '/' . $name;
    }

    /** Apaga um arquivo enviado pelo painel (nunca toca nas imagens do catálogo base). */
    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::DIR . '/') && preg_match('#^[a-z/]+/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) {
            $full = BASE_PATH . '/' . $path;
            if (is_file($full)) {
                unlink($full);
            }
        }
    }
}
