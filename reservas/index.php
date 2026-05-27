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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        $rid = (int) ($_POST['id'] ?? 0);

        if ($canStaff && $rid > 0 && ($action === 'aprobar' || $action === 'rechazar')) {
            $comentario = trim((string) ($_POST['comentario_revision'] ?? ''));
            if ($action === 'rechazar' && $comentario === '') {
                $error = 'Indica un motivo al rechazar la reserva.';
            } else {
                $nuevoEstado = $action === 'aprobar' ? 'aprobada' : 'rechazada';
                try {
                    $pdo->beginTransaction();

                    $stmtCheck = $pdo->prepare("SELECT estado FROM reservas WHERE id = ? FOR UPDATE");
                    $stmtCheck->execute([$rid]);
                    $rowCheck = $stmtCheck->fetch();
                    if (!$rowCheck || $rowCheck['estado'] !== 'pendiente') {
                        $error = 'No se pudo actualizar (¿ya estaba revisada?).';
                        $pdo->rollBack();
                    } else {
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

                        if ($action === 'aprobar') {
                            reserva_descontar_stock($pdo, $rid);
                        } elseif ($action === 'rechazar') {
                            reserva_reponer_stock($pdo, $rid);
                        }

                        $pdo->commit();
                        $message = $action === 'aprobar' ? 'Reserva autorizada. Stock descontado.' : 'Reserva rechazada.';
                    }
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $error = 'Error al procesar la solicitud.';
                }
            }
        }

        if ($rid > 0 && $action === 'cancelar' && !$canStaff) {
            try {
                $stmtCheck = $pdo->prepare("SELECT usuario_id, estado FROM reservas WHERE id = ?");
                $stmtCheck->execute([$rid]);
                $rowCheck = $stmtCheck->fetch();
                if (!$rowCheck) {
                    $error = 'Reserva no encontrada.';
                } elseif ((int) $rowCheck['usuario_id'] !== $userId) {
                    $error = 'No puedes cancelar una reserva que no te pertenece.';
                } elseif ($rowCheck['estado'] !== 'pendiente') {
                    $error = 'Solo puedes cancelar reservas pendientes.';
                } else {
                    $stmt = $pdo->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id = ? AND estado = 'pendiente'");
                    $stmt->execute([$rid]);
                    if ($stmt->rowCount() > 0) {
                        reserva_reponer_stock($pdo, $rid);
                        $message = 'Reserva cancelada correctamente.';
                    } else {
                        $error = 'No se pudo cancelar la reserva.';
                    }
                }
            } catch (Throwable $e) {
                $error = 'Error al cancelar la reserva.';
            }
        }
    }
}

$filtroEstado = trim((string) ($_GET['estado'] ?? ''));
$filtroDesde = trim((string) ($_GET['desde'] ?? ''));
$filtroHasta = trim((string) ($_GET['hasta'] ?? ''));
$orden = trim((string) ($_GET['orden'] ?? 'fecha_desc'));

$where = [];
$params = [];

if (!$canStaff) {
    $where[] = 'r.usuario_id = ?';
    $params[] = $userId;
}

if ($filtroEstado !== '' && in_array($filtroEstado, ['pendiente', 'aprobada', 'rechazada', 'cancelada'], true)) {
    $where[] = 'r.estado = ?';
    $params[] = $filtroEstado;
}

if ($filtroDesde !== '') {
    $where[] = 'r.fecha_evento >= ?';
    $params[] = $filtroDesde . ' 00:00:00';
}

if ($filtroHasta !== '') {
    $where[] = 'r.fecha_evento <= ?';
    $params[] = $filtroHasta . ' 23:59:59';
}

