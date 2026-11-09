<?php
/** Logotipo tipográfico: aro fino com marcação às 12h + MORVANE. */
$logoClass = $logoClass ?? '';
?>
<span class="logo <?= e($logoClass) ?>">
    <svg class="logo__mark" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
        <circle cx="16" cy="16" r="13.5" fill="none" stroke="currentColor" stroke-width="1"/>
        <line x1="16" y1="2.5" x2="16" y2="8" stroke="currentColor" stroke-width="1.2"/>
    </svg>
    <span class="logo__word">Morvane</span>
</span>
