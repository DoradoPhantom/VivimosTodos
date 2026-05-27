<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

// Descargas CSV para supervisor o administrador
if (!can_manage_reservations() && !can_manage_inventory()) {
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

$pageTitle = 'Informes';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1>Informes</h1>
<p class="muted">Descarga de reportes para administración.</p>

<section class="section">
    <h2>Reportes PDF</h2>
    <p class="muted">Genera reportes profesionales descargables con rango de fechas: reservas, insumos más solicitados y ocupación del salón.</p>
    <p>
        <a class="btn btn-primary" href="<?= htmlspecialchars(url('informes/pdf.php'), ENT_QUOTES, 'UTF-8') ?>">Ir a reportes PDF</a>
    </p>
</section>

<section class="section">
    <h2>CSV — Reservas</h2>
    <p class="muted">Exporta todas las reservas con estado y revisiones.</p>
    <p>
        <a class="btn" href="<?= htmlspecialchars(url('informes/reservas.csv.php'), ENT_QUOTES, 'UTF-8') ?>">Descargar CSV de reservas</a>
    </p>
</section>

<section class="section">
    <h2>CSV — Inventario</h2>
    <p class="muted">Exporta el catálogo de insumos (stock, categoría, precio, estado).</p>
    <p>
        <a class="btn" href="<?= htmlspecialchars(url('informes/insumos.csv.php'), ENT_QUOTES, 'UTF-8') ?>">Descargar CSV de insumos</a>
    </p>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

