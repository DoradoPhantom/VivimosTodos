<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

if (!can_manage_reservations() && !can_manage_inventory()) {
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

require_once dirname(__DIR__) . '/libs/fpdf.php';

$pdo = db();

// ── helpers ──────────────────────────────────────────────────────────
function u(string $s): string
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
}

class PDFReport extends FPDF
{
    public function header(): void
    {
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, u('VivimosTodos — Salón social — Reporte generado el ' . date('d/m/Y H:i')), 0, 1, 'R');
        $this->Ln(2);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    public function footer(): void
    {
        $this->SetY(-15);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(130, 130, 130);
        $this->Cell(0, 10, u('Página ') . $this->PageNo() . u(' de {nb}'), 0, 0, 'C');
    }
}

function generarPDF(PDO $pdo, string $tipo, DateTimeImmutable $desde, DateTimeImmutable $hasta): void
{
    $pdf = new PDFReport('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 20);

    match ($tipo) {
        'reservas' => reporteReservas($pdo, $pdf, $desde, $hasta),
        'insumos'  => reporteInsumos($pdo, $pdf),
        'ocupacion' => reporteOcupacion($pdo, $pdf, $desde, $hasta),
        default => null,
    };

    $pdf->Output('D', $tipo . '_' . $desde->format('Ymd') . '_' . $hasta->format('Ymd') . '.pdf');
}

function reporteReservas(PDO $pdo, PDFReport $pdf, DateTimeImmutable $desde, DateTimeImmutable $hasta): void
{
    $rows = [];
    try {
        $stmt = $pdo->prepare(
            "SELECT r.id, r.fecha_evento, r.estado, r.descripcion, r.comentario_revision,
                    u.nombre_completo AS solicitante
             FROM reservas r
             INNER JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.fecha_evento >= ? AND r.fecha_evento <= ?
             ORDER BY r.fecha_evento ASC"
        );
        $stmt->execute([$desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
    }

    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, u('Reporte de reservas'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, u('Período: ' . $desde->format('d/m/Y') . ' — ' . $hasta->format('d/m/Y')), 0, 1);
    $pdf->Ln(3);

    if (count($rows) === 0) {
        $pdf->SetFont('Helvetica', 'I', 11);
        $pdf->Cell(0, 10, u('No hay reservas en el período seleccionado.'), 0, 1);
        return;
    }

    $total = count($rows);
    $aprobadas = count(array_filter($rows, fn($r) => $r['estado'] === 'aprobada'));
    $pendientes = count(array_filter($rows, fn($r) => $r['estado'] === 'pendiente'));
    $rechazadas = count(array_filter($rows, fn($r) => $r['estado'] === 'rechazada'));

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 5, u("Total: $total | Aprobadas: $aprobadas | Pendientes: $pendientes | Rechazadas: $rechazadas"), 0, 1);
    $pdf->Ln(4);

    $headers = ['Fecha', 'Hora', 'Solicitante', 'Estado', 'Descripción'];
    $w = [30, 24, 50, 28, 148];
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(230, 230, 230);
    foreach ($headers as $i => $h) {
        $pdf->Cell($w[$i], 7, u($h), 1, 0, 'C', true);
    }
    $pdf->Ln();

    $colores = [
        'aprobada'  => [200, 245, 230],
        'pendiente' => [255, 243, 205],
        'rechazada' => [255, 220, 220],
    ];
    foreach ($rows as $r) {
        $color = $colores[$r['estado']] ?? [255, 255, 255];
        $pdf->SetFillColor($color[0], $color[1], $color[2]);

        $dt = new DateTimeImmutable($r['fecha_evento']);
        $fecha = $dt->format('d/m/Y');
        $hora = $dt->format('H:i');
        $solicitante = mb_substr((string) ($r['solicitante'] ?? ''), 0, 28);
        $estado = ucfirst((string) ($r['estado'] ?? ''));
        $desc = mb_substr((string) ($r['descripcion'] ?? ''), 0, 80);

        $row = [$fecha, $hora, $solicitante, $estado, $desc];
        foreach ($row as $i => $val) {
            $pdf->Cell($w[$i], 6, u($val), 1, 0, 'L', true);
        }
        $pdf->Ln();

        $com = (string) ($r['comentario_revision'] ?? '');
        if ($com !== '') {
            $pdf->SetFont('Helvetica', 'I', 7);
            $pdf->SetTextColor(150, 100, 50);
            $pdf->Cell($w[0] + $w[1] + $w[2] + $w[3], 5, u('  Revisión: ' . mb_substr($com, 0, 80)), 'LR', 0, 'L', true);
            $pdf->Cell($w[4], 5, '', 'LR', 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
        }
    }
}

function reporteInsumos(PDO $pdo, PDFReport $pdf): void
{
    $rows = [];
    try {
        $stmt = $pdo->query(
            "SELECT i.nombre, i.categoria, i.cantidad_stock, i.activo,
                    COALESCE(SUM(ri.cantidad_solicitada), 0) AS total_solicitado
             FROM insumos i
             LEFT JOIN reservas_insumos ri ON ri.insumo_id = i.id
             WHERE i.activo = 1
             GROUP BY i.id
             ORDER BY total_solicitado DESC"
        );
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
    }

    $pdf->AddPage('L');
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, u('Insumos más solicitados'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, u('Catálogo del salón — ordenado por demanda'), 0, 1);
    $pdf->Ln(4);

    if (count($rows) === 0) {
        $pdf->SetFont('Helvetica', 'I', 11);
        $pdf->Cell(0, 10, u('No hay insumos registrados.'), 0, 1);
        return;
    }

    $headers = ['#', 'Nombre', 'Categoría', 'Stock actual', 'Veces solicitado'];
    $w = [10, 80, 50, 38, 52];
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(230, 230, 230);
    foreach ($headers as $i => $h) {
        $pdf->Cell($w[$i], 7, u($h), 1, 0, 'C', true);
    }
    $pdf->Ln();

    $i = 1;
    foreach ($rows as $r) {
        $pdf->Cell($w[0], 6, (string) $i++, 1, 0, 'C');
        $pdf->Cell($w[1], 6, u(mb_substr((string) ($r['nombre'] ?? ''), 0, 45)), 1);
        $pdf->Cell($w[2], 6, u(mb_substr((string) ($r['categoria'] ?? '—'), 0, 28)), 1);
        $pdf->Cell($w[3], 6, (string) ((int) ($r['cantidad_stock'] ?? 0)), 1, 0, 'C');
        $pdf->Cell($w[4], 6, (string) ((int) ($r['total_solicitado'] ?? 0)), 1, 0, 'C');
        $pdf->Ln();
    }
}

function reporteOcupacion(PDO $pdo, PDFReport $pdf, DateTimeImmutable $desde, DateTimeImmutable $hasta): void
{
    $rows = [];
    try {
        $stmt = $pdo->prepare(
            "SELECT r.fecha_evento, r.estado, u.nombre_completo AS solicitante
             FROM reservas r
             INNER JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.fecha_evento >= ? AND r.fecha_evento <= ?
               AND r.estado IN ('aprobada', 'pendiente')
             ORDER BY r.fecha_evento ASC"
        );
        $stmt->execute([$desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
    }

    $pdf->AddPage('L');
    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->Cell(0, 10, u('Ocupación del salón'), 0, 1);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 6, u('Período: ' . $desde->format('d/m/Y') . ' — ' . $hasta->format('d/m/Y')), 0, 1);
    $pdf->Ln(3);

    if (count($rows) === 0) {
        $pdf->SetFont('Helvetica', 'I', 11);
        $pdf->Cell(0, 10, u('No hay reservas activas en el período.'), 0, 1);
        return;
    }

    $dias = [];
    foreach ($rows as $r) {
        $fecha = (new DateTimeImmutable($r['fecha_evento']))->format('Y-m-d');
        if (!isset($dias[$fecha])) {
            $dias[$fecha] = ['reservas' => [], 'count' => 0];
        }
        $dias[$fecha]['reservas'][] = $r;
        $dias[$fecha]['count']++;
    }

    $totalDias = count($dias);
    $totalReservas = count($rows);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 5, u("Días ocupados: $totalDias | Total de reservas: $totalReservas"), 0, 1);
    $pdf->Ln(4);

    $headers = ['Fecha', 'Día', 'Cant.', 'Estado', 'Solicitante'];
    $w = [30, 25, 14, 24, 60];
    $restante = 277 - array_sum($w);
    $w[] = $restante;

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetFillColor(230, 230, 230);
    foreach ($headers as $i => $h) {
        $pdf->Cell($w[$i], 7, u($h), 1, 0, 'C', true);
    }
    $pdf->Ln();

    $diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
    $toggle = false;

    ksort($dias);
    foreach ($dias as $fecha => $info) {
        $toggle = !$toggle;
        $pdf->SetFillColor($toggle ? 245 : 255, $toggle ? 245 : 255, $toggle ? 245 : 255);

        $dt = new DateTimeImmutable($fecha);
        $diaSem = $diasSemana[(int) $dt->format('w')];
        $fechaStr = $dt->format('d/m/Y');

        $primera = $info['reservas'][0];
        $estado = ucfirst((string) ($primera['estado'] ?? ''));
        $solicitante = mb_substr((string) ($primera['solicitante'] ?? ''), 0, 35);
        $horaInicio = (new DateTimeImmutable($primera['fecha_evento']))->format('H:i');

        $pdf->Cell($w[0], 6, $fechaStr, 1, 0, 'C', true);
        $pdf->Cell($w[1], 6, u($diaSem), 1, 0, 'C', true);
        $pdf->Cell($w[2], 6, (string) $info['count'], 1, 0, 'C', true);
        $pdf->Cell($w[3], 6, u($estado), 1, 0, 'C', true);
        $pdf->Cell($w[4], 6, u($solicitante), 1, 0, 'L', true);

        $detalles = '';
        foreach ($info['reservas'] as $r) {
            $h = (new DateTimeImmutable($r['fecha_evento']))->format('H:i');
            $detalles .= $h . ' ' . $r['solicitante'] . '; ';
        }
        $detalles = rtrim($detalles, '; ');
        $pdf->Cell($w[5], 6, u(mb_substr($detalles, 0, 90)), 1, 1, 'L', true);
    }
}

// ── procesar formulario ──────────────────────────────────────────────
$error = '';
$reportType = (string) ($_POST['tipo'] ?? '');
$fechaDesde = (string) ($_POST['fecha_desde'] ?? '');
$fechaHasta = (string) ($_POST['fecha_hasta'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reportType !== '') {
    if ($fechaDesde === '' || $fechaHasta === '') {
        $error = 'Debes indicar un rango de fechas.';
    } else {
        try {
            $dtDesde = new DateTimeImmutable($fechaDesde . ' 00:00:00');
            $dtHasta = new DateTimeImmutable($fechaHasta . ' 23:59:59');
        } catch (Throwable $e) {
            $error = 'Fechas no válidas.';
        }
    }
    if ($error === '' && $dtDesde > $dtHasta) {
        $error = 'La fecha "desde" no puede ser posterior a "hasta".';
    }
    if ($error === '') {
        generarPDF($pdo, $reportType, $dtDesde, $dtHasta);
        exit;
    }
}

// ── formulario HTML ──────────────────────────────────────────────────
$pageTitle = 'Reportes PDF';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1>Reportes PDF</h1>
<p class="muted">Genera reportes descargables con rango de fechas.</p>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="section">
    <form method="post" class="form-grid" style="max-width:500px">
        <label>Tipo de reporte
            <select name="tipo" required>
                <option value="">— Seleccionar —</option>
                <option value="reservas"<?= $reportType === 'reservas' ? ' selected' : '' ?>>Reservas por rango</option>
                <option value="insumos"<?= $reportType === 'insumos' ? ' selected' : '' ?>>Insumos más solicitados</option>
                <option value="ocupacion"<?= $reportType === 'ocupacion' ? ' selected' : '' ?>>Ocupación del salón</option>
            </select>
        </label>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap">
            <label style="flex:1">Desde
                <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fechaDesde, ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label style="flex:1">Hasta
                <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fechaHasta, ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Generar PDF</button>
        </div>
    </form>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
