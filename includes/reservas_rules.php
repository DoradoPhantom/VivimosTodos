<?php
declare(strict_types=1);

/**
 * Reglas de negocio para fechas de reserva (≥48 h y ≤90 días desde ahora).
 */
function reserva_parse_datetime_local(string $value): ?DateTimeImmutable
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $tz = new DateTimeZone(date_default_timezone_get());
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, $tz);
    if ($dt instanceof DateTimeImmutable) {
        return $dt;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $tz);

    return $dt instanceof DateTimeImmutable ? $dt : null;
}

/** @return string|null mensaje de error o null si la fecha es válida */
function reserva_validar_ventana_temporal(DateTimeImmutable $evento, DateTimeImmutable $ahora): ?string
{
    $min = $ahora->add(new DateInterval('PT48H'));
    if ($evento < $min) {
        return 'El evento debe programarse con al menos 48 horas de anticipación.';
    }
    $max = $ahora->add(new DateInterval('P90D'));
    if ($evento > $max) {
        return 'No se pueden hacer reservas con más de 90 días de anticipación.';
    }

    return null;
}

/** Valida el horario operativo del salón: 12:00 PM a 11:59 PM. */
function reserva_validar_horario_salon(DateTimeImmutable $evento): ?string
{
    $hora = (int) $evento->format('H');
    if ($hora < 12) {
        return 'El salón solo funciona desde las 12:00 PM hasta las 12:00 AM.';
    }

    return null;
}

function reserva_formato_tabla(string $mysqlDatetime): string
{
    $tz = new DateTimeZone(date_default_timezone_get());
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, $tz);
    if (!$dt instanceof DateTimeImmutable) {
        return $mysqlDatetime;
    }

    return $dt->format('d/m/Y H:i');
}

function reserva_fecha_dia(DateTimeImmutable $evento): string
{
    return $evento->format('Y-m-d');
}

/** @return string|null mensaje de error o null si cumple la hora límite */
function reserva_validar_hora_limite(DateTimeImmutable $ahora): ?string
{
    $limite = $ahora->setTime(12, 0, 0);
    if ($ahora >= $limite) {
        return 'Las reservas solo se pueden registrar antes de las 12:00 PM.';
    }

    return null;
}

function reserva_existe_para_usuario_en_dia(PDO $pdo, int $usuarioId, DateTimeImmutable $evento): bool
{
    $dia = reserva_fecha_dia($evento);
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM reservas
         WHERE usuario_id = ?
           AND DATE(fecha_evento) = ?
           AND estado IN (\'pendiente\', \'aprobada\')
         LIMIT 1'
    );
    $stmt->execute([$usuarioId, $dia]);

    return (bool) $stmt->fetchColumn();
}

function reserva_dia_ya_ocupado(PDO $pdo, DateTimeImmutable $evento): bool
{
    $dia = reserva_fecha_dia($evento);
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM reservas
         WHERE DATE(fecha_evento) = ?
           AND estado IN (\'pendiente\', \'aprobada\')
         LIMIT 1'
    );
    $stmt->execute([$dia]);

    return (bool) $stmt->fetchColumn();
}

/** @return array<int, array<string, mixed>> */
function reserva_insumos_disponibles(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, nombre, categoria, cantidad_stock, estado_operativo
         FROM insumos
         WHERE activo = 1
         ORDER BY nombre ASC'
    );

    return $stmt->fetchAll();
}

/**
 * @param array<int, mixed> $requestQtyById
 * @return array{items: array<int, array{id_insumo:int, cantidad:int}>, errors: array<int, string>}
 */
function reserva_validar_solicitud_insumos(array $inventario, array $requestQtyById): array
{
    $items = [];
    $errors = [];
    foreach ($inventario as $insumo) {
        $id = (int) ($insumo['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $qtyRaw = $requestQtyById[$id] ?? $requestQtyById[(string) $id] ?? 0;
        $qty = (int) $qtyRaw;
        if ($qty <= 0) {
            continue;
        }
        $stock = (int) round((float) ($insumo['cantidad_stock'] ?? 0));
        $estado = (string) ($insumo['estado_operativo'] ?? 'disponible');
        $nombre = (string) ($insumo['nombre'] ?? ('Insumo #' . $id));
        if ($estado !== 'disponible') {
            $errors[] = 'El insumo "' . $nombre . '" no está disponible para reservar.';
            continue;
        }
        if ($qty > $stock) {
            $errors[] = 'No hay suficientes unidades de "' . $nombre . '". Disponibles: ' . $stock . '.';
            continue;
        }
        $items[] = [
            'id_insumo' => $id,
            'cantidad' => $qty,
        ];
    }

    return ['items' => $items, 'errors' => $errors];
}
