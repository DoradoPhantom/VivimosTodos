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
    <!-- 1) Contenido y formularios (páginas PHP). 2) Barra lateral. 3) Barra superior. -->
    <link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/01-app-base.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/02-app-sidebar.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/03-app-topbar.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script>
    (function () {
        try {
            if (localStorage.getItem('vivimos-theme') === 'dark') {
                document.documentElement.classList.add('theme-dark');
            }
        } catch (e) {}
    })();
    </script>
</head>
<body class="app-body-root">
<div class="app-shell">
<?php if ($user): ?>
<?php require __DIR__ . '/sidebar.php'; ?>
<?php endif; ?>
<div class="app-body">
<?php if ($user): ?>
<header class="site-header">
    <div class="header-inner">
        <nav class="nav-main nav-main-end" aria-label="Acciones de sesión">
            <?php if (can_manage_reservations()): ?>
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
            <span class="nav-user">
                <?= htmlspecialchars($user['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>
                <small>(<?= htmlspecialchars($user['rol'], ENT_QUOTES, 'UTF-8') ?>)</small>
            </span>
            <a class="nav-link-exit" href="<?= htmlspecialchars(url('logout.php'), ENT_QUOTES, 'UTF-8') ?>">Salir</a>
        </nav>
    </div>
</header>
<?php endif; ?>
<main class="wrap main-content">
