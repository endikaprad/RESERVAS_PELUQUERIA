<?php
// ============================================================
//  POST /api/booking.php
// ============================================================

require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método no permitido', 405);
}

$body = readBody();

$servicio = trim($body['servicio'] ?? '');
$barbero  = trim($body['barbero']  ?? '');
$fecha    = trim($body['fecha']    ?? '');
$hora     = trim($body['hora']     ?? '');
$nombre   = trim($body['nombre']   ?? '');
$telefono = trim($body['telefono'] ?? '');
$email    = trim($body['email']    ?? '');
$notas    = trim($body['notas']    ?? '');

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

$ahora     = new DateTime('now');
$fechaHora = new DateTime($fecha . ' ' . $hora . ':00');
if ($fechaHora <= $ahora) {
    jsonError('No puedes reservar en el pasado ni en la hora actual');
}

try {
    $db = getDB();

    $s = $db->prepare('SELECT id, nombre FROM servicios WHERE id = ?');
    $s->execute([$servicio]);
    $servicioRow = $s->fetch();
    if (!$servicioRow) jsonError('Servicio no encontrado');

    $b = $db->prepare('SELECT id, nombre FROM barberos WHERE id = ?');
    $b->execute([$barbero]);
    $barberoRow = $b->fetch();
    if (!$barberoRow) jsonError('Barbero no encontrado');

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

    // ── Formatear fecha en español ───────────────────────────
    $dias   = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses  = ['enero','febrero','marzo','abril','mayo','junio',
               'julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $dt     = new DateTime($fecha);
    $fechaFormateada = $dias[$dt->format('w')] . ', ' .
                       $dt->format('j') . ' de ' .
                       $meses[(int)$dt->format('n') - 1] . ' de ' .
                       $dt->format('Y');

    // ── Email al PELUQUERO ───────────────────────────────────
    $asuntoPeluquero = "Nueva reserva · {$nombre} · {$fechaFormateada} {$hora}";
    $cuerpoTxt = "NUEVA RESERVA — PRADO BARBER CO.\n\n";
    $cuerpoTxt .= "Cliente:   {$nombre}\n";
    $cuerpoTxt .= "Teléfono:  {$telefono}\n";
    $cuerpoTxt .= "Email:     {$email}\n\n";
    $cuerpoTxt .= "Servicio:  {$servicioRow['nombre']}\n";
    $cuerpoTxt .= "Barbero:   {$barberoRow['nombre']}\n";
    $cuerpoTxt .= "Fecha:     {$fechaFormateada}\n";
    $cuerpoTxt .= "Hora:      {$hora}\n";
    if ($notas) $cuerpoTxt .= "Notas:     {$notas}\n";
    $cuerpoTxt .= "\nReserva #{$id} guardada en el sistema.";

    $headersPeluquero  = "From: reservas@pradopeluqueria.infinityfree.me\r\n";
    $headersPeluquero .= "Reply-To: {$email}\r\n";
    $headersPeluquero .= "Content-Type: text/plain; charset=UTF-8\r\n";

    mail('endikrapradodev@gmail.com', $asuntoPeluquero, $cuerpoTxt, $headersPeluquero);

    // ── Email de confirmación al CLIENTE ─────────────────────
    $asuntoCliente = "Reserva confirmada · Prado Barber Co.";
    $cuerpoCliente  = "Hola {$nombre},\n\n";
    $cuerpoCliente .= "Tu reserva en Prado Barber Co. está confirmada:\n\n";
    $cuerpoCliente .= "  Servicio:  {$servicioRow['nombre']}\n";
    $cuerpoCliente .= "  Barbero:   {$barberoRow['nombre']}\n";
    $cuerpoCliente .= "  Fecha:     {$fechaFormateada}\n";
    $cuerpoCliente .= "  Hora:      {$hora}\n\n";
    $cuerpoCliente .= "Si necesitas cancelar o cambiar tu cita, llámanos:\n";
    $cuerpoCliente .= "  +34 944 000 000\n";
    $cuerpoCliente .= "  Gran Vía, 12 · Bilbao\n\n";
    $cuerpoCliente .= "¡Hasta pronto!\n";
    $cuerpoCliente .= "El equipo de Prado Barber Co.";

    $headersCliente  = "From: reservas@pradopeluqueria.infinityfree.me\r\n";
    $headersCliente .= "Content-Type: text/plain; charset=UTF-8\r\n";

    mail($email, $asuntoCliente, $cuerpoCliente, $headersCliente);

    jsonOk([
        'id'      => (int)$id,
        'mensaje' => '¡Reserva confirmada!',
        'servicio'=> $servicio,
        'barbero' => $barbero,
        'fecha'   => $fecha,
        'hora'    => $hora,
    ]);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonError('Ese horario ya está reservado. Por favor elige otro.', 409);
    }
    jsonError('Error de base de datos: ' . $e->getMessage(), 500);
}