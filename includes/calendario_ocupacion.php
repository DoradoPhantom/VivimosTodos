<?php
declare(strict_types=1);

const MESES_CALENDARIO_ATRAS = 3;
const MESES_CALENDARIO_ADELANTE = 6;

function calendario_obtener_eventos(PDO $pdo, DateTimeImmutable $desde, DateTimeImmutable $hasta, ?array $currentUser = null): array
{
    $lista = [];
    $fechaFinCol = false;
    try {
        $pdo->query('SELECT fecha_fin FROM reservas LIMIT 1');
        $fechaFinCol = true;
    } catch (PDOException $e) {
    }
    $cols = $fechaFinCol
        ? 'id, usuario_id, fecha_evento, fecha_fin, descripcion, estado'
        : 'id, usuario_id, fecha_evento, descripcion, estado';
    try {
        $stmt = $pdo->prepare(
            "SELECT $cols
             FROM reservas
             WHERE estado IN ('pendiente', 'aprobada')
               AND fecha_evento >= ?
               AND fecha_evento <= ?
             ORDER BY fecha_evento ASC"
        );
        $stmt->execute([
            $desde->format('Y-m-d H:i:s'),
            $hasta->format('Y-m-d H:i:s'),
        ]);
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) ($row['id'] ?? 0);
            $fechaRaw = (string) ($row['fecha_evento'] ?? '');
            if ($id <= 0 || $fechaRaw === '') {
                continue;
            }
            $desc = (string) ($row['descripcion'] ?? '');
            $ownerId = (int) ($row['usuario_id'] ?? 0);
            $puedeVerDetalle = false;
            if ($currentUser !== null) {
                $userId = (int) ($currentUser['id'] ?? 0);
                $rol = (string) ($currentUser['rol'] ?? '');
                $puedeVerDetalle = ($userId === $ownerId) || in_array($rol, ['administrador', 'supervisor'], true);
            }
            $lista[] = [
                'id' => $id,
                'usuario_id' => $ownerId,
                'fecha' => $fechaRaw,
                'fecha_fin' => $fechaFinCol ? ($row['fecha_fin'] ?? null) : null,
                'titulo' => calendario_evento_titulo($desc),
                'estado' => (string) ($row['estado'] ?? ''),
                'descripcion' => $puedeVerDetalle ? $desc : '',
            ];
        }
    } catch (PDOException $e) {
        return [];
    }
    return $lista;
}

function calendario_evento_titulo(string $descripcion): string
{
    $descripcion = trim($descripcion);
    if ($descripcion === '') {
        return 'Reserva del salón';
    }
    $linea = strtok($descripcion, "\n");
    if ($linea === false || trim($linea) === '') {
        return 'Reserva del salón';
    }
    $linea = trim($linea);
    if (str_starts_with($linea, 'Asistentes solicitados:')) {
        return 'Reserva del salón';
    }
    if (strlen($linea) > 48) {
        return substr($linea, 0, 45) . '…';
    }
    return $linea;
}

