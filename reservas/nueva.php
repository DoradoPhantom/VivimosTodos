<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/reservas_rules.php';
require_login();

$pdo = db();
$error = '';
$errorList = [];
$fechaVal = trim((string) ($_POST['fecha_evento'] ?? ''));
$descVal = trim((string) ($_POST['descripcion'] ?? ''));
$cantidadesSolicitadas = is_array($_POST['insumos'] ?? null) ? $_POST['insumos'] : [];
$tz = new DateTimeZone(date_default_timezone_get());

$hoy = new DateTimeImmutable('today', $tz);
$ahoraCalendario = new DateTimeImmutable('now', $tz);
$inicioCalendario = $hoy->modify('first day of this month');
$finVentana = $hoy->add(new DateInterval('P90D'))->setTime(23, 59, 59);
$minReservaFecha = $ahoraCalendario->add(new DateInterval('PT48H'))->format('Y-m-d');
$maxReservaFecha = $ahoraCalendario->add(new DateInterval('P90D'))->format('Y-m-d');

$ocupacionPorDia = [];
try {
    $stmtDias = $pdo->prepare(
        'SELECT DATE(fecha_evento) AS dia, estado, COUNT(*) AS total
         FROM reservas
         WHERE estado IN (\'pendiente\', \'aprobada\')
           AND fecha_evento >= ?
           AND fecha_evento <= ?
         GROUP BY DATE(fecha_evento), estado'
    );
    $stmtDias->execute([
        $inicioCalendario->format('Y-m-d H:i:s'),
        $finVentana->format('Y-m-d H:i:s'),
    ]);
    foreach ($stmtDias->fetchAll() as $r) {
        $dia = (string) ($r['dia'] ?? '');
        if ($dia === '') {
            continue;
        }
        if (!isset($ocupacionPorDia[$dia])) {
            $ocupacionPorDia[$dia] = ['aprobada' => 0, 'pendiente' => 0];
        }
        $estado = (string) ($r['estado'] ?? '');
        if ($estado === 'aprobada' || $estado === 'pendiente') {
            $ocupacionPorDia[$dia][$estado] = (int) ($r['total'] ?? 0);
        }
    }
} catch (PDOException $e) {
    $ocupacionPorDia = [];
}

$proximosEventos = [];
$insumosDisponibles = [];
try {
    $stmtEventos = $pdo->prepare(
        'SELECT fecha_evento, descripcion, estado
         FROM reservas
         WHERE fecha_evento >= ?
           AND fecha_evento <= ?
           AND estado IN (\'pendiente\', \'aprobada\')
         ORDER BY fecha_evento ASC
         LIMIT 25'
    );
    $stmtEventos->execute([
        $hoy->format('Y-m-d 00:00:00'),
        $finVentana->format('Y-m-d H:i:s'),
    ]);
    $proximosEventos = $stmtEventos->fetchAll();
} catch (PDOException $e) {
    $proximosEventos = [];
}

try {
    $insumosDisponibles = reserva_insumos_disponibles($pdo);
} catch (PDOException $e) {
    $insumosDisponibles = [];
}

$mesesCalendario = [];
for ($i = 0; $i < 3; $i++) {
    $mes = $inicioCalendario->modify('+' . $i . ' month');
    $mesesCalendario[] = [
        'titulo' => ucfirst((string) $mes->format('F Y')),
        'inicio' => $mes,
        'dias' => (int) $mes->format('t'),
        'inicioSemana' => (int) $mes->format('N'),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $evento = reserva_parse_datetime_local($fechaVal);
        if ($evento === null) {
            $error = 'Indica fecha y hora del evento.';
        } else {
            $ahora = new DateTimeImmutable('now');
            $usuarioId = (int) (current_user()['id'] ?? 0);
            $ventana = reserva_validar_ventana_temporal($evento, $ahora);
            if ($ventana !== null) {
                $errorList[] = $ventana;
            }
            $horario = reserva_validar_horario_salon($evento);
            if ($horario !== null) {
                $errorList[] = $horario;
            }
            if (reserva_existe_para_usuario_en_dia($pdo, $usuarioId, $evento)) {
                $errorList[] = 'Ya tienes una reserva para este día.';
            }
            if (reserva_dia_ya_ocupado($pdo, $evento)) {
                $errorList[] = 'Ya existe una reserva para este día.';
            }

            $solicitudInsumos = reserva_validar_solicitud_insumos($insumosDisponibles, $cantidadesSolicitadas);
            if (count($solicitudInsumos['errors']) > 0) {
                $errorList = array_merge($errorList, $solicitudInsumos['errors']);
            }

            if (count($errorList) === 0) {
                $fmtDb = $evento->format('Y-m-d H:i:s');
                try {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare(
                        'INSERT INTO reservas (usuario_id, fecha_evento, descripcion, estado) VALUES (?, ?, ?, \'pendiente\')'
                    );
                    $stmt->execute([
                        $usuarioId,
                        $fmtDb,
                        $descVal === '' ? null : $descVal,
                    ]);
                    $reservaId = (int) $pdo->lastInsertId();
                    if (count($solicitudInsumos['items']) > 0) {
                        $stmtDet = $pdo->prepare(
                            'INSERT INTO detalle_reserva (id_reserva, id_insumo, cantidad) VALUES (?, ?, ?)'
                        );
                        foreach ($solicitudInsumos['items'] as $item) {
                            $stmtDet->execute([$reservaId, (int) $item['id_insumo'], (int) $item['cantidad']]);
                        }
                    }
                    $pdo->commit();
                    header('Location: ' . url('reservas/index.php'));
                    exit;
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'No se pudo guardar la reserva. Verifica que exista la tabla detalle_reserva en la base de datos.';
                }
            }
        }
    }
} else {
    $fechaVal = '';
}

$pageTitle = 'Nueva reserva';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1>Nueva reserva</h1>
<p class="muted">
    Reglas del sistema: al menos <strong>48 horas</strong> antes del evento y como máximo <strong>90 días</strong> desde hoy.
    No puede existir más de una reserva por usuario en el mismo día y el día no debe tener una reserva previa activa.
    El evento debe programarse en horario de funcionamiento del salón (12:00 PM a 12:00 AM).
    Pueden reservar residentes, administrador y supervisor; la aprobación la gestionan administrador y supervisor.
</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if (count($errorList) > 0): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars(implode(' ', $errorList), ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<section class="reserva-calendario-wrap">
    <h2>Calendario de ocupación</h2>
    <p class="muted">Verde: día con reservas aprobadas. Amarillo: día con solicitudes pendientes. Gris: no disponible (regla de 48 horas o más de 90 días).</p>
    <div class="reserva-legend">
        <span class="chip chip-aprobada">Aprobada</span>
        <span class="chip chip-pendiente">Pendiente</span>
        <span class="chip">Disponible</span>
        <span class="chip chip-no-disponible">No disponible</span>
    </div>
    <div class="reserva-calendarios">
        <?php foreach ($mesesCalendario as $mesInfo): ?>
            <article class="reserva-mes">
                <h3><?= htmlspecialchars($mesInfo['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="reserva-grid reserva-grid-head">
                    <span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span><span>Dom</span>
                </div>
                <div class="reserva-grid">
                    <?php for ($vac = 1; $vac < $mesInfo['inicioSemana']; $vac++): ?>
                        <span class="dia dia-vacio"></span>
                    <?php endfor; ?>
                    <?php for ($d = 1; $d <= $mesInfo['dias']; $d++):
                        $fecha = $mesInfo['inicio']->setDate(
                            (int) $mesInfo['inicio']->format('Y'),
                            (int) $mesInfo['inicio']->format('m'),
                            $d
                        );
                        $fechaKey = $fecha->format('Y-m-d');
                        $css = 'dia dia-libre';
                        $tooltip = 'Disponible';
                        $fueraVentana = ($fechaKey < $minReservaFecha) || ($fechaKey > $maxReservaFecha);
                        if ($fueraVentana) {
                            $css = 'dia dia-no-disponible';
                            $tooltip = 'No disponible por reglas de anticipación (48 horas) o límite máximo (90 días).';
                        } elseif (isset($ocupacionPorDia[$fechaKey])) {
                            $ap = (int) $ocupacionPorDia[$fechaKey]['aprobada'];
                            $pe = (int) $ocupacionPorDia[$fechaKey]['pendiente'];
                            if ($ap > 0) {
                                $css = 'dia dia-aprobada';
                                $tooltip = 'Aprobadas: ' . $ap . ($pe > 0 ? ' | Pendientes: ' . $pe : '');
                            } elseif ($pe > 0) {
                                $css = 'dia dia-pendiente';
                                $tooltip = 'Pendientes: ' . $pe;
                            }
                        }
                    ?>
                        <span class="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') ?>"><?= $d ?></span>
                    <?php endfor; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <h2>Próximos eventos programados</h2>
    <p class="muted">Se listan solicitudes pendientes y reservas aprobadas de los próximos 90 días.</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Fecha del evento</th>
                <th>Detalle</th>
                <th>Estado</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($proximosEventos as $ev): ?>
                <?php
                $estadoRaw = (string) ($ev['estado'] ?? '');
                $estadoTxt = $estadoRaw === 'aprobada'
                    ? 'Aprobada'
                    : ($estadoRaw === 'pendiente' ? 'Pendiente' : $estadoRaw);
                ?>
                <tr>
                    <td><?= htmlspecialchars(reserva_formato_tabla((string) $ev['fecha_evento']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= ($ev['descripcion'] ?? '') !== ''
                        ? nl2br(htmlspecialchars((string) $ev['descripcion'], ENT_QUOTES, 'UTF-8'))
                        : '—' ?></td>
                    <td><?= htmlspecialchars($estadoTxt, ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($proximosEventos) === 0): ?>
                <tr><td colspan="3">No hay eventos programados en la ventana actual.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<form method="post" class="form-grid">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <label class="full">Fecha y hora del evento
        <input type="datetime-local" name="fecha_evento" required value="<?= htmlspecialchars($fechaVal, ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label class="full">Descripción (opcional)
        <textarea name="descripcion" rows="3" placeholder="Tipo de evento, número de asistentes, etc."><?= htmlspecialchars($descVal, ENT_QUOTES, 'UTF-8') ?></textarea>
    </label>
    <fieldset class="full">
        <legend>Insumos requeridos (opcional)</legend>
        <p class="muted">Solicita solo cantidades disponibles; el descuento se hace cuando la reserva sea aprobada.</p>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Insumo</th>
                    <th>Categoría</th>
                    <th>Disponibles</th>
                    <th>Solicitar</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($insumosDisponibles as $insumo): ?>
                    <?php
                    $iid = (int) ($insumo['id'] ?? 0);
                    $stock = (int) round((float) ($insumo['cantidad_stock'] ?? 0));
                    $estado = (string) ($insumo['estado_operativo'] ?? 'disponible');
                    $valorAnterior = (int) ($cantidadesSolicitadas[$iid] ?? $cantidadesSolicitadas[(string) $iid] ?? 0);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($insumo['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($insumo['categoria'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $estado === 'disponible' ? $stock : 0 ?></td>
                        <td>
                            <input
                                type="number"
                                name="insumos[<?= $iid ?>]"
                                min="0"
                                max="<?= $estado === 'disponible' ? $stock : 0 ?>"
                                step="1"
                                value="<?= htmlspecialchars((string) max(0, $valorAnterior), ENT_QUOTES, 'UTF-8') ?>"
                                <?= $estado === 'disponible' ? '' : 'disabled' ?>
                            >
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($insumosDisponibles) === 0): ?>
                    <tr><td colspan="4">No hay insumos activos en el inventario.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </fieldset>
    <div class="form-actions full">
        <button type="submit" class="btn btn-primary">Enviar solicitud</button>
        <a class="btn btn-outline" href="<?= htmlspecialchars(url('reservas/index.php'), ENT_QUOTES, 'UTF-8') ?>">Volver</a>
    </div>
</form>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
