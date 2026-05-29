<?php
// ============================================================
//  GET /api/blocked-days.php?year=2026&month=6
//
//  Devuelve todos los días bloqueados de un mes concreto.
//  El frontend lo usa para pintar el calendario antes de
//  que el usuario haga clic en ningún día.
// ============================================================

require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

try {
    $db = getDB();

    // Crear la tabla si aún no existe (por si acaso)
    $db->exec("
        CREATE TABLE IF NOT EXISTS dias_bloqueados (
            id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            fecha     DATE         NOT NULL,
            motivo    VARCHAR(200) NOT NULL DEFAULT 'Vacaciones',
            creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fecha (fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $desde = sprintf('%04d-%02d-01', $year, $month);
    $hasta = date('Y-m-t', strtotime($desde)); // último día del mes

    $stmt = $db->prepare(
        'SELECT DATE_FORMAT(fecha, "%Y-%m-%d") AS fecha, motivo
         FROM dias_bloqueados
         WHERE fecha BETWEEN ? AND ?
         ORDER BY fecha ASC'
    );
    $stmt->execute([$desde, $hasta]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Devolver un objeto { "2026-06-01": "Vacaciones", ... }
    $map = [];
    foreach ($rows as $r) {
        $map[$r['fecha']] = $r['motivo'];
    }

    echo json_encode(['ok' => true, 'data' => $map], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('blocked-days.php PDO: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error interno del servidor']);
}