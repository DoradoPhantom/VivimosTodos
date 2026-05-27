<?php
declare(strict_types=1);

// Config global base de datos ruta en htdocs y reglas del salon
// Se carga antes de db y auth en casi todas las paginas
const DB_HOST = 'localhost';
const DB_NAME = 'vivimos_todos';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';
// Aforo maximo del salon al crear reservas
const VENUE_MAX_CAPACITY = 120;

// Ruta URL del proyecto bajo htdocs sin barra final
// Si falla la deteccion cambia la carpeta por defecto en htdocs por ejemplo VivimosTodos
$__docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$__projectRoot = realpath(dirname(__DIR__));
$__basePath = '/VivimosTodos'; // carpeta del proyecto en htdocs
if ($__docRoot !== false && $__projectRoot !== false) {
    $__d = str_replace('\\', '/', rtrim($__docRoot, '/\\'));
    $__p = str_replace('\\', '/', rtrim($__projectRoot, '/\\'));
    if (str_starts_with($__p, $__d)) {
        $__rel = trim(substr($__p, strlen($__d)), '/');
        $__basePath = $__rel === '' ? '' : '/' . $__rel;
    }
}
define('BASE_PATH', $__basePath);
unset($__docRoot, $__projectRoot, $__basePath, $__d, $__p, $__rel);

date_default_timezone_set('America/Bogota');