function calendario_render(array $opts): void
{
    $eventos = $opts['eventos'] ?? [];
    $puedeReservar = (bool) ($opts['puede_reservar'] ?? false);
    $csrf = (string) ($opts['csrf'] ?? '');
    $insumos = $opts['insumos'] ?? [];
    $asistentesVal = max(1, (int) ($opts['asistentes_val'] ?? 1));
    $descVal = (string) ($opts['desc_val'] ?? '');
    $insumoCantidades = $opts['insumo_cantidades'] ?? [];
    $fechaVal = (string) ($opts['fecha_val'] ?? '');
    $minDate = (string) ($opts['min_date'] ?? '');
    $maxDate = (string) ($opts['max_date'] ?? '');
    $hoy = (string) ($opts['hoy'] ?? '');
    $reopenDate = (string) ($opts['reopen_date'] ?? '');
    $userId = (int) ($opts['user_id'] ?? 0);
    $errorMsg = (string) ($opts['error'] ?? '');

    $eventosJson = json_encode(
        $eventos,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if ($eventosJson === false) {
        $eventosJson = '[]';
    }
    ?>
    <section class="gcal-wrap" id="calendario-reservas">
        <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-error gcal-error"><?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div
            class="gcal"
            data-events="<?= htmlspecialchars($eventosJson, ENT_QUOTES, 'UTF-8') ?>"
            data-hoy="<?= htmlspecialchars($hoy, ENT_QUOTES, 'UTF-8') ?>"
            data-min="<?= htmlspecialchars($minDate, ENT_QUOTES, 'UTF-8') ?>"
            data-max="<?= htmlspecialchars($maxDate, ENT_QUOTES, 'UTF-8') ?>"
            data-can-reserve="<?= $puedeReservar ? '1' : '0' ?>"
            data-current-user="<?= $userId ?>"
            <?php if ($reopenDate !== ''): ?>data-reopen-date="<?= htmlspecialchars($reopenDate, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
            <?php if ($errorMsg !== ''): ?>data-error="<?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
        >
            <header class="gcal-toolbar">
                <div class="gcal-toolbar-start">
                    <?php if ($puedeReservar): ?>
                    <button type="button" class="gcal-btn gcal-btn-create" data-gcal-create>Crear</button>
                    <?php endif; ?>
                    <button type="button" class="gcal-btn gcal-btn-today" data-gcal-today>Hoy</button>
                    <div class="gcal-nav" aria-label="Navegación del calendario">
                        <button type="button" class="gcal-btn gcal-btn-icon" data-gcal-prev aria-label="Mes anterior">‹</button>
                        <button type="button" class="gcal-btn gcal-btn-icon" data-gcal-next aria-label="Mes siguiente">›</button>
                    </div>
                    <h2 class="gcal-title" data-gcal-title></h2>
                </div>
                <div class="gcal-legend" aria-label="Leyenda">
                    <span class="gcal-legend-item"><i class="gcal-dot gcal-dot-aprobada"></i> Aprobada</span>
                    <span class="gcal-legend-item"><i class="gcal-dot gcal-dot-pendiente"></i> Pendiente</span>
                    <span class="gcal-legend-item"><i class="gcal-dot gcal-dot-ocupado"></i> Ocupado</span>
                </div>
            </header>
            <div class="gcal-weekdays" aria-hidden="true">
                <span>LUN</span><span>MAR</span><span>MIÉ</span><span>JUE</span><span>VIE</span><span>SÁB</span><span>DOM</span>
            </div>
            <div class="gcal-grid" data-gcal-grid role="grid" aria-label="Calendario mensual"></div>
        </div>
        <?php if ($puedeReservar): ?>
            <p class="gcal-hint muted">Haz clic en un día libre para solicitar una reserva. Haz clic en un evento para ver el detalle.</p>
        <?php else: ?>
            <p class="gcal-hint muted">Consulta la ocupación del salón. Haz clic en un evento para ver el detalle.</p>
        <?php endif; ?>
    </section>

    <div class="gcal-popover" id="gcal-event-detail" hidden>
        <div class="gcal-popover-backdrop" data-gcal-close></div>
        <article class="gcal-popover-card" role="dialog" aria-modal="true" aria-labelledby="gcal-detail-title">
            <header class="gcal-popover-head">
                <span class="gcal-popover-bar" data-gcal-detail-bar></span>
                <button type="button" class="gcal-popover-close" data-gcal-close aria-label="Cerrar">×</button>
            </header>
            <div class="gcal-popover-body">
                <h3 id="gcal-detail-title" class="gcal-popover-title" data-gcal-detail-title></h3>
                <p class="gcal-popover-meta" data-gcal-detail-fecha></p>
                <p class="gcal-popover-estado" data-gcal-detail-estado></p>
                <pre class="gcal-popover-desc" data-gcal-detail-desc></pre>
            </div>
        </article>
    </div>

    <?php if ($puedeReservar): ?>
    <div class="gcal-popover" id="gcal-reserva-modal" hidden>
        <div class="gcal-popover-backdrop" data-gcal-close></div>
        <article class="gcal-popover-card gcal-popover-card--form" role="dialog" aria-modal="true" aria-labelledby="gcal-reserva-title">
            <header class="gcal-popover-head gcal-popover-head--form">
                <span class="gcal-popover-bar gcal-popover-bar--new"></span>
                <h3 id="gcal-reserva-title" class="gcal-popover-form-title">Nueva reserva</h3>
                <button type="button" class="gcal-popover-close" data-gcal-close aria-label="Cerrar">×</button>
            </header>
            <div class="gcal-form-error" data-gcal-form-error hidden></div>
            <form method="post" class="gcal-form" id="gcal-reserva-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="fecha_evento" id="gcal-fecha-hidden" value="<?= htmlspecialchars($fechaVal, ENT_QUOTES, 'UTF-8') ?>">

                <div class="gcal-form-row gcal-form-row--date">
                    <span class="gcal-form-icon" aria-hidden="true">📅</span>
                    <div>
                        <p class="gcal-form-date-label" id="gcal-date-label" data-gcal-date-label></p>
                        <p class="muted gcal-form-rules">Mínimo 48 h y máximo 90 días de anticipación.</p>
                    </div>
                </div>

                <div class="gcal-form-row gcal-form-row--time">
                    <span class="gcal-form-icon" aria-hidden="true">🕐</span>
                    <div class="gcal-time-pickers">
                        <label class="gcal-time-field">
                            <span class="gcal-form-label">Hora</span>
                            <select id="gcal-hour" data-gcal-hour></select>
                            <span class="gcal-pm-label">pm</span>
                        </label>
                        <span class="gcal-time-sep">:</span>
                        <label class="gcal-time-field">
                            <span class="sr-only">Minutos</span>
                            <select id="gcal-minute" data-gcal-minute></select>
                        </label>
                        <small class="muted gcal-form-rules">Horario: 12:00 p. m. a 11:59 p. m.</small>
                    </div>
                </div>

                <label class="gcal-form-row">
                    <span class="gcal-form-icon" aria-hidden="true">👥</span>
                    <span class="gcal-form-field">
                        <span class="gcal-form-label">Aforo estimado</span>
                        <input type="number" name="asistentes" min="1" max="<?= VENUE_MAX_CAPACITY ?>" required value="<?= $asistentesVal ?>">
                        <small class="muted">Capacidad máxima: <?= VENUE_MAX_CAPACITY ?> personas.</small>
                    </span>
                </label>

                <?php if (count($insumos) > 0): ?>
                <details class="gcal-form-details full">
                    <summary>Insumos (opcional)</summary>
                    <div class="insumos-grid">
                        <?php foreach ($insumos as $ins): ?>
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
                </details>
                <?php endif; ?>

                <label class="gcal-form-row gcal-form-row--stack">
                    <span class="gcal-form-icon" aria-hidden="true">📝</span>
                    <span class="gcal-form-field">
                        <span class="gcal-form-label">Notas (opcional)</span>
                        <textarea name="descripcion" rows="3" placeholder="Tipo de evento, detalles…"><?= htmlspecialchars($descVal, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </span>
                </label>

                <footer class="gcal-form-actions">
                    <a class="btn btn-outline btn-sm" href="<?= htmlspecialchars(url('reservas/index.php'), ENT_QUOTES, 'UTF-8') ?>">Mis reservas</a>
                    <button type="submit" class="btn btn-primary">Enviar solicitud</button>
                </footer>
            </form>
        </article>
    </div>
    <?php endif; ?>
    <?php
}
