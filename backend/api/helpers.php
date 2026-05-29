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