<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/reservas_rules.php';
require_login();

if (!can_manage_reservations()) {
    http_response_code(403);
    echo 'Acceso denegado.';
    exit;
}

$pdo = db();
$hoy = new DateTimeImmutable('today');
$inicioMes = $hoy->modify('first day of this month')->setTime(0, 0, 0);
$finMes = $hoy->modify('last day of this month')->setTime(23, 59, 59);
$lim48 = (new DateTimeImmutable('now'))->add(new DateInterval('PT48H'));
$inicioAnio = $hoy->modify('first day of january this year')->setTime(0, 0, 0);
$finAnio = $hoy->modify('last day of december this year')->setTime(23, 59, 59);

$stats = [
    'usuarios_activos' => 0,
    'reservas_mes_total' => 0,
    'reservas_aprobadas' => 0,
    'reservas_pendientes' => 0,
    'reservas_rechazadas' => 0,
    'proximas_48h' => 0,
];

try {
    $stats['usuarios_activos'] = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE activo = 1")->fetchColumn();

    $stmtMes = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE fecha_evento >= ? AND fecha_evento <= ?");
    $stmtMes->execute([$inicioMes->format('Y-m-d H:i:s'), $finMes->format('Y-m-d H:i:s')]);
    $stats['reservas_mes_total'] = (int) $stmtMes->fetchColumn();

    $stats['reservas_aprobadas'] = (int) $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'aprobada'")->fetchColumn();
    $stats['reservas_pendientes'] = (int) $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente'")->fetchColumn();
    $stats['reservas_rechazadas'] = (int) $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'rechazada'")->fetchColumn();

    $stmt48 = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE fecha_evento >= NOW() AND fecha_evento <= ? AND estado IN ('pendiente','aprobada')");
    $stmt48->execute([$lim48->format('Y-m-d H:i:s')]);
    $stats['proximas_48h'] = (int) $stmt48->fetchColumn();
} catch (Throwable $e) {
}

// Reservas por mes (últimos 12 meses)
$reservasPorMes = [];
try {
    $stmt = $pdo->query(
        "SELECT DATE_FORMAT(fecha_evento, '%Y-%m') AS mes, COUNT(*) AS total,
                SUM(estado = 'aprobada') AS aprobadas,
                SUM(estado = 'pendiente') AS pendientes,
                SUM(estado = 'rechazada') AS rechazadas
         FROM reservas
         WHERE fecha_evento >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 MONTH), '%Y-%m-01')
         GROUP BY mes
         ORDER BY mes ASC"
    );
    $reservasPorMes = $stmt->fetchAll();
} catch (Throwable $e) {
}

// Insumos más solicitados
$topInsumos = [];
try {
    $stmt = $pdo->query(
        "SELECT i.nombre, SUM(ri.cantidad_solicitada) AS total_solicitado
         FROM reservas_insumos ri
         INNER JOIN insumos i ON i.id = ri.insumo_id
         GROUP BY ri.insumo_id
         ORDER BY total_solicitado DESC
         LIMIT 8"
    );
    $topInsumos = $stmt->fetchAll();
} catch (Throwable $e) {
}

$pageTitle = 'Dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>
<h1>Dashboard</h1>
<p class="muted" style="margin-top:-0.5rem;">Panorama general del sistema.</p>

<div class="dashboard-grid">
    <div class="dash-card">
        <div class="dash-card-icon">📅</div>
        <div class="dash-card-num"><?= (int) $stats['reservas_mes_total'] ?></div>
        <div class="dash-card-label">Reservas este mes</div>
    </div>
    <div class="dash-card">
        <div class="dash-card-icon">✅</div>
        <div class="dash-card-num" style="color:#10b981;"><?= (int) $stats['reservas_aprobadas'] ?></div>
        <div class="dash-card-label">Aprobadas</div>
    </div>
    <div class="dash-card">
        <div class="dash-card-icon">⏳</div>
        <div class="dash-card-num" style="color:#f59e0b;"><?= (int) $stats['reservas_pendientes'] ?></div>
        <div class="dash-card-label">Pendientes</div>
    </div>
    <div class="dash-card">
        <div class="dash-card-icon">❌</div>
        <div class="dash-card-num" style="color:#ef4444;"><?= (int) $stats['reservas_rechazadas'] ?></div>
        <div class="dash-card-label">Rechazadas</div>
    </div>
    <div class="dash-card">
        <div class="dash-card-icon">🕐</div>
        <div class="dash-card-num"><?= (int) $stats['proximas_48h'] ?></div>
        <div class="dash-card-label">Próximas 48 h</div>
    </div>
    <div class="dash-card">
        <div class="dash-card-icon">👥</div>
        <div class="dash-card-num"><?= (int) $stats['usuarios_activos'] ?></div>
        <div class="dash-card-label">Usuarios activos</div>
    </div>
