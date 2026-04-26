<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

if (!can_manage_inventory()) {
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

$pdo = db();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="insumos.csv"');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}

fputcsv($out, [
    'id',
    'codigo',
    'nombre',
    'categoria',
    'cantidad_stock',
    'precio_unitario',
    'estado_operativo',
    'activo',
]);

$rows = [];
try {
    // estado_operativo puede no existir aún; si falla, reintentamos sin la columna
    $rows = $pdo->query(
        'SELECT id, codigo, nombre, categoria, cantidad_stock, precio_unitario, estado_operativo, activo
         FROM insumos
         ORDER BY nombre ASC'
    )->fetchAll();
} catch (Throwable $e) {
    try {
        $rows = $pdo->query(
            'SELECT id, codigo, nombre, categoria, cantidad_stock, precio_unitario, activo
             FROM insumos
             ORDER BY nombre ASC'
        )->fetchAll();
    } catch (Throwable $e2) {
        $rows = [];
    }
}

foreach ($rows as $r) {
    fputcsv($out, [
        (int) ($r['id'] ?? 0),
        (string) ($r['codigo'] ?? ''),
        (string) ($r['nombre'] ?? ''),
        (string) ($r['categoria'] ?? ''),
        (string) ($r['cantidad_stock'] ?? ''),
        (string) ($r['precio_unitario'] ?? ''),
        (string) ($r['estado_operativo'] ?? 'disponible'),
        (int) ($r['activo'] ?? 0),
    ]);
}

fclose($out);

