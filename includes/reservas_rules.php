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

function reserva_formato_tabla(string $mysqlDatetime): string
{
    $tz = new DateTimeZone(date_default_timezone_get());
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, $tz);
    if (!$dt instanceof DateTimeImmutable) {
        return $mysqlDatetime;
    }

    return $dt->format('d/m/Y H:i');
}
