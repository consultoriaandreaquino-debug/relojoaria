<?php
/** Página de erro neutra (404, 419, 500). Variáveis: $title, $message */
$message = $message ?? 'Tivemos um problema ao carregar esta página. Tente de novo em instantes.';
$bodyClass = 'page-light';
$headerVariant = 'light';
ob_start();
try {
    require BASE_PATH . '/views/partials/head.php';
    require BASE_PATH . '/views/partials/header.php';
    $chrome = true;
    ob_end_flush();
} catch (Throwable $ignored) {
    // se o próprio cabeçalho falhar (ex.: banco fora do ar), descarta o que saiu e mostra só a mensagem
    ob_end_clean();
    $chrome = false;
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Morvane</title></head><body style="font-family:sans-serif;padding:3rem">';
}
?>
<main id="conteudo" class="error-page">
    <p class="eyebrow"><?= (int) http_response_code() ?></p>
    <h1 class="display-title"><?= e($title ?? 'Algo não saiu como esperado') ?></h1>
    <p class="lead-text"><?= e($message) ?></p>
    <a class="btn-line" href="<?= e(url('')) ?>">Voltar ao início</a>
</main>
<?php
if ($chrome) {
    require BASE_PATH . '/views/partials/footer.php';
} else {
    echo '</body></html>';
}
