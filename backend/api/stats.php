<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/stats.php
//  GET → devuelve estadísticas completas del negocio
// ============================================================

require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function ok($data): never {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function err($msg, $code = 500): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db  = getDB();
    $hoy = date('Y-m-d');
    $tz  = new DateTimeZone('Europe/Madrid');

    // ── 1. KPIs globales ─────────────────────────────────────
    $kpi = $db->query("
        SELECT
            COUNT(*) AS total_reservas,
            SUM(CASE WHEN estado = 'aceptada' THEN 1 ELSE 0 END)  AS aceptadas,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado = 'denegada'  THEN 1 ELSE 0 END) AS denegadas,
            SUM(CASE WHEN estado = 'aceptada'  THEN s.precio ELSE 0 END) AS ingresos_totales,
            COUNT(DISTINCT r.cliente_email) AS clientes_unicos
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
    ")->fetch();

    // ── 2. KPIs de hoy ───────────────────────────────────────
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS citas_hoy,
            SUM(CASE WHEN r.estado = 'aceptada' THEN s.precio ELSE 0 END) AS ingresos_hoy
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
        WHERE r.fecha = ?
    ");
    $stmt->execute([$hoy]);
    $hoyStats = $stmt->fetch();

    // ── 3. KPIs del mes actual ────────────────────────────────
    $mesInicio = date('Y-m-01');
    $mesFin    = date('Y-m-t');
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS citas_mes,
            SUM(CASE WHEN r.estado = 'aceptada' THEN s.precio ELSE 0 END) AS ingresos_mes
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
        WHERE r.fecha BETWEEN ? AND ?
    ");
    $stmt->execute([$mesInicio, $mesFin]);
    $mesStats = $stmt->fetch();

    // ── 4. Ingresos de los últimos 12 meses (por mes) ─────────
    $stmt = $db->query("
        SELECT
            DATE_FORMAT(r.fecha, '%Y-%m') AS mes,
            COUNT(*) AS citas,
            SUM(CASE WHEN r.estado = 'aceptada' THEN s.precio ELSE 0 END) AS ingresos
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
        WHERE r.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY mes
        ORDER BY mes ASC
    ");
    $ingresosPorMes = $stmt->fetchAll();

    // Rellenar meses sin datos
    $mesesCompletos = [];
    for ($i = 11; $i >= 0; $i--) {
        $dt  = new DateTime('now', $tz);
        $dt->modify("-{$i} months");
        $key = $dt->format('Y-m');
        $lbl = strftime_compat($dt->format('n'), $dt->format('Y'));
        $found = array_filter($ingresosPorMes, fn($r) => $r['mes'] === $key);
        $found = array_values($found);
        $mesesCompletos[] = [
            'mes'      => $key,
            'label'    => $lbl,
            'citas'    => $found ? (int)$found[0]['citas']    : 0,
            'ingresos' => $found ? (float)$found[0]['ingresos'] : 0,
        ];
    }

    // ── 5. Servicios más reservados ───────────────────────────
    $stmt = $db->query("
        SELECT s.nombre, s.precio,
               COUNT(*) AS total,
               SUM(CASE WHEN r.estado = 'aceptada' THEN s.precio ELSE 0 END) AS ingresos
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
        GROUP BY s.id, s.nombre, s.precio
        ORDER BY total DESC
        LIMIT 6
    ");
    $serviciosTop = $stmt->fetchAll();

    // ── 6. Barberos — rendimiento ─────────────────────────────
    $stmt = $db->query("
        SELECT b.nombre, b.iniciales,
               COUNT(*) AS total_citas,
               SUM(CASE WHEN r.estado = 'aceptada' THEN s.precio ELSE 0 END) AS ingresos,
               SUM(CASE WHEN r.estado = 'aceptada' THEN 1 ELSE 0 END) AS aceptadas,
               SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes
        FROM reservas r
        JOIN barberos  b ON b.id = r.barbero_id
        JOIN servicios s ON s.id = r.servicio_id
        GROUP BY b.id, b.nombre, b.iniciales
        ORDER BY ingresos DESC
    ");
    $barberoStats = $stmt->fetchAll();

    // ── 7. Distribución por día de la semana ─────────────────
    $stmt = $db->query("
        SELECT DAYOFWEEK(fecha) AS dow, COUNT(*) AS total
        FROM reservas
        WHERE estado != 'denegada'
        GROUP BY dow
        ORDER BY dow
    ");
    $rawDow = $stmt->fetchAll();
    $dowMap = [];
    foreach ($rawDow as $r) $dowMap[$r['dow']] = (int)$r['total'];
    $diasSemana = [
        ['label' => 'Lun', 'count' => $dowMap[2] ?? 0],
        ['label' => 'Mar', 'count' => $dowMap[3] ?? 0],
        ['label' => 'Mié', 'count' => $dowMap[4] ?? 0],
        ['label' => 'Jue', 'count' => $dowMap[5] ?? 0],
        ['label' => 'Vie', 'count' => $dowMap[6] ?? 0],
        ['label' => 'Sáb', 'count' => $dowMap[7] ?? 0],
    ];

    // ── 8. Franjas horarias más populares ────────────────────
    $stmt = $db->query("
        SELECT TIME_FORMAT(hora, '%H:%i') AS hora_slot, COUNT(*) AS total
        FROM reservas
        WHERE estado != 'denegada'
        GROUP BY hora_slot
        ORDER BY total DESC
        LIMIT 8
    ");
    $horasTop = $stmt->fetchAll();

    // ── 9. Tasa de conversión (pendiente→aceptada) ────────────
    $totalGestionadas = (int)$kpi['aceptadas'] + (int)$kpi['denegadas'];
    $tasaConversion   = $totalGestionadas > 0
        ? round((int)$kpi['aceptadas'] / $totalGestionadas * 100, 1)
        : 0;

    // ── 10. Reservas de los últimos 30 días (heatmap) ────────
    $stmt = $db->query("
        SELECT DATE_FORMAT(fecha,'%Y-%m-%d') AS dia, COUNT(*) AS total
        FROM reservas
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          AND estado != 'denegada'
        GROUP BY dia
        ORDER BY dia ASC
    ");
    $heatmap = $stmt->fetchAll();

    ok([
        'kpi'              => $kpi,
        'hoy'              => $hoyStats,
        'mes'              => $mesStats,
        'ingresos_mensual' => $mesesCompletos,
        'servicios_top'    => $serviciosTop,
        'barberos'         => $barberoStats,
        'dias_semana'      => $diasSemana,
        'horas_top'        => $horasTop,
        'tasa_conversion'  => $tasaConversion,
        'heatmap_30d'      => $heatmap,
    ]);

} catch (PDOException $e) {
    err('Error de base de datos: ' . $e->getMessage());
}

// Formatear mes en español sin strftime
function strftime_compat(int $month, string $year): string {
    $nombres = ['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return $nombres[$month] . ' ' . substr($year, 2);
}