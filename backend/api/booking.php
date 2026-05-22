<?php
// ============================================================
//  POST /api/booking.php
//  Body JSON:
//  {
//    "servicio": "corte",
//    "barbero":  "endika",
//    "fecha":    "2026-05-28",
//    "hora":     "10:00",
//    "nombre":   "Jon Ibáñez",
//    "telefono": "+34 600 000 000",
//    "email":    "jon@email.com",
//    "notas":    "opcional"
//  }
// ============================================================

require_once __DIR__ . '/_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$body = readBody();

// ── Extraer y sanear campos ──────────────────────────────────
$servicio = trim($body['servicio'] ?? '');
$barbero  = trim($body['barbero']  ?? '');
$fecha    = trim($body['fecha']    ?? '');
$hora     = trim($body['hora']     ?? '');
$nombre   = trim($body['nombre']   ?? '');
$telefono = trim($body['telefono'] ?? '');
$email    = trim($body['email']    ?? '');
$notas    = trim($body['notas']    ?? '');

// ── Validaciones ─────────────────────────────────────────────
if (!$servicio || !$barbero || !$fecha || !$hora || !$nombre || !$telefono || !$email) {
    jsonError('Faltan campos obligatorios');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    jsonError('Formato de fecha inválido (esperado YYYY-MM-DD)');
}
if (!preg_match('/^\d{2}:\d{2}$/', $hora)) {
    jsonError('Formato de hora inválido (esperado HH:MM)');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Email inválido');
}

// ── Validar que no sea en el pasado ──────────────────────────
$ahora      = new DateTime('now');
$fechaHora  = new DateTime($fecha . ' ' . $hora . ':00');
if ($fechaHora <= $ahora) {
    jsonError('No puedes reservar en el pasado ni en la hora actual');
}

// ── Insertar en BD ───────────────────────────────────────────
try {
    $db = getDB();

    // Verificar que servicio y barbero existen
    $s = $db->prepare('SELECT id FROM servicios WHERE id = ?');
    $s->execute([$servicio]);
    if (!$s->fetch()) jsonError('Servicio no encontrado');

    $b = $db->prepare('SELECT id FROM barberos WHERE id = ?');
    $b->execute([$barbero]);
    if (!$b->fetch()) jsonError('Barbero no encontrado');

    $insert = $db->prepare(
        'INSERT INTO reservas
         (servicio_id, barbero_id, fecha, hora,
          cliente_nombre, cliente_telefono, cliente_email, notas)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $servicio, $barbero, $fecha, $hora,
        $nombre, $telefono, $email, $notas
    ]);

    $id = $db->lastInsertId();

    jsonOk([
        'id'       => (int)$id,
        'mensaje'  => '¡Reserva confirmada!',
        'servicio' => $servicio,
        'barbero'  => $barbero,
        'fecha'    => $fecha,
        'hora'     => $hora,
    ]);

} catch (PDOException $e) {
    // Clave duplicada → hueco ya ocupado
    if ($e->getCode() === '23000') {
        jsonError('Ese horario ya está reservado. Por favor elige otro.', 409);
    }
    jsonError('Error de base de datos: ' . $e->getMessage(), 500);
}