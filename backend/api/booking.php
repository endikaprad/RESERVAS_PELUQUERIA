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

    $token = bin2hex(random_bytes(32));

    $insert = $db->prepare(
        "INSERT INTO reservas
         (servicio_id, barbero_id, fecha, hora,
          cliente_nombre, cliente_telefono, cliente_email, notas, estado, token)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)"
    );
    $insert->execute([
        $servicio, $barbero, $fecha, $hora,
        $nombre, $telefono, $email, $notas, $token
    ]);

    $id = $db->lastInsertId();

    $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['enero','febrero','marzo','abril','mayo','junio',
              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $dt    = new DateTime($fecha);
    $fechaFormateada = $dias[$dt->format('w')] . ', ' .
                       $dt->format('j') . ' de ' .
                       $meses[(int)$dt->format('n') - 1] . ' de ' .
                       $dt->format('Y');

    $baseUrl    = 'https://pradopeluqueria.infinityfree.me';
    $urlAceptar = $baseUrl . '/backend/api/reserva-action.php?token=' . $token . '&accion=aceptar';
    $urlDenegar = $baseUrl . '/backend/api/reserva-action.php?token=' . $token . '&accion=denegar';

    $notasHtml = $notas
        ? "<tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Notas</td>
           <td style='padding:10px 0;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($notas) . "</td></tr>"
        : '';

    // ── Email para el PELUQUERO ──────────────────────────────
    $htmlPeluquero = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#d42b2b;padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>&#128203; Nueva reserva pendiente</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>Prado Barber Co. &mdash; Necesita tu confirmación</p>
    </div>
    <div style='padding:32px;'>
      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Cliente</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-weight:600;'>" . htmlspecialchars($nombre) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Teléfono</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($telefono) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Email</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($email) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Servicio</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($servicioRow['nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td></tr>
        <tr><td style='padding:10px 0;" . ($notas ? 'border-bottom:1px solid #252530;' : '') . "color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;" . ($notas ? 'border-bottom:1px solid #252530;' : '') . "color:#d42b2b;font-size:16px;font-weight:700;'>{$hora}</td></tr>
        {$notasHtml}
      </table>
      <p style='color:#f0ece3;font-size:15px;font-weight:600;margin-bottom:20px;text-align:center;'>¿Puedes atender esta reserva?</p>
      <table style='width:100%;border-collapse:collapse;'>
        <tr>
          <td style='padding-right:8px;'>
            <a href='{$urlAceptar}' style='display:block;background:#22c55e;color:#fff;text-decoration:none;
               padding:14px;border-radius:6px;font-size:14px;font-weight:700;letter-spacing:0.1em;
               text-transform:uppercase;text-align:center;'>✓ Aceptar reserva</a>
          </td>
          <td style='padding-left:8px;'>
            <a href='{$urlDenegar}' style='display:block;background:#d42b2b;color:#fff;text-decoration:none;
               padding:14px;border-radius:6px;font-size:14px;font-weight:700;letter-spacing:0.1em;
               text-transform:uppercase;text-align:center;'>✕ Denegar reserva</a>
          </td>
        </tr>
      </table>
      <p style='color:#7a7880;font-size:12px;margin-top:24px;text-align:center;'>
        Reserva #{$id} &middot; <a href='{$baseUrl}/backend/admin.php' style='color:#d42b2b;'>Panel de administración</a>
      </p>
    </div>
  </div>
</body></html>";

    // ── Email de CONFIRMACIÓN PENDIENTE para el CLIENTE ──────
    $htmlCliente = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#c9a84c;padding:24px 32px;'>
      <h1 style='margin:0;color:#000;font-size:20px;font-weight:700;'>&#9200; Reserva pendiente de confirmación</h1>
      <p style='margin:6px 0 0;color:rgba(0,0,0,0.6);font-size:14px;'>Prado Barber Co. &mdash; Bilbao</p>
    </div>
    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;margin-bottom:24px;'>
        Hola <strong>" . htmlspecialchars($nombre) . "</strong>,<br><br>
        Hemos recibido tu solicitud de cita. El barbero la revisará y recibirás un email de confirmación en breve.
      </p>
      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Servicio</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-weight:600;'>" . htmlspecialchars($servicioRow['nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Barbero</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($barberoRow['nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td></tr>
        <tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;color:#c9a84c;font-size:16px;font-weight:700;'>{$hora}</td></tr>
      </table>
      <div style='background:#18181f;border:1px solid #252530;border-radius:8px;padding:16px;margin-bottom:24px;text-align:center;'>
        <p style='color:#7a7880;font-size:13px;margin:0;'>
          ¿Necesitas cancelar? Llámanos al<br>
          <a href='tel:+34944000000' style='color:#d42b2b;font-size:15px;font-weight:600;text-decoration:none;'>+34 944 000 000</a>
        </p>
      </div>
    </div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>
        &copy; 2026 Prado Barber Co. &mdash; Hecho con precisión en Bilbao
      </p>
    </div>
  </div>
</body></html>";

    // ── Enviar ambos emails ──────────────────────────────────
    sendResend(
        'endikapradodev@gmail.com',
        "📋 Nueva reserva #" . $id . " - " . htmlspecialchars($nombre) . " - {$fechaFormateada} {$hora}",
        $htmlPeluquero
    );
    sendResend(
        $email,
        '⏳ Reserva pendiente de confirmación - Prado Barber Co.',
        $htmlCliente
    );

    jsonOk([
        'id'      => (int)$id,
        'mensaje' => 'Solicitud enviada. Te confirmaremos por email en breve.',
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

// ── Envío via Resend con timeout y logging ───────────────────
function sendResend(string $to, string $subject, string $html): bool {
    $apiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : '';
    if (!$apiKey) return false;

    $payload = json_encode([
        'from'    => 'Prado Barber Co. <onboarding@resend.dev>',
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $html,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    // Log si falla (visible en error_log del servidor)
    if ($httpCode !== 200 && $httpCode !== 201) {
        error_log("Resend fallo [{$httpCode}] to:{$to} err:{$error} resp:{$resp}");
    }

    return $httpCode === 200 || $httpCode === 201;
}