<?php
declare(strict_types=1);

/**
 * Ajusta credenciales según tu instalación de XAMPP.
 */
const DB_HOST = 'localhost';
const DB_NAME = 'vivimos_todos';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';
const VENUE_MAX_CAPACITY = 120;

/**
 * Ruta URL del proyecto bajo htdocs (sin barra final). Se calcula desde la carpeta real del proyecto.
 * Si la detección falla (poco habitual en XAMPP), ajusta el valor por defecto al nombre de tu carpeta en htdocs (sin espacios), p. ej. '/VivimosTodos'.
 */
$__docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$__projectRoot = realpath(dirname(__DIR__));
$__basePath = '/VivimosTodos'; // carpeta del proyecto en htdocs (la URL no lleva espacio)
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
