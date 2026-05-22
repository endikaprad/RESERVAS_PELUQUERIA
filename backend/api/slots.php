<?php
// ============================================================
//  GET /api/slots.php?fecha=YYYY-MM-DD&barbero=endika
//
//  Devuelve las horas YA RESERVADAS para ese barbero+día,
//  para que el frontend las marque como "taken".
// ============================================================

require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método no permitido', 405);
}

$fecha   = trim($_GET['fecha']   ?? '');
$barbero = trim($_GET['barbero'] ?? '');

// Validaciones básicas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    jsonError('Parámetro "fecha" inválido. Formato esperado: YYYY-MM-DD');
}
if ($barbero === '') {
    jsonError('Parámetro "barbero" requerido');
}

try {
    $db = getDB();

    // Horas ocupadas de ese barbero en esa fecha
    $stmt = $db->prepare(
        'SELECT TIME_FORMAT(hora, "%H:%i") AS hora
         FROM reservas
         WHERE barbero_id = :barbero AND fecha = :fecha'
    );
    $stmt->execute([':barbero' => $barbero, ':fecha' => $fecha]);
    $ocupadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    jsonOk(['ocupadas' => $ocupadas, 'fecha' => $fecha, 'barbero' => $barbero]);

} catch (PDOException $e) {
    jsonError('Error de base de datos: ' . $e->getMessage(), 500);
}