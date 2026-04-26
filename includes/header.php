<?php
declare(strict_types=1);
if (!isset($pageTitle)) {
    $pageTitle = 'Vivimos Todos';
}
$user = current_user();
$pendingReservations = 0;
$pendingPreview = [];
// detalle para acciones rápidas desde la campana
$pendingDetail = [];
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

        $stmtDetail = $pdo->query(
            "SELECT r.id, r.fecha_evento, u.nombre_completo
             FROM reservas r
             INNER JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.estado = 'pendiente'
             ORDER BY r.creado_en DESC
             LIMIT 10"
        );
        $pendingDetail = $stmtDetail->fetchAll();
    } catch (Throwable $e) {
        $pendingReservations = 0;
        $pendingPreview = [];
        $pendingDetail = [];
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
                        <?php if (count($pendingDetail) > 0): ?>
                            <ul class="notif-list">
                                <?php foreach ($pendingDetail as $p): ?>
                                    <li>
                                        <button
                                            type="button"
                                            class="notif-item"
                                            data-reserva-id="<?= (int) ($p['id'] ?? 0) ?>"
                                            data-reserva-nombre="<?= htmlspecialchars((string) ($p['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-reserva-fecha="<?= htmlspecialchars((string) ($p['fecha_evento'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="openReservaReviewFromNotif(this)"
                                        >
                                            <?= htmlspecialchars((string) ($p['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                            <small><?= htmlspecialchars(date('d/m H:i', strtotime((string) $p['fecha_evento'])), ENT_QUOTES, 'UTF-8') ?></small>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (count($pendingPreview) > 0): ?>
                            <p class="muted" style="margin:0.5rem 0 0;">
                                También puedes revisar todo en <a href="<?= htmlspecialchars(url('reservas/index.php'), ENT_QUOTES, 'UTF-8') ?>">Reservas</a>.
                            </p>
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

<?php if (can_manage_reservations()): ?>
<div class="modal" id="reserva-review-modal" hidden>
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="reserva-review-title">
        <div class="modal-header">
            <h2 id="reserva-review-title">Revisar solicitud</h2>
            <button type="button" class="btn btn-sm btn-ghost" data-modal-close>Cancelar</button>
        </div>
        <div class="modal-body">
            <p class="modal-text" id="reserva-review-sub"></p>
            <form method="post" action="<?= htmlspecialchars(url('reservas/index.php'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return reviewValidateReject();" class="form-grid">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" id="review-id-reject" value="">
                <input type="hidden" name="action" value="rechazar">
                <label class="full">Motivo del rechazo (solo si rechazas)
                    <input type="text" name="comentario_revision" id="review-reason" maxlength="500" placeholder="Escribe el motivo…">
                </label>
                <div class="modal-actions full">
                    <button type="button" class="btn btn-outline" onclick="reviewApprove()">Autorizar</button>
                    <button type="submit" class="btn btn-danger">Rechazar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
<main class="wrap main-content">