</div>

<div class="chart-row compact">
    <div class="chart-box chart-box-full">
        <h3>Reservas por mes</h3>
        <canvas id="chartReservasMes"></canvas>
    </div>
</div>

<div class="chart-row compact">
    <div class="chart-box">
        <h3>Distribución de estados</h3>
        <canvas id="chartEstados"></canvas>
    </div>
    <div class="chart-box">
        <h3>Insumos más solicitados</h3>
        <canvas id="chartInsumos"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var charts = [];
    function isDark() {
        return document.documentElement.classList.contains('theme-dark');
    }
    var txt = function () { return isDark() ? '#ececec' : '#1a1d21'; };
    var mtd = function () { return isDark() ? '#9aa0a6' : '#6b7280'; };
    var gridColor = function () { return isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)'; };
    var labelColor = function () { return txt(); };

    function updateCharts() {
        charts.forEach(function (c) { if (c) c.update(); });
    }
    var themeObserver = new MutationObserver(function () {
        if (document.documentElement.classList.contains('theme-dark') !== undefined) {
            updateCharts();
        }
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    // Reservas por mes (barras agrupadas + línea total)
    (function () {
        var ctx = document.getElementById('chartReservasMes');
        if (!ctx) return;
        var raw = <?= json_encode($reservasPorMes, JSON_UNESCAPED_UNICODE) ?>;
        var labels = raw.map(function (r) {
            var p = r.mes.split('-');
            var meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
            return meses[parseInt(p[1],10)-1] + ' ' + p[0];
        });
        var totales = raw.map(function (r) { return parseInt(r.total, 10); });
        var aprobadas = raw.map(function (r) { return parseInt(r.aprobadas, 10); });
        var pendientes = raw.map(function (r) { return parseInt(r.pendientes, 10); });
        var rechazadas = raw.map(function (r) { return parseInt(r.rechazadas, 10); });
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Aprobadas', data: aprobadas, backgroundColor: 'rgba(16, 185, 129, 0.2)', borderColor: 'rgba(16, 185, 129, 1)', borderWidth: 2, borderRadius: 3 },
                    { label: 'Pendientes', data: pendientes, backgroundColor: 'rgba(245, 158, 11, 0.2)', borderColor: 'rgba(245, 158, 11, 1)', borderWidth: 2, borderRadius: 3 },
                    { label: 'Rechazadas', data: rechazadas, backgroundColor: 'rgba(239, 68, 68, 0.2)', borderColor: 'rgba(239, 68, 68, 1)', borderWidth: 2, borderRadius: 3 },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 2.5,
                plugins: {
                    legend: { labels: { color: labelColor, boxWidth: 12, padding: 10, font: { size: 11 } } }
                },
                scales: {
                    x: { ticks: { color: mtd(), font: { size: 10 } }, grid: { color: gridColor() } },
                    y: { beginAtZero: true, ticks: { color: mtd(), font: { size: 10 }, stepSize: 1 }, grid: { color: gridColor() } }
                }
            }
        });
        charts.push(Chart.getChart(ctx));
    })();

    // Distribución de estados (dona)
    (function () {
        var ctx = document.getElementById('chartEstados');
        if (!ctx) return;
        var datos = [<?= $stats['reservas_aprobadas'] ?>, <?= $stats['reservas_pendientes'] ?>, <?= $stats['reservas_rechazadas'] ?>];
        var total = datos.reduce(function (a, b) { return a + b; }, 0);
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Aprobadas', 'Pendientes', 'Rechazadas'],
                datasets: [{
                    data: datos,
                    backgroundColor: ['rgba(16, 185, 129, 0.2)', 'rgba(245, 158, 11, 0.2)', 'rgba(239, 68, 68, 0.2)'],
                    borderColor: ['rgba(16, 185, 129, 1)', 'rgba(245, 158, 11, 1)', 'rgba(239, 68, 68, 1)'],
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.2,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: labelColor,
                            padding: 12,
                            font: { size: 11 },
                            generateLabels: function (chart) {
                                var data = chart.data;
                                return data.labels.map(function (label, i) {
                                    var val = data.datasets[0].data[i];
                                    var pct = total > 0 ? Math.round(val / total * 100) + '%' : '0%';
                                    return {
                                        text: label + ' (' + val + ', ' + pct + ')',
                                        fillStyle: data.datasets[0].backgroundColor[i],
                                        strokeStyle: 'transparent',
                                        pointStyle: 'circle',
                                        index: i,
                                    };
                                });
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var val = ctx.parsed;
                                var pct = total > 0 ? Math.round(val / total * 100) + '%' : '0%';
                                return ctx.label + ': ' + val + ' (' + pct + ')';
                            }
                        }
                    }
                }
            },
            plugins: [{
                id: 'centerText',
                beforeDraw: function (chart) {
                    var w = chart.width, h = chart.height;
                    if (!w || !h) return;
                    var ctx2 = chart.ctx;
                    ctx2.save();
                    var text = total + (total === 1 ? ' reserva' : ' reservas');
                    ctx2.font = '500 ' + (Math.min(w, h) * 0.08) + 'px sans-serif';
                    ctx2.textAlign = 'center';
                    ctx2.textBaseline = 'middle';
                    ctx2.fillStyle = txt();
                    ctx2.fillText(text, w / 2, h / 2);
                    ctx2.restore();
                }
            }]
        });
        charts.push(Chart.getChart(ctx));
    })();

    // Insumos más solicitados
    (function () {
        var ctx = document.getElementById('chartInsumos');
        if (!ctx) return;
        var labels = <?= json_encode(array_reverse(array_column($topInsumos, 'nombre')), JSON_UNESCAPED_UNICODE) ?>;
        var datos = <?= json_encode(array_reverse(array_map('intval', array_column($topInsumos, 'total_solicitado'))), JSON_UNESCAPED_UNICODE) ?>;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Veces solicitado',
                    data: datos,
                    backgroundColor: 'rgba(99, 102, 241, 0.2)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 2,
                    borderRadius: 4,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.8,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { color: mtd(), font: { size: 10 }, stepSize: 1 }, grid: { color: gridColor() } },
                    y: { ticks: { color: txt(), font: { size: 10 } }, grid: { color: gridColor() } }
                }
            }
        });
        charts.push(Chart.getChart(ctx));
    })();

});
</script>

