<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/cliente-stats.php
//  GET ?telefono=+34600000000
//  Devuelve estadísticas completas del cliente por teléfono.
// ============================================================

require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function csOk(mixed $d): never
{
    echo json_encode(['ok' => true, 'data' => $d], JSON_UNESCAPED_UNICODE);
    exit;
}
function csErr(string $m, int $c = 400): never
{
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}

// Acepta tanto ?telefono= como ?email= (retrocompatibilidad)
$telefono = trim($_GET['telefono'] ?? '');
$email    = trim($_GET['email']    ?? '');

if (!$telefono && !$email) csErr('Se requiere telefono o email');

try {
    $db = getDB();

    // ── Construir WHERE ──────────────────────────────────────
    // Busca por teléfono (prioritario) o por email como fallback
    if ($telefono) {
        // Normalizar: quitar espacios y guiones para comparación flexible
        $telefonoLimpio = preg_replace('/[\s\-]/', '', $telefono);
        // Buscar tanto con el valor exacto como sin prefijo
        $where  = "(REPLACE(REPLACE(cliente_telefono,' ',''),'-','') = ? OR REPLACE(REPLACE(cliente_telefono,' ',''),'-','') = ?)";
        $params = [$telefonoLimpio, $telefonoLimpio];
    } else {
        $where  = 'cliente_email = ?';
        $params = [$email];
    }

    // ── Historial completo ───────────────────────────────────
    $stmtHist = $db->prepare(
        "SELECT r.id, r.fecha, r.hora, r.estado, r.creado_en,
                s.nombre AS servicio, s.precio,
                b.nombre AS barbero
         FROM reservas r
         JOIN servicios s ON s.id = r.servicio_id
         JOIN barberos  b ON b.id = r.barbero_id
         WHERE {$where}
         ORDER BY r.fecha DESC, r.hora DESC"
    );
    $stmtHist->execute($params);
    $historial = $stmtHist->fetchAll();

    if (empty($historial)) {
        csOk([
            'visitas_total'     => 0,
            'gasto_total'       => 0,
            'ticket_medio'      => 0,
            'ultimas_6meses'    => 0,
            'cancelaciones'     => 0,
            'frecuencia_semanas' => null,
            'cliente_desde'     => null,
            'nivel'             => nivelCliente(0),
            'progreso_siguiente' => null,
            'preferencias'      => [],
            'gasto_por_servicio' => [],
            'insights'          => [['icon' => 'ti-info-circle', 'color' => '#7a7880', 'bg' => 'rgba(122,120,128,.1)', 'texto' => 'Este cliente no tiene visitas registradas todavía.']],
            'historial'         => [],
            'nota_interna'      => '',
        ]);
    }

    // ── KPIs básicos ─────────────────────────────────────────
    $visitasOk   = array_filter($historial, fn($r) => in_array($r['estado'], ['aceptada', 'pendiente']));
    $canceladas  = array_filter($historial, fn($r) => in_array($r['estado'], ['cancelada', 'denegada']));
    $totalVisitas = count($visitasOk);
    $totalCancel  = count($canceladas);
    $gastoTotal   = array_sum(array_column(array_values($visitasOk), 'precio'));
    $ticketMedio  = $totalVisitas > 0 ? round($gastoTotal / $totalVisitas, 1) : 0;

    // Últimas 6 meses
    $hace6m = (new DateTime())->modify('-6 months')->format('Y-m-d');
    $ult6m  = count(array_filter($visitasOk, fn($r) => $r['fecha'] >= $hace6m));

    // Frecuencia media (semanas entre visitas)
    $fechas = array_column(array_values($visitasOk), 'fecha');
    sort($fechas);
    $frecuencia = null;
    if (count($fechas) >= 2) {
        $total_dias = 0;
        for ($i = 1; $i < count($fechas); $i++) {
            $total_dias += (new DateTime($fechas[$i - 1]))->diff(new DateTime($fechas[$i]))->days;
        }
        $media_dias = $total_dias / (count($fechas) - 1);
        $frecuencia = round($media_dias / 7, 1);
    }

    // Fecha primera visita
    $clienteDesde = null;
    if (!empty($fechas)) {
        $dt = new DateTime($fechas[0]);
        $clienteDesde = $dt->format('d/m/Y');
    }

    // ── Nivel de fidelidad ───────────────────────────────────
    $nivel = nivelCliente($totalVisitas);

    // Progreso al siguiente nivel
    $progresoSiguiente = progresoNivel($totalVisitas);

    // ── Preferencias detectadas ──────────────────────────────
    $preferencias = [];

    // Barbero favorito
    $barberoCount = [];
    foreach ($visitasOk as $v) {
        $barberoCount[$v['barbero']] = ($barberoCount[$v['barbero']] ?? 0) + 1;
    }
    if (!empty($barberoCount)) {
        arsort($barberoCount);
        $favBarbero = array_key_first($barberoCount);
        $preferencias[] = ['icon' => 'ti-scissors', 'color' => '#d42b2b', 'label' => 'Prefiere: ' . $favBarbero];
    }

    // Servicio favorito
    $svcCount = [];
    foreach ($visitasOk as $v) {
        $svcCount[$v['servicio']] = ($svcCount[$v['servicio']] ?? 0) + 1;
    }
    if (!empty($svcCount)) {
        arsort($svcCount);
        $favSvc = array_key_first($svcCount);
        $preferencias[] = ['icon' => 'ti-star', 'color' => '#c9a84c', 'label' => $favSvc];
    }

    // Franja horaria favorita
    $franjas = [];
    foreach ($visitasOk as $v) {
        $h = (int)substr($v['hora'], 0, 2);
        $franja = $h < 14 ? 'Mañanas' : 'Tardes';
        $franjas[$franja] = ($franjas[$franja] ?? 0) + 1;
    }
    if (!empty($franjas)) {
        arsort($franjas);
        $favFranja = array_key_first($franjas);
        $preferencias[] = ['icon' => 'ti-clock', 'color' => '#6b9fff', 'label' => $favFranja];
    }

    // Frecuencia tag
    if ($frecuencia !== null) {
        if ($frecuencia <= 3) $freqLabel = 'Cliente semanal';
        elseif ($frecuencia <= 5) $freqLabel = 'Visita quincenal';
        elseif ($frecuencia <= 8) $freqLabel = 'Visita mensual';
        else $freqLabel = 'Visita esporádica';
        $preferencias[] = ['icon' => 'ti-repeat', 'color' => '#22c55e', 'label' => $freqLabel];
    }

    // ── Gasto por servicio (con colores) ─────────────────────
    $gastoSvc = [];
    foreach ($visitasOk as $v) {
        if (!isset($gastoSvc[$v['servicio']])) {
            $gastoSvc[$v['servicio']] = ['gasto' => 0, 'count' => 0];
        }
        $gastoSvc[$v['servicio']]['gasto'] += $v['precio'];
        $gastoSvc[$v['servicio']]['count']++;
    }
    arsort($gastoSvc);
    $maxGastoSvc = max(array_column($gastoSvc, 'gasto') ?: [1]);
    $colors = ['#d42b2b', '#c9a84c', '#2550a0', '#22c55e', '#f59e0b', '#a78bfa'];
    $gastoPorSvc = [];
    $ci = 0;
    foreach ($gastoSvc as $nombre => $data) {
        $pct = $maxGastoSvc > 0 ? round($data['gasto'] / $maxGastoSvc * 100) : 0;
        $gastoPorSvc[] = [
            'nombre' => $nombre,
            'gasto'  => number_format($data['gasto'], 0),
            'count'  => $data['count'],
            'pct'    => $pct,
            'color'  => $colors[$ci % count($colors)],
        ];
        $ci++;
    }

    // ── Insights automáticos ─────────────────────────────────
    $insights = [];

    if ($totalVisitas === 0) {
        $insights[] = insight('ti-info-circle', '#7a7880', 'rgba(122,120,128,.1)', 'Sin historial de visitas registradas aún.');
    } else {
        if ($totalVisitas >= 10) {
            $insights[] = insight('ti-award', '#c9a84c', 'rgba(201,168,76,.1)', "Cliente fidelizado con {$totalVisitas} visitas. ¡Un gran activo del negocio!");
        } elseif ($totalVisitas >= 5) {
            $insights[] = insight('ti-thumb-up', '#22c55e', 'rgba(34,197,94,.1)', "Cliente recurrente con {$totalVisitas} visitas registradas.");
        }

        if ($frecuencia !== null && $frecuencia <= 3) {
            $insights[] = insight('ti-repeat', '#6b9fff', 'rgba(37,80,160,.1)', "Alta frecuencia: visita cada {$frecuencia} semanas de media.");
        }

        if ($totalCancel > 0 && $totalVisitas > 0) {
            $pctCancel = round($totalCancel / ($totalVisitas + $totalCancel) * 100);
            if ($pctCancel >= 30) {
                $insights[] = insight('ti-alert-triangle', '#f59e0b', 'rgba(245,158,11,.1)', "Tasa de cancelación del {$pctCancel}%. Considera recordatorios previos.");
            }
        }

        if ($ult6m === 0 && $totalVisitas > 0) {
            $insights[] = insight('ti-clock', '#d42b2b', 'rgba(212,43,43,.1)', 'Sin visitas en los últimos 6 meses. Podría estar inactivo.');
        } elseif ($ult6m >= 4) {
            $insights[] = insight('ti-trending-up', '#22c55e', 'rgba(34,197,94,.1)', "Muy activo: {$ult6m} visitas en los últimos 6 meses.");
        }

        if ($gastoTotal >= 200) {
            $insights[] = insight('ti-coins', '#c9a84c', 'rgba(201,168,76,.1)', "Alto valor: ha generado " . number_format($gastoTotal, 0) . " € en ingresos totales.");
        }
    }

    // ── Nota interna ─────────────────────────────────────────
    $notaInterna = '';
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS notas_cliente (
            id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            telefono  VARCHAR(60) NOT NULL,
            nota      TEXT NOT NULL,
            updated   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_telefono (telefono)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Buscar por teléfono o por email (buscamos el teléfono del cliente en reservas)
        if ($telefono) {
            $stmtNota = $db->prepare("SELECT nota FROM notas_cliente WHERE telefono = ?");
            $stmtNota->execute([$telefonoLimpio]);
        } else {
            // Obtener el teléfono del cliente por email para buscar la nota
            $stmtTel = $db->prepare("SELECT cliente_telefono FROM reservas WHERE cliente_email = ? LIMIT 1");
            $stmtTel->execute([$email]);
            $telRow = $stmtTel->fetch();
            $telParaNota = $telRow ? preg_replace('/[\s\-]/', '', $telRow['cliente_telefono']) : $email;

            $stmtNota = $db->prepare("SELECT nota FROM notas_cliente WHERE telefono = ?");
            $stmtNota->execute([$telParaNota]);
        }
        $notaRow = $stmtNota->fetch();
        $notaInterna = $notaRow ? $notaRow['nota'] : '';
    } catch (Exception $e) {
        $notaInterna = '';
    }

    // ── Formatear historial ───────────────────────────────────
    $histFormatted = [];
    $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    foreach (array_slice($historial, 0, 20) as $h) {
        $dt = new DateTime($h['fecha']);
        $histFormatted[] = [
            'fecha'    => $dt->format('j') . ' ' . $meses[(int)$dt->format('n')] . ' ' . $dt->format('Y'),
            'hora'     => substr($h['hora'], 0, 5),
            'servicio' => $h['servicio'],
            'barbero'  => $h['barbero'],
            'precio'   => number_format($h['precio'], 0),
            'estado'   => $h['estado'],
        ];
    }

    csOk([
        'visitas_total'      => $totalVisitas,
        'gasto_total'        => number_format($gastoTotal, 0),
        'ticket_medio'       => number_format($ticketMedio, 0),
        'ultimas_6meses'     => $ult6m,
        'cancelaciones'      => $totalCancel,
        'frecuencia_semanas' => $frecuencia,
        'cliente_desde'      => $clienteDesde,
        'nivel'              => $nivel,
        'progreso_siguiente' => $progresoSiguiente,
        'preferencias'       => $preferencias,
        'gasto_por_servicio' => $gastoPorSvc,
        'insights'           => $insights,
        'historial'          => $histFormatted,
        'nota_interna'       => $notaInterna,
    ]);
} catch (PDOException $e) {
    csErr('Error de base de datos: ' . $e->getMessage(), 500);
}

