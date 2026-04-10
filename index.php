<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/reservas_rules.php';
require_login();

$pdo = db();
$rol = (string) (current_user()['rol'] ?? '');
$canSeeCalendar = in_array($rol, ['residente', 'supervisor'], true);
$canQuickReserve = $rol === 'residente';

$error = '';
$message = '';
$fechaVal = trim((string) ($_POST['fecha_evento'] ?? ''));
$descVal = trim((string) ($_POST['descripcion'] ?? ''));
$asistentesVal = max(1, (int) ($_POST['asistentes'] ?? 1));
$insumoCantidades = [];
$tz = new DateTimeZone(date_default_timezone_get());

$hoy = new DateTimeImmutable('today', $tz);
$inicioCalendario = $hoy->modify('first day of this month');
$finVentana = $hoy->add(new DateInterval('P90D'))->setTime(23, 59, 59);

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

$insumosDisponibles = [];
if ($canQuickReserve) {
    try {
        $stmtInsumos = $pdo->query(
            'SELECT id, nombre, categoria, cantidad_stock, unidad_medida
             FROM insumos
             WHERE activo = 1
             ORDER BY nombre ASC'
        );
        foreach ($stmtInsumos->fetchAll() as $ins) {
            $iid = (int) ($ins['id'] ?? 0);
            if ($iid <= 0) {
                continue;
            }
            $stock = max(0, (int) round((float) ($ins['cantidad_stock'] ?? 0)));
            $insumosDisponibles[$iid] = [
                'id' => $iid,
                'nombre' => (string) ($ins['nombre'] ?? ''),
                'categoria' => (string) ($ins['categoria'] ?? ''),
                'stock' => $stock,
                'unidad' => (string) ($ins['unidad_medida'] ?? 'unidad'),
            ];
        }
    } catch (PDOException $e) {
        $insumosDisponibles = [];
    }
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

if ($canQuickReserve && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Token de seguridad inválido.';
    } else {
        $rawCantidades = $_POST['insumo_cantidad'] ?? [];
        if (is_array($rawCantidades)) {
            foreach ($rawCantidades as $iidRaw => $cantRaw) {
                $iid = (int) $iidRaw;
                if ($iid <= 0 || !isset($insumosDisponibles[$iid])) {
                    continue;
                }
                $cant = max(0, (int) $cantRaw);
                if ($cant === 0) {
                    continue;
                }
                $stock = (int) $insumosDisponibles[$iid]['stock'];
                if ($cant > $stock) {
                    $error = 'No hay stock suficiente para "' . $insumosDisponibles[$iid]['nombre'] . '". Disponible: ' . $stock . '.';
                    break;
                }
                $insumoCantidades[$iid] = $cant;
            }
        }
        if ($error === '' && $asistentesVal > VENUE_MAX_CAPACITY) {
            $error = 'La capacidad máxima del salón es de ' . VENUE_MAX_CAPACITY . ' personas.';
        }

        $evento = reserva_parse_datetime_local($fechaVal);
        if ($error === '' && $evento === null) {
            $error = 'Indica fecha y hora del evento.';
        } elseif ($error === '') {
            $ahora = new DateTimeImmutable('now');
            $ventana = reserva_validar_ventana_temporal($evento, $ahora);
            if ($ventana !== null) {
                $error = $ventana;
            } else {
                $fmtDb = $evento->format('Y-m-d H:i:s');
                $lineas = [];
                $lineas[] = 'Asistentes solicitados: ' . $asistentesVal . ' (capacidad máxima: ' . VENUE_MAX_CAPACITY . ').';
                if (count($insumoCantidades) > 0) {
                    $lineas[] = 'Insumos solicitados:';
                    foreach ($insumoCantidades as $iid => $cant) {
                        $ins = $insumosDisponibles[$iid];
                        $lineas[] = '- ' . $cant . ' x ' . $ins['nombre'] . ' (stock actual: ' . $ins['stock'] . ')';
                    }
                } else {
                    $lineas[] = 'Insumos solicitados: ninguno.';
                }
                if ($descVal !== '') {
                    $lineas[] = '';
                    $lineas[] = 'Notas del solicitante:';
                    $lineas[] = $descVal;
                } 
                $descripcionCompleta = implode("\n", $lineas);
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO reservas (usuario_id, fecha_evento, descripcion, estado) VALUES (?, ?, ?, \'pendiente\')'
                    );
                    $stmt->execute([
                        (int) current_user()['id'],
                        $fmtDb,
                        $descripcionCompleta,
                    ]);
                    $message = 'Solicitud enviada. Un administrador o supervisor la revisará.';
                    $fechaVal = '';
                    $descVal = '';
                    $asistentesVal = 1;
                    $insumoCantidades = [];
                } catch (PDOException $e) {
                    $error = 'No se pudo guardar la reserva. Verifica la tabla reservas en la base de datos.';
                }
            }
        }
    }
}

