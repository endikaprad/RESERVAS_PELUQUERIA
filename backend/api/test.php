<?php
// Endpoint de diagnóstico — solo accesible en entorno local
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}

require_once __DIR__ . '/helpers.php';

try {
    $db = getDB();
    echo json_encode(['ok' => true, 'msg' => 'Conexion BD correcta']);
} catch (Exception $e) {
    error_log('test.php: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Error de conexión']);
}