<section class="section" style="margin-top:1.5rem;">
    <h2 style="font-size:1rem;margin-bottom:0.5rem;">Próximas 48 horas</h2>
    <?php
    $semaforo = [];
    try {
        $stmtSem = $pdo->prepare(
            "SELECT r.fecha_evento, r.estado, u.nombre_completo
             FROM reservas r
             INNER JOIN usuarios u ON u.id = r.usuario_id
             WHERE r.fecha_evento >= NOW()
               AND r.fecha_evento <= ?
               AND r.estado IN ('pendiente','aprobada')
             ORDER BY r.fecha_evento ASC
             LIMIT 10"
        );
        $stmtSem->execute([$lim48->format('Y-m-d H:i:s')]);
        $semaforo = $stmtSem->fetchAll();
    } catch (Throwable $e) {
    }
    ?>
    <?php if (count($semaforo) === 0): ?>
        <p class="muted">No hay reservas en las próximas 48 horas.</p>
    <?php else: ?>
        <div class="table-wrap" style="max-width:600px;">
            <table class="data-table" style="font-size:0.85rem;">
                <thead><tr><th>Fecha</th><th>Solicitante</th><th>Estado</th><th>Urgencia</th></tr></thead>
                <tbody>
                <?php foreach ($semaforo as $r): ?>
                    <?php
                    $fecha = new DateTimeImmutable((string) $r['fecha_evento']);
                    $diffH = ($fecha->getTimestamp() - time()) / 3600;
                    if ($diffH <= 12) {
                        $chipClase = 'chip chip-aprobada';
                        $chipLabel = 'Urgente';
                    } elseif ($diffH <= 24) {
                        $chipClase = 'chip chip-pendiente';
                        $chipLabel = 'Próximo';
                    } else {
                        $chipClase = 'chip chip-verde';
                        $chipLabel = 'Tranquilo';
                    }
                    $estado = (string) ($r['estado'] ?? '');
                    $estadoTxt = $estado === 'aprobada' ? 'Aprobada' : 'Pendiente';
                    ?>
                    <tr>
                        <td><?= htmlspecialchars(reserva_formato_tabla((string) $r['fecha_evento']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($r['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="chip chip-<?= $estado ?>"><?= htmlspecialchars($estadoTxt, ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><span class="<?= htmlspecialchars($chipClase, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($chipLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
