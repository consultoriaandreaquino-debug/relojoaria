<?php
/**
 * Abertura do documento.
 * Variáveis: $title, $description, $bodyClass, $headerVariant ('overlay' | 'light' | 'dark')
 */
$pageTitle = isset($title) && $title !== '' ? $title . ' · ' . APP_NAME : APP_NAME . ' · Relojoaria desde 1968';
$pageDescription = $description ?? 'Morvane, casa de relojoaria fundada em São Paulo em 1968. Relógios automáticos, cronógrafos e edições numeradas.';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="theme-color" content="#071525">
    <link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <script>
        // Marca a revelação antes da pintura, para a peça não piscar antes de ser coberta
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) document.documentElement.classList.add('js-reveal');
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Manrope:wght@300;400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/pages.css')) ?>">
    <?php if (!empty($isAdmin)): ?>
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
    <?php endif; ?>
    <?php if (!empty($preloadImage)): ?>
    <link rel="preload" as="image" href="<?= e($preloadImage) ?>">
    <?php endif; ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
