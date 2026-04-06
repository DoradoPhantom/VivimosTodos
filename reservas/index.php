<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/reservas_rules.php';
require_login();

$pdo = db();
$message = '';
$error = '';
$canStaff = can_manage_reservations();
$userId = (int) (current_user()['id'] ?? 0);

if ($canStaff && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        $rid = (int) ($_POST['id'] ?? 0);
        if ($rid > 0 && ($action === 'aprobar' || $action === 'rechazar')) {
            $comentario = trim((string) ($_POST['comentario_revision'] ?? ''));
            if ($action === 'rechazar' && $comentario === '') {
                $error = 'Indica un motivo al rechazar la reserva.';
            } else {
                $nuevoEstado = $action === 'aprobar' ? 'aprobada' : 'rechazada';
                $stmt = $pdo->prepare(
                    'UPDATE reservas SET estado = ?, comentario_revision = ?, revisado_por_id = ?, revisado_en = NOW()
                     WHERE id = ? AND estado = \'pendiente\''
                );
                $stmt->execute([
                    $nuevoEstado,
                    $action === 'aprobar' ? null : $comentario,
                    $userId,
                    $rid,
                ]);
                if ($stmt->rowCount() > 0) {
                    $message = $action === 'aprobar' ? 'Reserva autorizada.' : 'Reserva rechazada.';
                } else {
                    $error = 'No se pudo actualizar (¿ya estaba revisada?).';
                }
            }
        }
    }
}

if ($canStaff) {
    $reservas = $pdo->query(
        'SELECT r.id, r.fecha_evento, r.descripcion, r.estado, r.comentario_revision, r.creado_en,
                u.nombre_completo AS solicitante_nombre, u.usuario AS solicitante_usuario
         FROM reservas r
         INNER JOIN usuarios u ON u.id = r.usuario_id
         ORDER BY r.fecha_evento DESC, r.id DESC'
    )->fetchAll();
} else {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.fecha_evento, r.descripcion, r.estado, r.comentario_revision, r.creado_en
         FROM reservas r
         WHERE r.usuario_id = ?
         ORDER BY r.fecha_evento DESC, r.id DESC'
    );
    $stmt->execute([$userId]);
    $reservas = $stmt->fetchAll();
}

$estadoEtiqueta = static function (string $e): string {
    return match ($e) {
        'pendiente' => 'Pendiente',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        default => $e,
    };
};

$pageTitle = 'Reservas del salón';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1>Reservas del salón</h1>
<p class="muted">
    <?php if ($canStaff): ?>
        Como <strong>administrador</strong> o <strong>supervisor</strong> ves todas las solicitudes y puedes autorizar o rechazar las que estén pendientes.
    <?php else: ?>
        Aquí aparecen tus reservas. Recuerda: mínimo 48 horas y máximo 90 días de anticipación.
    <?php endif; ?>
</p>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<p>
    <a class="btn btn-primary" href="<?= htmlspecialchars(url('reservas/nueva.php'), ENT_QUOTES, 'UTF-8') ?>">Nueva reserva</a>
</p>

<div class="table-wrap">
    <table class="data-table">
        <thead>
        <tr>
            <th>Fecha del evento</th>
            <?php if ($canStaff): ?>
                <th>Solicitante</th>
            <?php endif; ?>
            <th>Descripción</th>
            <th>Estado</th>
            <th>Observaciones</th>
            <?php if ($canStaff): ?><th>Acciones</th><?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($reservas as $row): ?>
            <tr>
                <td><?= htmlspecialchars(reserva_formato_tabla((string) $row['fecha_evento']), ENT_QUOTES, 'UTF-8') ?></td>
                <?php if ($canStaff): ?>
                    <td>
                        <?= htmlspecialchars($row['solicitante_nombre'], ENT_QUOTES, 'UTF-8') ?>
                        <br><small class="muted"><?= htmlspecialchars($row['solicitante_usuario'], ENT_QUOTES, 'UTF-8') ?></small>
                    </td>
                <?php endif; ?>
                <td><?= $row['descripcion'] !== null && $row['descripcion'] !== ''
                    ? nl2br(htmlspecialchars((string) $row['descripcion'], ENT_QUOTES, 'UTF-8'))
                    : '—' ?></td>
                <td><?= htmlspecialchars($estadoEtiqueta((string) $row['estado']), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $row['comentario_revision'] !== null && $row['comentario_revision'] !== ''
                    ? nl2br(htmlspecialchars((string) $row['comentario_revision'], ENT_QUOTES, 'UTF-8'))
                    : '—' ?></td>
                <?php if ($canStaff): ?>
                    <td class="actions">
                        <?php if ($row['estado'] === 'pendiente'): ?>
                            <form method="post" class="inline-form" onsubmit="return confirm('¿Autorizar esta reserva?');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="aprobar">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-primary">Autorizar</button>
                            </form>
                            <form method="post" class="reserva-rechazo-form" onsubmit="return reservaConfirmarRechazo(this);">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="rechazar">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <label class="sr-only" for="motivo-<?= (int) $row['id'] ?>">Motivo del rechazo</label>
                                <input type="text" name="comentario_revision" id="motivo-<?= (int) $row['id'] ?>" class="input-rechazo" placeholder="Motivo si rechazas…" maxlength="500">
                                <button type="submit" class="btn btn-sm btn-danger">Rechazar</button>
                            </form>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php
        $colspan = $canStaff ? 6 : 4;
        if (count($reservas) === 0): ?>
            <tr><td colspan="<?= $colspan ?>">No hay reservas registradas.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
function reservaConfirmarRechazo(form) {
    var input = form.querySelector('input[name="comentario_revision"]');
    if (!input || !input.value.trim()) {
        alert('Escribe el motivo del rechazo.');
        return false;
    }
    return confirm('¿Rechazar esta reserva?');
}
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
