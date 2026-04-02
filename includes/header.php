<?php
declare(strict_types=1);
if (!isset($pageTitle)) {
    $pageTitle = 'VivemosTodos';
}
$user = current_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="logo" href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">VivemosTodos</a>
        <?php if ($user): ?>
            <nav class="nav-main">
                <a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Inicio</a>
                <a href="<?= htmlspecialchars(url('inventario/index.php'), ENT_QUOTES, 'UTF-8') ?>">Inventario</a>
                <?php if (($user['rol'] ?? '') === 'administrador'): ?>
                    <a href="<?= htmlspecialchars(url('admin/usuarios.php'), ENT_QUOTES, 'UTF-8') ?>">Usuarios</a>
                <?php endif; ?>
                <span class="nav-user"><?= htmlspecialchars($user['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                    <small>(<?= htmlspecialchars($user['rol'], ENT_QUOTES, 'UTF-8') ?>)</small></span>
                <a class="btn btn-outline" href="<?= htmlspecialchars(url('logout.php'), ENT_QUOTES, 'UTF-8') ?>">Salir</a>
            </nav>
        <?php endif; ?>
    </div>
</header>
<main class="wrap main-content">
