<?php
/** Logout: só por POST com CSRF; destrói a sessão inteira. */
require dirname(__DIR__) . '/config/app.php';

require_post();
Csrf::verify();

Auth::logout();

// nova sessão limpa só para a mensagem de despedida
session_start();
session_regenerate_id(true);
flash('info', 'Você saiu da sua conta.');
redirect('');
