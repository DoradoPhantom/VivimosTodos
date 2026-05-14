<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/reservas_rules.php';
require_login();

$pdo = db();
$message = '';
$error = '';
$canStaff = can_manage_reservations();
$isResident = is_resident();
$userId = (int) (current_user()['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        $rid = (int) ($_POST['id'] ?? 0);
        if ($rid > 0 && ($action === 'aprobar' || $action === 'rechazar')) {
            if (!$canStaff) {
                $error = 'No tienes permisos para revisar reservas.';
            } else {
            $comentario = trim((string) ($_POST['comentario_revision'] ?? ''));
            if ($action === 'rechazar' && $comentario === '') {
                $error = 'Indica un motivo al rechazar la reserva.';
            } else {
                $nuevoEstado = $action === 'aprobar' ? 'aprobada' : 'rechazada';
                try {
                    $pdo->beginTransaction();
                    if ($action === 'aprobar') {
                        $stmtDet = $pdo->prepare(
                            'SELECT d.id_insumo, d.cantidad, i.nombre, i.cantidad_stock
                             FROM detalle_reserva d
                             INNER JOIN insumos i ON i.id = d.id_insumo
                             WHERE d.id_reserva = ?
                             FOR UPDATE'
                        );
                        $stmtDet->execute([$rid]);
                        $detalles = $stmtDet->fetchAll();

                        foreach ($detalles as $detalle) {
                            $solicitado = (int) ($detalle['cantidad'] ?? 0);
                            $stockActual = (int) round((float) ($detalle['cantidad_stock'] ?? 0));
                            if ($solicitado > $stockActual) {
                                throw new RuntimeException(
                                    'No hay suficientes insumos para aprobar. "' .
                                    (string) ($detalle['nombre'] ?? 'Insumo') .
                                    '" disponibles: ' . $stockActual . '.'
                                );
                            }
                        }

                        if (count($detalles) > 0) {
                            $stmtDescontar = $pdo->prepare(
                                'UPDATE insumos SET cantidad_stock = cantidad_stock - ? WHERE id = ?'
                            );
                            foreach ($detalles as $detalle) {
                                $stmtDescontar->execute([
                                    (int) ($detalle['cantidad'] ?? 0),
                                    (int) ($detalle['id_insumo'] ?? 0),
                                ]);
                            }
                        }
                    }

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
                    if ($stmt->rowCount() === 0) {
                        throw new RuntimeException('No se pudo actualizar (¿ya estaba revisada?).');
                    }
                    $pdo->commit();
                    $message = $action === 'aprobar' ? 'Reserva autorizada e inventario actualizado.' : 'Reserva rechazada.';
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = $e->getMessage();
                }
            }
        }
        } elseif ($rid > 0 && $action === 'cancelar') {
            try {
                $pdo->beginTransaction();
                if ($canStaff) {
                    $stmtReserva = $pdo->prepare(
                        'SELECT id, usuario_id, estado FROM reservas WHERE id = ? FOR UPDATE'
                    );
                    $stmtReserva->execute([$rid]);
                } else {
                    $stmtReserva = $pdo->prepare(
                        'SELECT id, usuario_id, estado FROM reservas WHERE id = ? AND usuario_id = ? FOR UPDATE'
                    );
                    $stmtReserva->execute([$rid, $userId]);
                }
                $reserva = $stmtReserva->fetch();
                if (!$reserva) {
                    throw new RuntimeException($canStaff ? 'No se encontró la reserva a cancelar.' : 'No tienes permisos para cancelar esta reserva.');
                }

                $estadoActual = (string) ($reserva['estado'] ?? '');
                if ($canStaff) {
                    // Admin/supervisor solo cancelan reservas YA aprobadas.
                    if ($estadoActual !== 'aprobada') {
                        throw new RuntimeException('Solo puedes cancelar reservas aprobadas.');
                    }
                } else {
                    // Residente puede cancelar cuando la reserva está pendiente o aprobada.
                    if ($estadoActual !== 'pendiente' && $estadoActual !== 'aprobada') {
                        throw new RuntimeException('No tienes permisos para cancelar esta reserva.');
                    }
                }

                if ($estadoActual === 'aprobada') {
                    $stmtDet = $pdo->prepare(
                        'SELECT d.id_insumo, d.cantidad
                         FROM detalle_reserva d
                         WHERE d.id_reserva = ?
                         FOR UPDATE'
                    );
                    $stmtDet->execute([$rid]);
                    $detalles = $stmtDet->fetchAll();
                    if (count($detalles) > 0) {
                        $stmtDevolver = $pdo->prepare(
                            'UPDATE insumos SET cantidad_stock = cantidad_stock + ? WHERE id = ?'
                        );
                        foreach ($detalles as $detalle) {
                            $stmtDevolver->execute([
                                (int) ($detalle['cantidad'] ?? 0),
                                (int) ($detalle['id_insumo'] ?? 0),
                            ]);
                        }
                    }
                }

                $motivo = $canStaff ? 'Cancelada por administración.' : 'Cancelada por residente.';
                $stmtCancelar = $pdo->prepare(
                    'UPDATE reservas
                     SET estado = \'cancelada\',
                         comentario_revision = ?,
                         revisado_por_id = ?,
                         revisado_en = NOW()
                     WHERE id = ?'
                );
                $stmtCancelar->execute([$motivo, $userId, $rid]);

                $pdo->commit();
                $message = 'Reserva cancelada correctamente.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    }
}

$reservas = [];
try {
    if ($canStaff) {
        $reservas = $pdo->query(
            'SELECT r.id, r.fecha_evento, r.descripcion, r.estado, r.comentario_revision, r.creado_en,
                    u.nombre_completo AS solicitante_nombre, u.usuario AS solicitante_usuario,
                    GROUP_CONCAT(CONCAT(i.nombre, \' x\', CAST(d.cantidad AS UNSIGNED)) ORDER BY i.nombre SEPARATOR \', \') AS insumos_solicitados
             FROM reservas r
             INNER JOIN usuarios u ON u.id = r.usuario_id
             LEFT JOIN detalle_reserva d ON d.id_reserva = r.id
             LEFT JOIN insumos i ON i.id = d.id_insumo
             GROUP BY r.id, r.fecha_evento, r.descripcion, r.estado, r.comentario_revision, r.creado_en, u.nombre_completo, u.usuario
             ORDER BY r.fecha_evento DESC, r.id DESC'
        )->fetchAll();
    } else {
        $stmt = $pdo->prepare(
            'SELECT r.id, r.fecha_evento, r.descripcion, r.estado, r.comentario_revision, r.creado_en,
                    GROUP_CONCAT(CONCAT(i.nombre, \' x\', CAST(d.cantidad AS UNSIGNED)) ORDER BY i.nombre SEPARATOR \', \') AS insumos_solicitados
             FROM reservas r
             LEFT JOIN detalle_reserva d ON d.id_reserva = r.id
             LEFT JOIN insumos i ON i.id = d.id_insumo
             WHERE r.usuario_id = ?
             GROUP BY r.id, r.fecha_evento, r.descripcion, r.estado, r.comentario_revision, r.creado_en
             ORDER BY r.fecha_evento DESC, r.id DESC'
        );
        $stmt->execute([$userId]);
        $reservas = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    if ($canStaff) {
        $reservas = $pdo->query(
            'SELECT r.id, r.fecha_evento, r.descripcion, r.estado, r.comentario_revision, r.creado_en,
                    u.nombre_completo AS solicitante_nombre, u.usuario AS solicitante_usuario,
                    NULL AS insumos_solicitados
             FROM reservas r
             INNER JOIN usuarios u ON u.id = r.usuario_id
             ORDER BY r.fecha_evento DESC, r.id DESC'
        )->fetchAll();
    } else {
        $stmt = $pdo->prepare(
            'SELECT r.id, r.fecha_evento, r.descripcion, r.estado, r.comentario_revision, r.creado_en,
                    NULL AS insumos_solicitados
             FROM reservas r
             WHERE r.usuario_id = ?
             ORDER BY r.fecha_evento DESC, r.id DESC'
        );
        $stmt->execute([$userId]);
        $reservas = $stmt->fetchAll();
    }
    $error = $error === '' ? 'Actualiza la base de datos para habilitar el detalle de insumos por reserva.' : $error;
}

$estadoEtiqueta = static function (string $e): string {
    return match ($e) {
        'pendiente' => 'Pendiente',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        'cancelada' => 'Cancelada',
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

<?php if ($isResident): ?>
    <p>
        <a class="btn btn-primary" href="<?= htmlspecialchars(url('reservas/nueva.php'), ENT_QUOTES, 'UTF-8') ?>">Nueva reserva</a>
    </p>
<?php endif; ?>

<div class="table-wrap">
    <table class="data-table">
        <thead>
        <tr>
            <th>Fecha del evento</th>
            <?php if ($canStaff): ?>
                <th>Solicitante</th>
            <?php endif; ?>
            <th>Descripción</th>
            <th>Insumos</th>
            <th>Estado</th>
            <th>Observaciones</th>
            <th>Acciones</th>
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
                <td><?= $row['insumos_solicitados'] !== null && $row['insumos_solicitados'] !== ''
                    ? htmlspecialchars((string) $row['insumos_solicitados'], ENT_QUOTES, 'UTF-8')
                    : '—' ?></td>
                <td><?= htmlspecialchars($estadoEtiqueta((string) $row['estado']), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $row['comentario_revision'] !== null && $row['comentario_revision'] !== ''
                    ? nl2br(htmlspecialchars((string) $row['comentario_revision'], ENT_QUOTES, 'UTF-8'))
                    : '—' ?></td>
                <td class="actions">
                    <?php if ($canStaff && $row['estado'] === 'pendiente'): ?>
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
                    <?php endif; ?>

                    <?php if (
                        (!$canStaff && in_array((string) $row['estado'], ['pendiente', 'aprobada'], true))
                        || ($canStaff && (string) $row['estado'] === 'aprobada')
                    ): ?>
                        <form method="post" class="inline-form" onsubmit="return confirm('¿Cancelar esta reserva?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="cancelar">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline">Cancelar</button>
                        </form>
                    <?php endif; ?>

                    <?php if (!in_array((string) $row['estado'], ['pendiente', 'aprobada'], true)): ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php
        $colspan = $canStaff ? 7 : 6;
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
