<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/reservas_rules.php';
require_login();

if (!can_manage_reservations()) {
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

$pdo = db();

$stats = [
    'usuarios_activos' => 0,
    'reservas_mes_total' => 0,
    'reservas_aprobadas' => 0,
    'reservas_pendientes' => 0,
    'proximas_48h' => 0,
];

$hoy = new DateTimeImmutable('today');
$inicioMes = $hoy->modify('first day of this month')->setTime(0, 0, 0);
$finMes = $hoy->modify('last day of this month')->setTime(23, 59, 59);
$lim48 = (new DateTimeImmutable('now'))->add(new DateInterval('PT48H'));

try {
    $stats['usuarios_activos'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();
    $stmtMes = $pdo->prepare(
        "SELECT COUNT(*) FROM reservas WHERE fecha_evento >= ? AND fecha_evento <= ?"
    );
    $stmtMes->execute([$inicioMes->format('Y-m-d H:i:s'), $finMes->format('Y-m-d H:i:s')]);
    $stats['reservas_mes_total'] = (int) $stmtMes->fetchColumn();

    $stats['reservas_aprobadas'] = (int) $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'aprobada'")->fetchColumn();
    $stats['reservas_pendientes'] = (int) $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente'")->fetchColumn();

    $stmt48 = $pdo->prepare(
        "SELECT COUNT(*) FROM reservas
         WHERE fecha_evento >= NOW()
           AND fecha_evento <= ?
           AND estado IN ('pendiente','aprobada')"
    );
    $stmt48->execute([$lim48->format('Y-m-d H:i:s')]);
    $stats['proximas_48h'] = (int) $stmt48->fetchColumn();
} catch (Throwable $e) {
    // mantener stats por defecto
}

$semaforo = [];
try {
    $stmtSem = $pdo->prepare(
        "SELECT r.fecha_evento, r.estado, u.nombre_completo
         FROM reservas r
         INNER JOIN usuarios u ON u.id = r.usuario_id
         WHERE r.fecha_evento >= NOW()
           AND r.fecha_evento <= ?
           AND r.estado IN ('pendiente','aprobada')
         ORDER BY r.fecha_evento ASC
         LIMIT 10"
    );
    $stmtSem->execute([$lim48->format('Y-m-d H:i:s')]);
    $semaforo = $stmtSem->fetchAll();
} catch (Throwable $e) {
    $semaforo = [];
}

$pageTitle = 'Dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1>Dashboard</h1>
<p class="muted">Resumen para administrador y supervisor.</p>

<section class="section">
    <div class="form-grid">
        <div class="full" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
            <div class="section" style="margin:0;">
                <strong><?= (int) $stats['reservas_mes_total'] ?></strong>
                <div class="muted">Total reservas (mes)</div>
            </div>
            <div class="section" style="margin:0;">
                <strong><?= (int) $stats['reservas_aprobadas'] ?></strong>
                <div class="muted">Reservas aprobadas</div>
            </div>
            <div class="section" style="margin:0;">
                <strong><?= (int) $stats['reservas_pendientes'] ?></strong>
                <div class="muted">Pendientes de revisión</div>
            </div>
            <div class="section" style="margin:0;">
                <strong><?= (int) $stats['proximas_48h'] ?></strong>
                <div class="muted">Próximas (48 h)</div>
            </div>
            <div class="section" style="margin:0;">
                <strong><?= (int) $stats['usuarios_activos'] ?></strong>
                <div class="muted">Usuarios activos</div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <h2>Semáforo de reservas (próximas 48 h)</h2>
    <?php if (count($semaforo) === 0): ?>
        <p class="muted">No hay reservas en las próximas 48 horas.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Solicitante</th>
                    <th>Estado</th>
                    <th>Semáforo</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($semaforo as $r): ?>
                    <?php
                    $fecha = new DateTimeImmutable((string) $r['fecha_evento']);
                    $diffH = ($fecha->getTimestamp() - time()) / 3600;
                    $color = $diffH <= 12 ? 'chip chip-aprobada' : ($diffH <= 24 ? 'chip chip-pendiente' : 'chip');
                    $label = $diffH <= 12 ? 'Rojo' : ($diffH <= 24 ? 'Amarillo' : 'Verde');
                    $estado = (string) ($r['estado'] ?? '');
                    $estadoTxt = $estado === 'aprobada' ? 'Aprobada' : 'Pendiente';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars(reserva_formato_tabla((string) $r['fecha_evento']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($r['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($estadoTxt, ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="<?= htmlspecialchars($color, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p class="muted" style="margin-top:0.75rem;">
        Recomendación: usa la campana para aprobar/rechazar más rápido o entra a <a href="<?= htmlspecialchars(url('reservas/index.php'), ENT_QUOTES, 'UTF-8') ?>">Reservas</a>.
    </p>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

