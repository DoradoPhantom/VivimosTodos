<?php
declare(strict_types=1);

// Las reservas nuevas se hacen en inicio esta pagina solo redirige al calendario
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

$rol = (string) (current_user()['rol'] ?? '');
if (!in_array($rol, ['residente', 'administrador', 'supervisor'], true)) {
    header('Location: ' . url('reservas/index.php'));
    exit;
}

header('Location: ' . url('index.php') . '#calendario-reservas');
exit;
