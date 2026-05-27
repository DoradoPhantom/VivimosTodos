<?php
declare(strict_types=1);

const HORA_MINIMA = 12;
const HORA_MAXIMA = 23;
const MINUTO_MAXIMO = 59;

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

function reserva_validar_horario(DateTimeImmutable $evento): ?string
{
    $hora = (int) $evento->format('G');
    $minutos = (int) $evento->format('i');
    if ($hora < HORA_MINIMA) {
        return "El salón está disponible desde las " . HORA_MINIMA . ":00 (mediodía). Selecciona un horario a partir de las " . HORA_MINIMA . ":00.";
    }
    if ($hora > HORA_MAXIMA || ($hora === HORA_MAXIMA && $minutos > 0)) {
        return "El salón se presta hasta la media noche (23:59). Selecciona un horario antes de las 00:00.";
    }
    return null;
}

function reserva_calcular_fin(DateTimeImmutable $evento): DateTimeImmutable
{
    return $evento->setTime(23, 59, 59);
}

function reserva_validar_no_duplicado(PDO $pdo, DateTimeImmutable $evento, ?int $excluirId = null): ?string
{
    $fecha = $evento->format('Y-m-d');
    $sql = "SELECT COUNT(*) FROM reservas
            WHERE DATE(fecha_evento) = ?
              AND estado IN ('pendiente', 'aprobada')";
    $params = [$fecha];
    if ($excluirId !== null) {
        $sql .= " AND id != ?";
        $params[] = $excluirId;
    }
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            return "Ya existe una reserva (pendiente o aprobada) para el " . $fecha . ". Solo se permite una reserva por día.";
        }
    } catch (PDOException $e) {
        return 'Error al verificar disponibilidad.';
    }
    return null;
}

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

function reserva_formato_tabla(string $mysqlDatetime): string
{
    $tz = new DateTimeZone(date_default_timezone_get());
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, $tz);
    if (!$dt instanceof DateTimeImmutable) {
        return $mysqlDatetime;
    }
    return $dt->format('d/m/Y H:i');
}

function reserva_descontar_stock(PDO $pdo, int $reservaId): void
{
    try {
        $stmt = $pdo->prepare(
            'SELECT ri.insumo_id, ri.cantidad_solicitada
             FROM reservas_insumos ri
             WHERE ri.reserva_id = ? AND ri.cantidad_entregada IS NULL'
        );
        $stmt->execute([$reservaId]);
        $items = $stmt->fetchAll();
        foreach ($items as $item) {
            $insumoId = (int) $item['insumo_id'];
            $cantidad = (float) $item['cantidad_solicitada'];
            $upd = $pdo->prepare(
                'UPDATE insumos SET cantidad_stock = GREATEST(cantidad_stock - ?, 0) WHERE id = ?'
            );
            $upd->execute([$cantidad, $insumoId]);
        }
        $pdo->prepare(
            'UPDATE reservas_insumos SET cantidad_entregada = cantidad_solicitada WHERE reserva_id = ? AND cantidad_entregada IS NULL'
        )->execute([$reservaId]);
    } catch (PDOException $e) {
    }
}

function reserva_reponer_stock(PDO $pdo, int $reservaId): void
{
    try {
        $stmt = $pdo->prepare(
            'SELECT ri.insumo_id, ri.cantidad_entregada
             FROM reservas_insumos ri
             WHERE ri.reserva_id = ? AND ri.cantidad_entregada IS NOT NULL'
        );
        $stmt->execute([$reservaId]);
        $items = $stmt->fetchAll();
        foreach ($items as $item) {
            $insumoId = (int) $item['insumo_id'];
            $cantidad = (float) $item['cantidad_entregada'];
            $upd = $pdo->prepare('UPDATE insumos SET cantidad_stock = cantidad_stock + ? WHERE id = ?');
            $upd->execute([$cantidad, $insumoId]);
        }
        $pdo->prepare(
            'UPDATE reservas_insumos SET cantidad_entregada = NULL WHERE reserva_id = ?'
        )->execute([$reservaId]);
    } catch (PDOException $e) {
    }
}
