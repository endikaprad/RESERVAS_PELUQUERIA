<?php
// ============================================================
//  POST /api/booking.php  — FIXED
//  Fix: unique constraint allows cancelled bookings to be re-booked
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

$tz    = new DateTimeZone('Europe/Madrid');
$ahora = new DateTime('now', $tz);

$fechaHora = new DateTime($fecha . ' ' . $hora . ':00', $tz);
if ($fechaHora <= $ahora) {
    jsonError('No puedes reservar en el pasado ni en la hora actual');
}

try {
    $db = getDB();

    // ── Validar servicio y barbero ────────────────────────────
    $s = $db->prepare('SELECT id, nombre FROM servicios WHERE id = ?');
    $s->execute([$servicio]);
    $servicioRow = $s->fetch();
    if (!$servicioRow) jsonError('Servicio no encontrado');

    $b = $db->prepare('SELECT id, nombre FROM barberos WHERE id = ?');
    $b->execute([$barbero]);
    $barberoRow = $b->fetch();
    if (!$barberoRow) jsonError('Barbero no encontrado');

    // ── Comprobar disponibilidad real (excluyendo canceladas/denegadas) ──
    // FIX: La UNIQUE KEY de BD bloquea aunque el estado sea 'cancelada'.
    // Solución: antes de insertar, eliminar filas canceladas/denegadas
    // que coincidan con el mismo barbero+fecha+hora para liberar el slot.
    $cleanUp = $db->prepare(
        "DELETE FROM reservas
         WHERE barbero_id = ? AND fecha = ? AND hora = ?
           AND estado IN ('cancelada', 'denegada')"
    );
    $cleanUp->execute([$barbero, $fecha, $hora . ':00']);

    // Verificar que el slot sigue libre tras la limpieza
    $checkSlot = $db->prepare(
        "SELECT COUNT(*) FROM reservas
         WHERE barbero_id = ? AND fecha = ? AND hora = ?
           AND estado IN ('pendiente', 'aceptada', 'reprogramar_barbero', 'reprogramar_cliente')"
    );
    $checkSlot->execute([$barbero, $fecha, $hora . ':00']);
    if ((int)$checkSlot->fetchColumn() > 0) {
        jsonError('Ese horario ya está reservado. Por favor elige otro.', 409);
    }

    // ── Leer configuración de auto-aceptar ───────────────────
    $autoAceptar      = 'no';
    $autoAceptarHasta = '';
    try {
        $cfgStmt = $db->prepare(
            "SELECT clave, valor FROM configuracion WHERE clave IN ('auto_aceptar', 'auto_aceptar_hasta')"
        );
        $cfgStmt->execute();
        foreach ($cfgStmt->fetchAll() as $row) {
            if ($row['clave'] === 'auto_aceptar')       $autoAceptar      = $row['valor'];
            if ($row['clave'] === 'auto_aceptar_hasta') $autoAceptarHasta = $row['valor'];
        }
    } catch (Exception $e) {
        $autoAceptar = 'no';
    }

    // ── Determinar si esta reserva se auto-acepta ─────────────
    $estadoFinal = 'pendiente';

    if ($autoAceptar !== 'no' && $autoAceptarHasta !== '') {
        $hoyStr   = $ahora->format('Y-m-d');
        $hastaStr = $autoAceptarHasta;
        if ($hoyStr <= $hastaStr) {
            $estadoFinal = 'aceptada';
        }
    } elseif ($autoAceptar === 'siempre') {
        $estadoFinal = 'aceptada';
    }

    // ── Insertar reserva ─────────────────────────────────────
    $token = bin2hex(random_bytes(32));

    $insert = $db->prepare(
        "INSERT INTO reservas
         (servicio_id, barbero_id, fecha, hora,
          cliente_nombre, cliente_telefono, cliente_email, notas, estado, token)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insert->execute([
        $servicio, $barbero, $fecha, $hora,
        $nombre, $telefono, $email, $notas, $estadoFinal, $token
    ]);

    $id = $db->lastInsertId();

    // ── Formatear fecha legible ───────────────────────────────
    $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['enero','febrero','marzo','abril','mayo','junio',
              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $dt    = new DateTime($fecha, $tz);
    $fechaFormateada = $dias[$dt->format('w')] . ', ' .
                       $dt->format('j') . ' de ' .
                       $meses[(int)$dt->format('n') - 1] . ' de ' .
                       $dt->format('Y');

    $baseUrl = 'https://pradopeluqueria.infinityfree.me';

    $notasHtml = $notas
        ? "<tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Notas</td>
           <td style='padding:10px 0;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($notas) . "</td></tr>"
        : '';

    // URL de cancelación (única para este token)
    $urlCancelar = $baseUrl . '/backend/api/cancel-booking.php?token=' . $token;

    // Bloque HTML reutilizable del botón cancelar
    $cancelBox = "
      <div style='background:#18181f;border:1px solid #252530;border-radius:8px;padding:16px;margin-bottom:24px;text-align:center;'>
        <p style='color:#7a7880;font-size:13px;margin:0 0 12px;'>
          ¿Necesitas cancelar tu reserva?
        </p>
        <a href='{$urlCancelar}'
           style='display:inline-block;background:#374151;color:#f0ece3;text-decoration:none;
                  padding:10px 24px;border-radius:6px;font-size:13px;font-weight:600;
                  letter-spacing:0.08em;text-transform:uppercase;border:1px solid #4b5563;'>
          Cancelar reserva
        </a>
        <p style='color:#52525b;font-size:11px;margin:10px 0 0;'>
          Solo disponible hasta las 23:59 del día anterior a tu cita.
        </p>
      </div>";

    // ================================================================
    //  EMAIL AL PELUQUERO
    // ================================================================
    if ($estadoFinal === 'aceptada') {

        $htmlPeluquero = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#22c55e;padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>Nueva reserva aceptada automáticamente</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>Prado Barber Co. &mdash; Auto-aceptación activa</p>
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
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Barbero</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($barberoRow['nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td></tr>
        <tr><td style='padding:10px 0;" . ($notas ? 'border-bottom:1px solid #252530;' : '') . "color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;" . ($notas ? 'border-bottom:1px solid #252530;' : '') . "color:#22c55e;font-size:16px;font-weight:700;'>{$hora}</td></tr>
        {$notasHtml}
      </table>
      <p style='color:#7a7880;font-size:12px;text-align:center;'>
        Reserva #{$id} &middot; <a href='{$baseUrl}/backend/admin.php' style='color:#22c55e;'>Ver en el panel</a>
      </p>
    </div>
  </div>
</body></html>";

    } else {

        $urlAceptar = $baseUrl . '/backend/api/reserva-action.php?token=' . $token . '&accion=aceptar';
        $urlDenegar = $baseUrl . '/backend/api/reserva-action.php?token=' . $token . '&accion=denegar';

        $htmlPeluquero = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#d42b2b;padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>Nueva reserva pendiente</h1>
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
               text-transform:uppercase;text-align:center;'>Aceptar reserva</a>
          </td>
          <td style='padding-left:8px;'>
            <a href='{$urlDenegar}' style='display:block;background:#d42b2b;color:#fff;text-decoration:none;
               padding:14px;border-radius:6px;font-size:14px;font-weight:700;letter-spacing:0.1em;
               text-transform:uppercase;text-align:center;'>Denegar reserva</a>
          </td>
        </tr>
      </table>
      <p style='color:#7a7880;font-size:12px;margin-top:24px;text-align:center;'>
        Reserva #{$id} &middot; <a href='{$baseUrl}/backend/admin.php' style='color:#d42b2b;'>Panel de administración</a>
      </p>
    </div>
  </div>
</body></html>";
    }

    // ================================================================
    //  EMAIL AL CLIENTE
    // ================================================================
    if ($estadoFinal === 'aceptada') {

        $htmlCliente = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#22c55e;padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>¡Reserva confirmada!</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>Prado Barber Co. &mdash; Bilbao</p>
    </div>
    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;margin-bottom:24px;'>
        Hola <strong>" . htmlspecialchars($nombre) . "</strong>,<br><br>
        Tu cita ha sido <strong>confirmada</strong>. ¡Te esperamos!
      </p>
      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Servicio</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-weight:600;'>" . htmlspecialchars($servicioRow['nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Barbero</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($barberoRow['nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td></tr>
        <tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;color:#22c55e;font-size:16px;font-weight:700;'>{$hora}</td></tr>
      </table>
      {$cancelBox}
    </div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>
        2026 Prado Barber Co. &mdash; Hecho con precisión en Bilbao
      </p>
    </div>
  </div>
</body></html>";

        $asuntoCliente = 'Reserva confirmada - Prado Barber Co.';

    } else {

        $htmlCliente = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#c9a84c;padding:24px 32px;'>
      <h1 style='margin:0;color:#000;font-size:20px;font-weight:700;'>Reserva pendiente de confirmación</h1>
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
      {$cancelBox}
    </div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>
        2026 Prado Barber Co. &mdash; Hecho con precisión en Bilbao
      </p>
    </div>
  </div>
</body></html>";

        $asuntoCliente = 'Reserva pendiente de confirmación - Prado Barber Co.';
    }

    // ── Enviar emails ─────────────────────────────────────────
    $asuntoPeluquero = $estadoFinal === 'aceptada'
        ? "Reserva #{$id} auto-aceptada - {$nombre} - {$fechaFormateada} {$hora}"
        : "Nueva reserva #{$id} - {$nombre} - {$fechaFormateada} {$hora}";

    sendBrevo('endikapradodev@gmail.com', 'Prado Barber Co.', $asuntoPeluquero, $htmlPeluquero);
    sendBrevo($email, $nombre, $asuntoCliente, $htmlCliente);

    // ── Respuesta al frontend ─────────────────────────────────
    $mensaje = $estadoFinal === 'aceptada'
        ? '¡Reserva confirmada! Te hemos enviado los detalles por email.'
        : 'Solicitud enviada. Te confirmaremos por email en breve.';

    jsonOk([
        'id'      => (int)$id,
        'mensaje' => $mensaje,
        'estado'  => $estadoFinal,
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

// ── Envío via Brevo API ───────────────────────────────────────
function sendBrevo(string $toEmail, string $toName, string $subject, string $html): bool {
    $apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : '';
    if (!$apiKey) return false;

    $payload = json_encode([
        'sender'      => ['name' => 'Prado Barber Co.', 'email' => 'endikapradodev@gmail.com'],
        'to'          => [['email' => $toEmail, 'name'  => $toName]],
        'subject'     => $subject,
        'htmlContent' => $html,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 201) {
        error_log("Brevo error [{$httpCode}] to:{$toEmail} curl:{$error} resp:{$resp}");
    }

    return $httpCode === 201;
}