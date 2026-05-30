<?php
// ============================================================
//  GET /api/servicios.php
//  Devuelve todos los servicios activos ordenados por precio.
// ============================================================

require_once __DIR__ . '/../config.php';

$allowed = defined('FRONTEND_URL') ? FRONTEND_URL : 'https://pradopeluqueria.infinityfree.me';
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === $allowed) {
    header('Access-Control-Allow-Origin: ' . $allowed);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $db = getDB();

    $stmt      = $db->query(
        "SELECT id, nombre, duracion, precio, categoria
         FROM servicios
         WHERE activo = 1
         ORDER BY categoria, orden ASC"
    );
    $servicios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($servicios as &$s) {
        $s['precio'] = (float)$s['precio'];
    }

    echo json_encode(['ok' => true, 'data' => $servicios], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('servicios.php PDO: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor']);
}