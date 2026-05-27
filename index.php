<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/reservas_rules.php';
require_once __DIR__ . '/includes/calendario_ocupacion.php';
require_login();

$pdo = db();
$rol = (string) (current_user()['rol'] ?? '');
$canSeeCalendar = in_array($rol, ['residente', 'supervisor', 'administrador'], true);
$canQuickReserve = in_array($rol, ['residente', 'supervisor', 'administrador'], true);

$error = '';
$message = '';
$fechaVal = trim((string) ($_POST['fecha_evento'] ?? ''));
$descVal = trim((string) ($_POST['descripcion'] ?? ''));
$asistentesVal = max(1, (int) ($_POST['asistentes'] ?? 1));
$insumoCantidades = [];
$tz = new DateTimeZone(date_default_timezone_get());

$hoy = new DateTimeImmutable('today', $tz);
$ahora = new DateTimeImmutable('now', $tz);
$inicioCalendario = $hoy->modify('-'.MESES_CALENDARIO_ATRAS.' months')->modify('first day of this month')->setTime(0, 0, 0);
$finVentana = $ahora->add(new DateInterval('P90D'))->setTime(23, 59, 59);
$finCalendario = $ahora->add(new DateInterval('P' . MESES_CALENDARIO_ADELANTE . 'M'))->setTime(23, 59, 59);
$maxDateCalendario = $finCalendario->format('Y-m-d');

$minReserva = $ahora->add(new DateInterval('PT48H'));
$maxReserva = $ahora->add(new DateInterval('P90D'));

$eventosCalendario = [];
if ($canSeeCalendar) {
    $eventosCalendario = calendario_obtener_eventos($pdo, $inicioCalendario, $finCalendario, current_user());
}

$insumosDisponibles = [];
if ($canQuickReserve) {
    $insumosQuery = 'SELECT id, nombre, categoria, cantidad_stock, unidad_medida, estado_operativo
                     FROM insumos
                     WHERE activo = 1
                     ORDER BY nombre ASC';
    try {
        $stmtInsumos = $pdo->query($insumosQuery);
        foreach ($stmtInsumos->fetchAll() as $ins) {
            $estado = (string) ($ins['estado_operativo'] ?? '');
            if ($estado === 'danado' || $estado === 'reparacion') {
                continue;
            }
            $iid = (int) ($ins['id'] ?? 0);
            if ($iid <= 0) {
                continue;
            }
            $stock = max(0, (int) round((float) ($ins['cantidad_stock'] ?? 0)));
            if ($stock <= 0) {
                continue;
            }
            $insumosDisponibles[$iid] = [
                'id' => $iid,
                'nombre' => (string) ($ins['nombre'] ?? ''),
                'categoria' => (string) ($ins['categoria'] ?? ''),
                'stock' => $stock,
                'unidad' => (string) ($ins['unidad_medida'] ?? 'unidad'),
            ];
        }
    } catch (PDOException $e) {
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
                if ($stock <= 0) {
                    continue;
                }
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
            $ventana = reserva_validar_ventana_temporal($evento, $ahora);
            if ($ventana !== null) {
                $error = $ventana;
            } else {
                $errorHorario = reserva_validar_horario($evento);
                if ($errorHorario !== null) {
                    $error = $errorHorario;
                }
            }
        }

        if ($error === '') {
            $error = reserva_validar_no_duplicado($pdo, $evento, null) ?? '';
        }

        if ($error === '') {
            $fechaFin = reserva_calcular_fin($evento);
            $fmtDb = $evento->format('Y-m-d H:i:s');
            $fmtFin = $fechaFin->format('Y-m-d H:i:s');
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
                $pdo->beginTransaction();

                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO reservas (usuario_id, fecha_evento, fecha_fin, descripcion, estado) VALUES (?, ?, ?, ?, \'pendiente\')'
                    );
                    $stmt->execute([
                        (int) current_user()['id'],
                        $fmtDb,
                        $fmtFin,
                        $descripcionCompleta,
                    ]);
                } catch (PDOException $e) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO reservas (usuario_id, fecha_evento, descripcion, estado) VALUES (?, ?, ?, \'pendiente\')'
                    );
                    $stmt->execute([
                        (int) current_user()['id'],
                        $fmtDb,
                        $descripcionCompleta,
                    ]);
                }
                $reservaId = (int) $pdo->lastInsertId();

                if (count($insumoCantidades) > 0) {
                    try {
                        $stmtIns = $pdo->prepare(
                            'INSERT INTO reservas_insumos (reserva_id, insumo_id, cantidad_solicitada) VALUES (?, ?, ?)'
                        );
                        foreach ($insumoCantidades as $iid => $cant) {
                            $stmtIns->execute([$reservaId, $iid, $cant]);
                        }
                    } catch (PDOException $e) {
                    }
                }

                $pdo->commit();

                $message = 'Solicitud enviada. Un administrador o supervisor la revisará.';
                $fechaVal = '';
                $descVal = '';
                $asistentesVal = 1;
                $insumoCantidades = [];
                $eventosCalendario = calendario_obtener_eventos($pdo, $inicioCalendario, $finCalendario, current_user());
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'No se pudo guardar la reserva. Verifica la tabla reservas en la base de datos.';
            }
        }
    }
}

$reopenDate = '';
if ($error !== '' && $_SERVER['REQUEST_METHOD'] === 'POST' && $fechaVal !== '') {
    $reopenDate = substr($fechaVal, 0, 10);
}

$pageTitle = 'Inicio';
require __DIR__ . '/includes/header.php';
?>
<h1>Bienvenido</h1>
<p class="lead">Panel principal del sistema.</p>

<?php if ($canSeeCalendar): ?>
    <?php if ((string) $message !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php
    calendario_render([
        'eventos' => $eventosCalendario,
        'puede_reservar' => $canQuickReserve,
        'csrf' => csrf_token(),
        'insumos' => $insumosDisponibles,
        'asistentes_val' => $asistentesVal,
        'desc_val' => $descVal,
        'insumo_cantidades' => $insumoCantidades,
        'fecha_val' => $fechaVal,
        'min_date' => $minReserva->format('Y-m-d'),
        'max_date' => $maxReserva->format('Y-m-d'),
        'hoy' => $hoy->format('Y-m-d'),
        'reopen_date' => $reopenDate,
        'user_id' => (int) (current_user()['id'] ?? 0),
        'error' => $error,
    ]);
    ?>
    <script src="<?= htmlspecialchars(url('assets/js/calendario-gcal.js') . '?v=' . filemtime(__DIR__ . '/assets/js/calendario-gcal.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
