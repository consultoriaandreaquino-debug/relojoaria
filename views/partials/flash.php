<?php
/** Mensagens da requisição anterior (sucesso, erro, aviso). */
$flashes = take_flashes();
if (!$flashes) {
    return;
}
?>
<div class="flash-stack" role="status">
    <?php foreach ($flashes as $f): ?>
    <div class="flash flash--<?= e($f['type']) ?>">
        <span><?= e($f['message']) ?></span>
        <button type="button" class="flash__close" aria-label="Fechar aviso" data-flash-close>&times;</button>
    </div>
    <?php endforeach; ?>
</div>
