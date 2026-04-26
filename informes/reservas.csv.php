<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

if (!can_manage_reservations()) {
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

$pdo = db();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="reservas.csv"');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}

fputcsv($out, [
    'id',
    'fecha_evento',
    'estado',
    'solicitante',
    'usuario',
    'descripcion',
    'comentario_revision',
    'revisado_por',
    'revisado_en',
    'creado_en',
]);

$rows = [];
try {
    $rows = $pdo->query(
        "SELECT r.id, r.fecha_evento, r.estado, r.descripcion, r.comentario_revision, r.revisado_en, r.creado_en,
                u.nombre_completo AS solicitante_nombre, u.usuario AS solicitante_usuario,
                rv.nombre_completo AS revisor_nombre
         FROM reservas r
         INNER JOIN usuarios u ON u.id = r.usuario_id
         LEFT JOIN usuarios rv ON rv.id = r.revisado_por_id
         ORDER BY r.id DESC"
    )->fetchAll();
} catch (Throwable $e) {
    $rows = [];
}

foreach ($rows as $r) {
    fputcsv($out, [
        (int) ($r['id'] ?? 0),
        (string) ($r['fecha_evento'] ?? ''),
        (string) ($r['estado'] ?? ''),
        (string) ($r['solicitante_nombre'] ?? ''),
        (string) ($r['solicitante_usuario'] ?? ''),
        (string) ($r['descripcion'] ?? ''),
        (string) ($r['comentario_revision'] ?? ''),
        (string) ($r['revisor_nombre'] ?? ''),
        (string) ($r['revisado_en'] ?? ''),
        (string) ($r['creado_en'] ?? ''),
    ]);
}

fclose($out);

