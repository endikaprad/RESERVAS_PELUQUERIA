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

    // Token único para aceptar/denegar
    $token = bin2hex(random_bytes(32));

    $insert = $db->prepare(
        'INSERT INTO reservas
         (servicio_id, barbero_id, fecha, hora,
          cliente_nombre, cliente_telefono, cliente_email, notas, estado, token)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pendiente\', ?)'
    );
    $insert->execute([
        $servicio, $barbero, $fecha, $hora,
        $nombre, $telefono, $email, $notas, $token
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

    // ── URL base de la web ───────────────────────────────────
    $baseUrl = 'https://pradopeluqueria.infinityfree.me';

    $urlAceptar = $baseUrl . '/backend/api/reserva-action.php?token=' . $token . '&accion=aceptar';
    $urlDenegar = $baseUrl . '/backend/api/reserva-action.php?token=' . $token . '&accion=denegar';

    // ── Email al PELUQUERO ───────────────────────────────────
    $asuntoPeluquero = "⏳ Nueva reserva pendiente · {$nombre} · {$fechaFormateada} {$hora}";

    $cuerpoPeluquero = "
<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>

    <div style='background:#d42b2b;padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>⏳ Nueva reserva pendiente</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>Prado Barber Co. · Necesita tu confirmación</p>
    </div>

    <div style='padding:32px;'>
      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Cliente</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-weight:600;'>{$nombre}</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Teléfono</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$telefono}</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Email</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$email}</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Servicio</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$servicioRow['nombre']}</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-size:16px;font-weight:700;color:#d42b2b;'>{$hora}</td></tr>" .
        ($notas ? "<tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Notas</td>
            <td style='padding:10px 0;color:#f0ece3;font-size:13px;'>{$notas}</td></tr>" : '') . "
      </table>

      <p style='color:#f0ece3;font-size:15px;font-weight:600;margin-bottom:20px;text-align:center;'>
        ¿Puedes atender esta reserva?
      </p>

      <div style='display:flex;gap:12px;text-align:center;'>
        <a href='{$urlAceptar}'
           style='display:inline-block;flex:1;background:#22c55e;color:#fff;text-decoration:none;
                  padding:14px 28px;border-radius:6px;font-size:14px;font-weight:700;
                  letter-spacing:0.1em;text-transform:uppercase;margin-right:8px;'>
          ✓ Aceptar reserva
        </a>
        <a href='{$urlDenegar}'
           style='display:inline-block;flex:1;background:#d42b2b;color:#fff;text-decoration:none;
                  padding:14px 28px;border-radius:6px;font-size:14px;font-weight:700;
                  letter-spacing:0.1em;text-transform:uppercase;'>
          ✕ Denegar reserva
        </a>
      </div>

      <p style='color:#7a7880;font-size:12px;margin-top:24px;text-align:center;'>
        Reserva #{$id} · También puedes gestionarla desde el
        <a href='{$baseUrl}/backend/admin.php' style='color:#d42b2b;'>panel de administración</a>
      </p>
    </div>

  </div>
</body>
</html>";

    $headersPeluquero  = "From: reservas@pradopeluqueria.infinityfree.me\r\n";
    $headersPeluquero .= "Reply-To: {$email}\r\n";
    $headersPeluquero .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headersPeluquero .= "MIME-Version: 1.0\r\n";

    mail('endikapradodev@gmail.com', $asuntoPeluquero, $cuerpoPeluquero, $headersPeluquero);

    // ── Email de PENDIENTE al CLIENTE ────────────────────────
    $asuntoCliente = "Reserva recibida · Prado Barber Co. — Pendiente de confirmación";

    $cuerpoCliente = "
<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>

    <div style='background:#c9a84c;padding:24px 32px;'>
      <h1 style='margin:0;color:#000;font-size:20px;font-weight:700;'>⏳ Reserva pendiente de confirmación</h1>
      <p style='margin:6px 0 0;color:rgba(0,0,0,0.65);font-size:14px;'>Prado Barber Co. · Bilbao</p>
    </div>

    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;margin-bottom:24px;'>
        Hola <strong>{$nombre}</strong>, hemos recibido tu solicitud de reserva.<br>
        En breve te confirmaremos por email si está disponible.
      </p>

      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Servicio</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-weight:600;'>{$servicioRow['nombre']}</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Barbero</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$barberoRow['nombre']}</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td></tr>
        <tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;color:#f0ece3;font-size:16px;font-weight:700;color:#c9a84c;'>{$hora}</td></tr>
      </table>

      <p style='color:#7a7880;font-size:13px;text-align:center;'>
        ¿Necesitas cancelar o cambiar tu cita?<br>
        <a href='tel:+34944000000' style='color:#d42b2b;'>+34 944 000 000</a> ·
        Gran Vía, 12 · Bilbao
      </p>
    </div>

    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>
        © 2026 Prado Barber Co. · Hecho con precisión en Bilbao ✦
      </p>
    </div>
  </div>
</body>
</html>";

    $headersCliente  = "From: reservas@pradopeluqueria.infinityfree.me\r\n";
    $headersCliente .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headersCliente .= "MIME-Version: 1.0\r\n";

    mail($email, $asuntoCliente, $cuerpoCliente, $headersCliente);

    jsonOk([
        'id'      => (int)$id,
        'mensaje' => '¡Solicitud enviada! Te confirmaremos por email en breve.',
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