$pageTitle = 'Inicio';
require __DIR__ . '/includes/header.php';
?>
<h1>Bienvenido</h1>
<p class="lead">Panel principal del sistema.</p>

<?php if ($canSeeCalendar): ?>
    <section class="reserva-calendario-wrap">
        <h2>Calendario de ocupación</h2>
        <p class="muted">Rojo: día con reservas aprobadas. Amarillo: día con solicitudes pendientes.</p>
        <div class="reserva-legend">
            <span class="chip chip-aprobada">Aprobada</span>
            <span class="chip chip-pendiente">Pendiente</span>
            <span class="chip">Disponible</span>
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
                            if (isset($ocupacionPorDia[$fechaKey])) {
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

    <?php if ($canQuickReserve): ?>
        <?php if ($message !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" class="form-grid section">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <h2 class="full">Solicitar reserva</h2>
            <label>Aforo estimado (personas)
                <input type="number" name="asistentes" min="1" max="<?= VENUE_MAX_CAPACITY ?>" required value="<?= (int) $asistentesVal ?>">
                <small class="muted">Capacidad máxima del salón: <?= VENUE_MAX_CAPACITY ?> personas.</small>
            </label>
            <label class="full">Fecha y hora del evento
                <input type="datetime-local" name="fecha_evento" required value="<?= htmlspecialchars($fechaVal, ENT_QUOTES, 'UTF-8') ?>">
            </label>
            <div class="full">
                <label>Insumos solicitados (opcional)</label>
                <?php if (count($insumosDisponibles) > 0): ?>
                    <div class="insumos-grid">
                        <?php foreach ($insumosDisponibles as $ins): ?>
                            <?php $iid = (int) $ins['id']; ?>
                            <label class="insumo-item">
                                <span class="insumo-titulo"><?= htmlspecialchars($ins['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                                <small class="muted">Disp.: <?= (int) $ins['stock'] ?><?= $ins['categoria'] !== '' ? ' · ' . htmlspecialchars($ins['categoria'], ENT_QUOTES, 'UTF-8') : '' ?></small>
                                <input
                                    type="number"
                                    name="insumo_cantidad[<?= $iid ?>]"
                                    min="0"
                                    max="<?= (int) $ins['stock'] ?>"
                                    value="<?= isset($insumoCantidades[$iid]) ? (int) $insumoCantidades[$iid] : 0 ?>"
                                >
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">No hay insumos cargados aún en el catálogo.</p>
                <?php endif; ?>
            </div>
            <label class="full">Descripción (opcional)
                <textarea name="descripcion" rows="3" placeholder="Tipo de evento, número de asistentes, etc."><?= htmlspecialchars($descVal, ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
            <div class="form-actions full">
                <button type="submit" class="btn btn-primary">Enviar solicitud</button>
                <a class="btn btn-outline" href="<?= htmlspecialchars(url('reservas/index.php'), ENT_QUOTES, 'UTF-8') ?>">Ver mis reservas</a>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