$sqlOrder = match ($orden) {
    'fecha_asc' => 'r.fecha_evento ASC, r.id ASC',
    'creado_desc' => 'r.creado_en DESC',
    'creado_asc' => 'r.creado_en ASC',
    'estado' => 'r.estado ASC, r.fecha_evento DESC',
    default => 'r.fecha_evento DESC, r.id DESC',
};

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    if ($canStaff) {
        $cols = 'r.id, r.fecha_evento, r.fecha_fin, r.descripcion, r.estado, r.comentario_revision, r.creado_en,
                 u.nombre_completo AS solicitante_nombre, u.usuario AS solicitante_usuario';
        $join = 'INNER JOIN usuarios u ON u.id = r.usuario_id';
        $sql = "SELECT $cols FROM reservas r $join $whereClause ORDER BY $sqlOrder";
    } else {
        $cols = 'r.id, r.fecha_evento, r.fecha_fin, r.descripcion, r.estado, r.comentario_revision, r.creado_en';
        $sql = "SELECT $cols FROM reservas r $whereClause ORDER BY $sqlOrder";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservas = $stmt->fetchAll();
} catch (PDOException $e) {
    $reservas = $pdo->query(
        'SELECT r.id, r.fecha_evento, r.descripcion, r.estado, r.comentario_revision, r.creado_en,
                u.nombre_completo AS solicitante_nombre, u.usuario AS solicitante_usuario
         FROM reservas r
         INNER JOIN usuarios u ON u.id = r.usuario_id
         ORDER BY r.fecha_evento DESC, r.id DESC'
    )->fetchAll();
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

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="reservas-toolbar">
    <a class="btn btn-primary" href="<?= htmlspecialchars(url('reservas/nueva.php'), ENT_QUOTES, 'UTF-8') ?>">Nueva reserva</a>
    <form method="get" class="reservas-filtros">
        <div class="filtro-group">
            <label class="filtro-label">Estado</label>
            <select name="estado" class="filtro-select">
                <option value="">Todos</option>
                <option value="pendiente"<?= $filtroEstado === 'pendiente' ? ' selected' : '' ?>>Pendiente</option>
                <option value="aprobada"<?= $filtroEstado === 'aprobada' ? ' selected' : '' ?>>Aprobada</option>
                <option value="rechazada"<?= $filtroEstado === 'rechazada' ? ' selected' : '' ?>>Rechazada</option>
                <option value="cancelada"<?= $filtroEstado === 'cancelada' ? ' selected' : '' ?>>Cancelada</option>
            </select>
        </div>
        <div class="filtro-group">
            <label class="filtro-label">Desde</label>
            <input type="date" name="desde" class="filtro-input" value="<?= htmlspecialchars($filtroDesde, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="filtro-group">
            <label class="filtro-label">Hasta</label>
            <input type="date" name="hasta" class="filtro-input" value="<?= htmlspecialchars($filtroHasta, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="filtro-group">
            <label class="filtro-label">Ordenar</label>
            <select name="orden" class="filtro-select">
                <option value="fecha_desc"<?= $orden === 'fecha_desc' ? ' selected' : '' ?>>Fecha más reciente</option>
                <option value="fecha_asc"<?= $orden === 'fecha_asc' ? ' selected' : '' ?>>Fecha más antigua</option>
                <option value="creado_desc"<?= $orden === 'creado_desc' ? ' selected' : '' ?>>Creado recientemente</option>
                <option value="creado_asc"<?= $orden === 'creado_asc' ? ' selected' : '' ?>>Creado primero</option>
                <option value="estado"<?= $orden === 'estado' ? ' selected' : '' ?>>Por estado</option>
            </select>
        </div>
        <div class="filtro-actions">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a class="btn btn-sm btn-outline" href="<?= htmlspecialchars(url('reservas/index.php'), ENT_QUOTES, 'UTF-8') ?>">Limpiar</a>
        </div>
    </form>
</div>

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
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($reservas as $row): ?>
            <tr class="reserva-row reserva-row--<?= htmlspecialchars((string) $row['estado'], ENT_QUOTES, 'UTF-8') ?>">
                <td><?= htmlspecialchars(reserva_formato_tabla((string) $row['fecha_evento']), ENT_QUOTES, 'UTF-8') ?></td>
                <?php if ($canStaff): ?>
                    <td>
                        <?= htmlspecialchars($row['solicitante_nombre'], ENT_QUOTES, 'UTF-8') ?>
                        <br><small class="muted"><?= htmlspecialchars($row['solicitante_usuario'], ENT_QUOTES, 'UTF-8') ?></small>
                    </td>
                <?php endif; ?>
                <td class="reserva-desc-cell"><?= $row['descripcion'] !== null && $row['descripcion'] !== ''
                    ? '<details><summary>Ver detalle</summary><pre class="reserva-desc-pre">' . htmlspecialchars((string) $row['descripcion'], ENT_QUOTES, 'UTF-8') . '</pre></details>'
                    : '—' ?></td>
                <td><span class="chip chip-<?= htmlspecialchars((string) $row['estado'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($estadoEtiqueta((string) $row['estado']), ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><?= $row['comentario_revision'] !== null && $row['comentario_revision'] !== ''
                    ? nl2br(htmlspecialchars((string) $row['comentario_revision'], ENT_QUOTES, 'UTF-8'))
                    : '—' ?></td>
                <td class="actions">
                    <?php if ($canStaff): ?>
                        <?php if ($row['estado'] === 'pendiente'): ?>
                            <form method="post" class="inline-form" onsubmit="return confirm('¿Autorizar esta reserva? Se descontará el stock solicitado.');">
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
                    <?php else: ?>
                        <?php if ($row['estado'] === 'pendiente'): ?>
                            <form method="post" class="inline-form" onsubmit="return confirm('¿Cancelar esta reserva?');">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="cancelar">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline">Cancelar</button>
                            </form>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (count($reservas) === 0): ?>
            <tr><td colspan="6" class="empty-cell">No hay reservas registradas con los filtros actuales.</td></tr>
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