// ── Helpers ───────────────────────────────────────────────────
function nivelCliente(int $visitas): array
{
    if ($visitas >= 20) return ['nombre' => 'Platino', 'icon' => 'ti-diamond',     'color_text' => '#a78bfa', 'color_bg' => 'rgba(167,139,250,.12)'];
    if ($visitas >= 10) return ['nombre' => 'Oro',     'icon' => 'ti-award',       'color_text' => '#c9a84c', 'color_bg' => 'rgba(201,168,76,.12)'];
    if ($visitas >= 5)  return ['nombre' => 'Plata',   'icon' => 'ti-medal',       'color_text' => '#9ca3af', 'color_bg' => 'rgba(156,163,175,.12)'];
    if ($visitas >= 1)  return ['nombre' => 'Bronce',  'icon' => 'ti-user-check',  'color_text' => '#cd7c3a', 'color_bg' => 'rgba(205,124,58,.12)'];
    return              ['nombre' => 'Nuevo',   'icon' => 'ti-user',        'color_text' => '#7a7880', 'color_bg' => 'rgba(122,120,128,.12)'];
}

function progresoNivel(int $visitas): ?array
{
    $niveles = [1 => 'Bronce', 5 => 'Plata', 10 => 'Oro', 20 => 'Platino'];
    foreach ($niveles as $umbral => $nombre) {
        if ($visitas < $umbral) {
            $anteriorUmbral = 0;
            $umbrales = array_keys($niveles);
            $idx = array_search($umbral, $umbrales);
            if ($idx > 0) $anteriorUmbral = $umbrales[$idx - 1];
            $rango = $umbral - $anteriorUmbral;
            $progreso = $visitas - $anteriorUmbral;
            $pct = min(100, round($progreso / $rango * 100));
            return ['label' => 'Siguiente: nivel ' . $nombre, 'faltan' => $umbral - $visitas, 'pct' => $pct];
        }
    }
    return null; // Nivel máximo
}

function insight(string $icon, string $color, string $bg, string $texto): array
{
    return ['icon' => $icon, 'color' => $color, 'bg' => $bg, 'texto' => $texto];
}
