<?php
declare(strict_types=1);
if (!isset($pageTitle)) {
    $pageTitle = 'Vivimos Todos';
}
$user = current_user();
$pendingReservations = 0;
$pendingPreview = [];
if ($user && can_manage_reservations()) {
    try {
        $pdo = db();
        $stmtPending = $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente'");
        $pendingReservations = (int) $stmtPending->fetchColumn();
        $stmtPreview = $pdo->query(
            "SELECT r.fecha_evento, u.nombre_completo
             FROM reservas r
             INNER JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.estado = 'pendiente'
             ORDER BY r.creado_en DESC
             LIMIT 5"
        );
        $pendingPreview = $stmtPreview->fetchAll();
    } catch (Throwable $e) {
        $pendingReservations = 0;
        $pendingPreview = [];
    }
}
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
        <a class="logo" href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Vivimos Todos</a>
        <?php if ($user): ?>
            <nav class="nav-main">
                <a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Inicio</a>
                <a href="<?= htmlspecialchars(url('inventario/index.php'), ENT_QUOTES, 'UTF-8') ?>">Catálogo</a>
                <a href="<?= htmlspecialchars(url('reservas/index.php'), ENT_QUOTES, 'UTF-8') ?>">Reservas</a>
                <?php if ($user && can_manage_reservations()): ?>
                    <div class="nav-notif-wrap">
                    <button type="button" class="nav-notif" title="Notificaciones de reservas" aria-label="Notificaciones" onclick="toggleNotifPanel()">
                        <span class="nav-notif-icon" aria-hidden="true">&#128276;</span>
                        <?php if ($pendingReservations > 0): ?>
                            <span class="nav-notif-badge"><?= $pendingReservations ?></span>
                        <?php endif; ?>
                    </button>
                        <div id="notif-panel" class="notif-panel" hidden>
                            <p class="notif-title">
                                <?php if ($pendingReservations > 0): ?>
                                    Tienes <?= $pendingReservations ?> solicitud(es) pendiente(s).
                                <?php else: ?>
                                    No hay reservas pendientes.
                                <?php endif; ?>
                            </p>
                            <?php if (count($pendingPreview) > 0): ?>
                                <ul class="notif-list">
                                    <?php foreach ($pendingPreview as $p): ?>
                                        <li>
                                            <?= htmlspecialchars($p['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                                            <small><?= htmlspecialchars(date('d/m H:i', strtotime((string) $p['fecha_evento'])), ENT_QUOTES, 'UTF-8') ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
<?php if ($user && can_manage_reservations()): ?>
<script>
function toggleNotifPanel() {
    var panel = document.getElementById('notif-panel');
    if (!panel) return;
    panel.hidden = !panel.hidden;
}
document.addEventListener('click', function (ev) {
    var wrap = document.querySelector('.nav-notif-wrap');
    if (!wrap) return;
    if (!wrap.contains(ev.target)) {
        var panel = document.getElementById('notif-panel');
        if (panel) panel.hidden = true;
    }
});
</script>
<?php endif; ?>
