<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Inicio';
require __DIR__ . '/includes/header.php';
?>
<h1>Bienvenido</h1>
<p class="lead">Panel principal del sistema.</p>
<ul class="card-list">
    <li><a href="<?= htmlspecialchars(url('inventario/index.php'), ENT_QUOTES, 'UTF-8') ?>">Ver y gestionar inventario de insumos</a></li>
    <?php if ((current_user()['rol'] ?? '') === 'administrador'): ?>
        <li><a href="<?= htmlspecialchars(url('admin/usuarios.php'), ENT_QUOTES, 'UTF-8') ?>">Administración de usuarios (solo administrador)</a></li>
    <?php endif; ?>
</ul>
<?php require __DIR__ . '/includes/footer.php'; ?>
