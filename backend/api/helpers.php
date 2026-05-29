<?php
// ============================================================
//  PRADO BARBER CO. — Helpers comunes para la API REST
// ============================================================

require_once __DIR__ . '/../config.php';

// ── CORS ────────────────────────────────────────────────────
$allowed = defined('FRONTEND_URL') ? FRONTEND_URL : 'https://pradopeluqueria.infinityfree.me';
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $allowed) {
    header('Access-Control-Allow-Origin: ' . $allowed);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Preflight OPTIONS → respuesta vacía 200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Respuestas JSON ─────────────────────────────────────────
function jsonOk(mixed $data): never {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Leer body JSON del request ───────────────────────────────
function readBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ── Generación de slots desde horario configurado ─────────────
function generarSlots(string $inicio, string $fin, int $intervalo): array {
    $slots = [];
    [$hI, $mI] = array_map('intval', explode(':', $inicio));
    [$hF, $mF] = array_map('intval', explode(':', $fin));
    $minIni = $hI * 60 + $mI;
    $minFin = $hF * 60 + $mF;
    if ($intervalo <= 0 || $minIni >= $minFin) return [];
    for ($t = $minIni; $t < $minFin; $t += $intervalo) {
        $slots[] = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
    }
    return $slots;
}

function getHorarioCfg(PDO $db): array {
    $defaults = [
        'horario_manana_activo'  => '1',
        'horario_manana_inicio'  => '09:00',
        'horario_manana_fin'     => '14:00',
        'horario_tarde_activo'   => '1',
        'horario_tarde_inicio'   => '16:00',
        'horario_tarde_fin'      => '20:00',
        'horario_intervalo'      => '30',
        'horario_dias_abiertos'  => '1,2,3,4,5,6',
    ];
    try {
        $rows = $db->query(
            "SELECT clave, valor FROM configuracion WHERE clave LIKE 'horario_%'"
        )->fetchAll();
        foreach ($rows as $r) $defaults[$r['clave']] = $r['valor'];
    } catch (Exception $e) {}
    return $defaults;
}

function getSlotsParaDia(PDO $db, int $diaSemana): array {
    $cfg          = getHorarioCfg($db);
    $diasAbiertos = array_map('intval', explode(',', $cfg['horario_dias_abiertos']));
    if (!in_array($diaSemana, $diasAbiertos, true)) return [];
    $intervalo = max(1, (int)$cfg['horario_intervalo']);
    $manana    = $cfg['horario_manana_activo'] === '1'
        ? generarSlots($cfg['horario_manana_inicio'], $cfg['horario_manana_fin'], $intervalo) : [];
    $tarde     = $cfg['horario_tarde_activo'] === '1'
        ? generarSlots($cfg['horario_tarde_inicio'], $cfg['horario_tarde_fin'], $intervalo) : [];
    return array_merge($manana, $tarde);
}