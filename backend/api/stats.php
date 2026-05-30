<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/stats.php
//  GET → devuelve estadísticas completas del negocio
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/helpers.php';

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

    // ── Periodo filter ────────────────────────────────────────
    $periodoRaw = $_GET['periodo'] ?? 'todo';
    $periodoAllowed = ['hoy','semana','mes','trimestre','año','todo'];
    $periodo = in_array($periodoRaw, $periodoAllowed) ? $periodoRaw : 'todo';

    $dateFrom = null;
    $dateTo   = null;
    $now = new DateTime('now', $tz);
    switch ($periodo) {
        case 'hoy':
            $dateFrom = $hoy;
            $dateTo   = $hoy;
            break;
        case 'semana':
            // Semana ISO actual: lunes a domingo
            $weekDay  = (int)$now->format('N'); // 1=Lun, 7=Dom
            $dateFrom = (clone $now)->modify('-' . ($weekDay - 1) . ' days')->format('Y-m-d');
            $dateTo   = (clone $now)->modify('+' . (7 - $weekDay) . ' days')->format('Y-m-d');
            break;
        case 'mes':
            $dateFrom = date('Y-m-01');
            $dateTo   = date('Y-m-t');
            break;
        case 'trimestre':
            $dateFrom = (clone $now)->modify('-3 months')->format('Y-m-d');
            $dateTo   = $hoy;
            break;
        case 'año':
            $dateFrom = date('Y-01-01');
            $dateTo   = date('Y-12-31');
            break;
        default:
            $dateFrom = null;
            $dateTo   = null;
    }
    $wherePeriod = $dateFrom
        ? " AND r.fecha BETWEEN " . $db->quote($dateFrom) . " AND " . $db->quote($dateTo)
        : '';
    $wherePeriodR = $dateFrom
        ? " AND fecha BETWEEN " . $db->quote($dateFrom) . " AND " . $db->quote($dateTo)
        : '';

    // ── 1. KPIs globales ─────────────────────────────────────
    $kpi = $db->query("
        SELECT
            COUNT(*) AS total_reservas,
            SUM(CASE WHEN estado = 'aceptada' THEN 1 ELSE 0 END)  AS aceptadas,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado = 'denegada'  THEN 1 ELSE 0 END) AS denegadas,
            SUM(CASE WHEN estado = 'aceptada'  THEN s.precio ELSE 0 END) AS ingresos_totales,
            COUNT(DISTINCT r.cliente_telefono) AS clientes_unicos
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
        WHERE 1=1 $wherePeriod
    ")->fetch();

    // ── 2. KPIs de hoy ───────────────────────────────────────
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS citas_hoy_total,
            SUM(CASE WHEN r.estado = 'aceptada'  THEN 1 ELSE 0 END) AS citas_hoy_aceptadas,
            SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) AS citas_hoy_pendientes,
            SUM(CASE WHEN r.estado = 'denegada'  THEN 1 ELSE 0 END) AS citas_hoy_denegadas,
            SUM(CASE WHEN r.estado = 'aceptada' THEN s.precio ELSE 0 END) AS ingresos_hoy
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
        WHERE r.fecha = ?
    ");
    $stmt->execute([$hoy]);
    $hoyStats = $stmt->fetch();
    // Alias de compatibilidad: citas_hoy = solo aceptadas
    $hoyStats['citas_hoy'] = (int)$hoyStats['citas_hoy_aceptadas'];

    // ── 3. KPIs del mes actual ────────────────────────────────
    $mesInicio = date('Y-m-01');
    $mesFin    = date('Y-m-t');
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS citas_mes_total,
            SUM(CASE WHEN r.estado = 'aceptada'  THEN 1 ELSE 0 END) AS citas_mes_aceptadas,
            SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END) AS citas_mes_pendientes,
            SUM(CASE WHEN r.estado = 'denegada'  THEN 1 ELSE 0 END) AS citas_mes_denegadas,
            SUM(CASE WHEN r.estado = 'aceptada' THEN s.precio ELSE 0 END) AS ingresos_mes
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
        WHERE r.fecha BETWEEN ? AND ?
    ");
    $stmt->execute([$mesInicio, $mesFin]);
    $mesStats = $stmt->fetch();
    // Alias de compatibilidad: citas_mes = solo aceptadas
    $mesStats['citas_mes'] = (int)$mesStats['citas_mes_aceptadas'];

    // ── 4. Evolución (granularidad según periodo) ─────────────
    // Para hoy/semana: por día; mes/trimestre: por semana; año/todo: por mes
    if (in_array($periodo, ['hoy','semana'])) {
        $granularity = 'day';
        $fmt = '%Y-%m-%d';
        $intervals = ($periodo === 'hoy') ? 1 : 7;
    } elseif (in_array($periodo, ['mes','trimestre'])) {
        $granularity = 'week';
        $fmt = '%x-W%v'; // ISO year + week
        $intervals = ($periodo === 'mes') ? 5 : 13;
    } else {
        $granularity = 'month';
        $fmt = '%Y-%m';
        $intervals = 12;
    }

    $intervalSQL = $dateFrom
        ? "r.fecha BETWEEN " . $db->quote($dateFrom) . " AND " . $db->quote($dateTo)
        : "r.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";

    if ($granularity === 'day') {
        $stmt = $db->query("
            SELECT DATE_FORMAT(r.fecha,'%Y-%m-%d') AS periodo_key,
                   COUNT(*) AS citas,
                   SUM(CASE WHEN r.estado='aceptada' THEN s.precio ELSE 0 END) AS ingresos
            FROM reservas r JOIN servicios s ON s.id=r.servicio_id
            WHERE $intervalSQL
            GROUP BY periodo_key ORDER BY periodo_key ASC
        ");
    } elseif ($granularity === 'week') {
        $stmt = $db->query("
            SELECT DATE_FORMAT(r.fecha,'%x-W%v') AS periodo_key,
                   MIN(DATE_FORMAT(r.fecha,'%d/%m')) AS label_hint,
                   COUNT(*) AS citas,
                   SUM(CASE WHEN r.estado='aceptada' THEN s.precio ELSE 0 END) AS ingresos
            FROM reservas r JOIN servicios s ON s.id=r.servicio_id
            WHERE $intervalSQL
            GROUP BY periodo_key ORDER BY periodo_key ASC
        ");
    } else {
        $stmt = $db->query("
            SELECT DATE_FORMAT(r.fecha,'%Y-%m') AS periodo_key,
                   COUNT(*) AS citas,
                   SUM(CASE WHEN r.estado='aceptada' THEN s.precio ELSE 0 END) AS ingresos
            FROM reservas r JOIN servicios s ON s.id=r.servicio_id
            WHERE $intervalSQL
            GROUP BY periodo_key ORDER BY periodo_key ASC
        ");
    }
    $rawPeriodos = $stmt->fetchAll();

    // Build complete series filling gaps
    $mesesCompletos = [];
    if ($granularity === 'day') {
        $start = new DateTime($dateFrom ?? date('Y-m-d', strtotime('-11 months')), $tz);
        $end   = new DateTime($dateTo, $tz);
        $cur   = clone $start;
        $map   = [];
        foreach ($rawPeriodos as $r) $map[$r['periodo_key']] = $r;
        while ($cur <= $end) {
            $k   = $cur->format('Y-m-d');
            $lbl = $cur->format('d/m');
            $found = $map[$k] ?? null;
            $mesesCompletos[] = ['mes'=>$k,'label'=>$lbl,'citas'=>$found?(int)$found['citas']:0,'ingresos'=>$found?(float)$found['ingresos']:0.0];
            $cur->modify('+1 day');
        }
    } elseif ($granularity === 'week') {
        $map = [];
        foreach ($rawPeriodos as $r) $map[$r['periodo_key']] = $r;
        // Generate all weeks in range, filling gaps with 0
        $cur = new DateTime($dateFrom, $tz);
        // Move to Monday of the start week
        $dow = (int)$cur->format('N');
        if ($dow > 1) $cur->modify('-' . ($dow - 1) . ' days');
        $endDt = new DateTime($dateTo, $tz);
        while ($cur <= $endDt) {
            $isoKey = $cur->format('o-\WW');
            $lbl = 'Sem ' . $cur->format('d/m');
            $found = $map[$isoKey] ?? null;
            $mesesCompletos[] = [
                'mes'      => $isoKey,
                'label'    => $found ? 'Sem ' . $found['label_hint'] : $lbl,
                'citas'    => $found ? (int)$found['citas']    : 0,
                'ingresos' => $found ? (float)$found['ingresos'] : 0.0,
            ];
            $cur->modify('+1 week');
        }
        if (empty($mesesCompletos)) {
            $mesesCompletos[] = ['mes'=>date('Y-m'),'label'=>'—','citas'=>0,'ingresos'=>0.0];
        }
    } else {
        $map = [];
        foreach ($rawPeriodos as $r) $map[$r['periodo_key']] = $r;
        for ($i = $intervals - 1; $i >= 0; $i--) {
            $dt  = new DateTime('now', $tz);
            $dt->modify("-{$i} months");
            $key = $dt->format('Y-m');
            $lbl = strftime_compat($dt->format('n'), $dt->format('Y'));
            $found = $map[$key] ?? null;
            $mesesCompletos[] = ['mes'=>$key,'label'=>$lbl,'citas'=>$found?(int)$found['citas']:0,'ingresos'=>$found?(float)$found['ingresos']:0.0];
        }
    }

    // ── 5. Servicios más reservados ───────────────────────────
    $stmt = $db->query("
        SELECT s.nombre, s.precio,
               COUNT(*) AS total,
               SUM(CASE WHEN r.estado = 'aceptada' THEN s.precio ELSE 0 END) AS ingresos,
               SUM(CASE WHEN r.estado IN ('denegada','cancelada') THEN s.precio ELSE 0 END) AS ingresos_perdidos,
               SUM(CASE WHEN r.estado = 'aceptada' THEN 1 ELSE 0 END) AS citas_aceptadas,
               SUM(CASE WHEN r.estado IN ('denegada','cancelada') THEN 1 ELSE 0 END) AS citas_perdidas
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
        WHERE 1=1 $wherePeriod
        GROUP BY s.id, s.nombre, s.precio
        ORDER BY total DESC
        LIMIT 6
    ");
    $serviciosTop = $stmt->fetchAll();

    // ── 6. Barberos — rendimiento ─────────────────────────────
    $stmt = $db->query("
        SELECT b.nombre, b.iniciales,
               COUNT(r.id) AS total_citas,
               COALESCE(SUM(CASE WHEN r.estado = 'aceptada' THEN s.precio ELSE 0 END), 0) AS ingresos,
               COALESCE(SUM(CASE WHEN r.estado = 'aceptada' THEN 1 ELSE 0 END), 0) AS aceptadas,
               COALESCE(SUM(CASE WHEN r.estado = 'pendiente' THEN 1 ELSE 0 END), 0) AS pendientes
        FROM barberos b
        LEFT JOIN reservas r  ON r.barbero_id = b.id AND 1=1 $wherePeriod
        LEFT JOIN servicios s ON s.id = r.servicio_id
        GROUP BY b.id, b.nombre, b.iniciales
        ORDER BY ingresos DESC, b.nombre ASC
    ");
    $barberoStats = $stmt->fetchAll();

    // ── 7. Distribución por día de la semana ─────────────────
    $stmt = $db->query("
        SELECT DAYOFWEEK(fecha) AS dow, COUNT(*) AS total
        FROM reservas
        WHERE estado != 'denegada' $wherePeriodR
        GROUP BY dow
        ORDER BY dow
    ");
    $rawDow = $stmt->fetchAll();
    $dowMap = [];
    foreach ($rawDow as $r) $dowMap[$r['dow']] = (int)$r['total'];
    // horario_dias_abiertos usa ISO (1=Lun…7=Dom); DAYOFWEEK MySQL: 1=Dom,2=Lun…7=Sáb
    $horCfg      = getHorarioCfg($db);
    $diasAbiertos = array_map('intval', explode(',', $horCfg['horario_dias_abiertos']));
    $abreDomingo  = in_array(7, $diasAbiertos, true); // ISO 7 = domingo
    $diasSemana = [
        ['label' => 'Lun', 'count' => $dowMap[2] ?? 0],
        ['label' => 'Mar', 'count' => $dowMap[3] ?? 0],
        ['label' => 'Mié', 'count' => $dowMap[4] ?? 0],
        ['label' => 'Jue', 'count' => $dowMap[5] ?? 0],
        ['label' => 'Vie', 'count' => $dowMap[6] ?? 0],
        ['label' => 'Sáb', 'count' => $dowMap[7] ?? 0],
    ];
    if ($abreDomingo) {
        $diasSemana[] = ['label' => 'Dom', 'count' => $dowMap[1] ?? 0];
    }

    // ── 8. Franjas horarias más populares ────────────────────
    $stmt = $db->query("
        SELECT TIME_FORMAT(hora, '%H:%i') AS hora_slot, COUNT(*) AS total
        FROM reservas
        WHERE estado != 'denegada' $wherePeriodR
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

    // ── 10. Reservas — heatmap ────────────────────────────────
    $hmFrom = $dateFrom ?? date('Y-m-d', strtotime('-30 days'));
    $hmTo   = $dateTo   ?? date('Y-m-d', strtotime('+30 days'));
    $stmt = $db->query("
        SELECT DATE_FORMAT(fecha,'%Y-%m-%d') AS dia, COUNT(*) AS total
        FROM reservas
        WHERE fecha BETWEEN " . $db->quote($hmFrom) . " AND " . $db->quote($hmTo) . "
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

} catch (Throwable $e) {
    err('Error: ' . $e->getMessage());
}

// Formatear mes en español sin strftime
function strftime_compat(int $month, string $year): string {
    $nombres = ['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return $nombres[$month] . ' ' . substr($year, 2);
}