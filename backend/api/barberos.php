<?php
// ============================================================
//  GET /api/barberos.php
//  Devuelve todos los barberos activos ordenados por nombre.
// ============================================================

require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

try {
    $db = getDB();

    // Crear columna 'activo' si no existe (migración automática)
    try {
        $db->exec("ALTER TABLE barberos ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1");
    } catch (PDOException $e) {
        // Ya existe — ignorar
    }

    $stmt = $db->query(
        "SELECT id, nombre, especialidad, iniciales
         FROM barberos
         WHERE activo = 1
         ORDER BY nombre ASC"
    );
    $barberos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'data' => $barberos], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}