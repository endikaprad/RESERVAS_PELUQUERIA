<?php
// ============================================================
//  GET /api/reserva-action.php?token=XXX&accion=aceptar|denegar
//  Con botón de Google Calendar en el email de aceptación.
// ============================================================

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/gcal-helper.php';   // ← NUEVO

$token     = trim($_GET['token']  ?? '');
$accion    = trim($_GET['accion'] ?? '');
$fromAdmin = isset($_GET['from']) && $_GET['from'] === 'admin';

if (!$token || !in_array($accion, ['aceptar', 'denegar'], true)) {
    mostrarPagina('error', 'Enlace invalido', 'El enlace no es valido o esta incompleto.', $fromAdmin);
}

try {
    $db = getDB();

    $stmt = $db->prepare(
        'SELECT r.*, s.nombre AS servicio_nombre, s.duracion,
                b.nombre AS barbero_nombre
         FROM reservas r
         JOIN servicios s ON s.id = r.servicio_id
         JOIN barberos  b ON b.id = r.barbero_id
         WHERE r.token = ?'
    );
    $stmt->execute([$token]);
    $reserva = $stmt->fetch();

    if (!$reserva) {
        mostrarPagina('error', 'No encontrada', 'Esta reserva no existe o el enlace ha caducado.', $fromAdmin);
    }

    if ($reserva['estado'] !== 'pendiente') {
        $estadoTexto = $reserva['estado'] === 'aceptada' ? 'aceptada' : 'denegada';
        mostrarPagina('info', 'Ya procesada', "Esta reserva ya fue <strong>{$estadoTexto}</strong> anteriormente.", $fromAdmin);
    }

    $nuevoEstado = $accion === 'aceptar' ? 'aceptada' : 'denegada';
    $upd = $db->prepare('UPDATE reservas SET estado = ? WHERE token = ?');
    $upd->execute([$nuevoEstado, $token]);

    $dias  = ['Domingo','Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'];
    $meses = ['enero','febrero','marzo','abril','mayo','junio',
              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $dt    = new DateTime($reserva['fecha']);
    $fechaFormateada = $dias[$dt->format('w')] . ', ' .
                       $dt->format('j') . ' de ' .
                       $meses[(int)$dt->format('n') - 1] . ' de ' .
                       $dt->format('Y');
    $hora = substr($reserva['hora'], 0, 5);

    $baseUrl = 'https://pradopeluqueria.infinityfree.me';

    if ($accion === 'aceptar') {
        $asuntoCliente = 'Reserva confirmada - Prado Barber Co.';
        $colorHeader   = '#22c55e';
        $tituloHeader  = 'Reserva confirmada!';
        $mensajeCliente= 'Tu cita ha sido <strong>confirmada</strong>. Te esperamos!';
        $pieCliente    = '';
    } else {
        $asuntoCliente = 'Reserva no disponible - Prado Barber Co.';
        $colorHeader   = '#d42b2b';
        $tituloHeader  = 'Reserva no disponible';
        $mensajeCliente= 'Lo sentimos, <strong>' . htmlspecialchars($reserva['barbero_nombre']) . '</strong> no esta disponible para ese horario.';
        $pieCliente    = 'Puedes hacer una nueva reserva en <a href="' . $baseUrl . '/reservas.html" style="color:#d42b2b;">nuestra web</a>.';
    }

    // ── Google Calendar (solo si se acepta) ──────────────────────────
    $gcalBlock = '';
    if ($accion === 'aceptar') {
        $duracionMinutos = parseDuracionMinutos($reserva['duracion'] ?? '30 min');
        $gcalUrl  = buildGCalUrl(
            $reserva['fecha'],
            $hora,
            $duracionMinutos,
            $reserva['servicio_nombre'],
            $reserva['barbero_nombre'],
            $reserva['notas'] ?? ''
        );
        $gcalBlock = buildGCalBlock($gcalUrl);
    }

    // ── Bloque cancelar (solo para reservas aceptadas) ───────────────
    $cancelBox = '';
    if ($accion === 'aceptar') {
        $urlCancelar = $baseUrl . '/backend/api/cancel-booking.php?token=' . $reserva['token'];
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
    }

    $htmlCliente = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:{$colorHeader};padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>{$tituloHeader}</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>Prado Barber Co. - Bilbao</p>
    </div>
    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;margin-bottom:24px;'>
        Hola <strong>" . htmlspecialchars($reserva['cliente_nombre']) . "</strong>,<br>
        {$mensajeCliente}
      </p>
      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Servicio</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-weight:600;'>" . htmlspecialchars($reserva['servicio_nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Barbero</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($reserva['barbero_nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td></tr>
        <tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;color:{$colorHeader};font-size:16px;font-weight:700;'>{$hora}</td></tr>
      </table>
      {$gcalBlock}
      {$cancelBox}
      " . ($pieCliente ? "<p style='color:#7a7880;font-size:13px;text-align:center;'>{$pieCliente}</p>" : "") . "
    </div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>2026 Prado Barber Co. - Hecho con precision en Bilbao</p>
    </div>
  </div>
</body></html>";

    sendBrevo($reserva['cliente_email'], $reserva['cliente_nombre'], $asuntoCliente, $htmlCliente);

    if ($accion === 'aceptar') {
        mostrarPagina('ok', 'Reserva aceptada!',
            'Has confirmado la cita de <strong>' . htmlspecialchars($reserva['cliente_nombre']) . '</strong><br>' .
            'para el <strong>' . $fechaFormateada . '</strong> a las <strong>' . $hora . '</strong>.<br><br>' .
            'Se ha notificado al cliente por email con el enlace para añadir la cita a Google Calendar.',
            $fromAdmin);
    } else {
        mostrarPagina('denied', 'Reserva denegada',
            'Has rechazado la cita de <strong>' . htmlspecialchars($reserva['cliente_nombre']) . '</strong>.<br>' .
            'Se ha notificado al cliente para que elija otro horario.',
            $fromAdmin);
    }

} catch (PDOException $e) {
    mostrarPagina('error', 'Error de base de datos', htmlspecialchars($e->getMessage()), $fromAdmin);
}

// ── Envío via Brevo API ──────────────────────────────────────
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

// ── Página de resultado HTML ─────────────────────────────────
function mostrarPagina(string $tipo, string $titulo, string $mensaje, bool $fromAdmin = false): never {
    $baseUrl = 'https://pradopeluqueria.infinityfree.me';
    $colores = [
        'ok'     => ['bg' => '#22c55e', 'icon' => '✓', 'text' => '#fff'],
        'denied' => ['bg' => '#d42b2b', 'icon' => '✕', 'text' => '#fff'],
        'info'   => ['bg' => '#c9a84c', 'icon' => 'i', 'text' => '#000'],
        'error'  => ['bg' => '#6b7280', 'icon' => '!', 'text' => '#fff'],
    ];
    $c = $colores[$tipo] ?? $colores['error'];
    $metaRefresh = $fromAdmin ? "<meta http-equiv='refresh' content='3;url={$baseUrl}/backend/admin.php'>" : '';

    echo "<!DOCTYPE html>
<html lang='es'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1.0'>
  {$metaRefresh}
  <title>{$titulo} - Prado Barber Co.</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;
         display:flex;align-items:center;justify-content:center;padding:2rem;}
    .card{background:#111119;border:1px solid #252530;border-radius:16px;max-width:480px;width:100%;overflow:hidden;text-align:center;}
    .card-header{background:{$c['bg']};padding:2rem;color:{$c['text']};}
    .icon{width:64px;height:64px;border-radius:50%;background:rgba(0,0,0,0.15);display:flex;align-items:center;
          justify-content:center;font-size:1.75rem;margin:0 auto 1rem;font-weight:700;}
    .card-header h1{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;}
    .card-body{padding:2rem;}
    .card-body p{color:#c0bcc9;font-size:.95rem;line-height:1.75;margin-bottom:1.5rem;}
    .card-body strong{color:#f0ece3;}
    .btn{display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;padding:.75rem 2rem;
         border-radius:4px;font-size:.75rem;font-weight:600;letter-spacing:.15em;text-transform:uppercase;}
    .brand{font-family:'Playfair Display',serif;font-style:italic;font-size:.9rem;color:#7a7880;margin-top:1.5rem;}
    .redirect-note{font-size:.75rem;color:#7a7880;margin-top:1rem;}
  </style>
</head>
<body>
  <div class='card'>
    <div class='card-header'>
      <div class='icon'>{$c['icon']}</div>
      <h1>{$titulo}</h1>
    </div>
    <div class='card-body'>
      <p>{$mensaje}</p>
      <a href='{$baseUrl}/backend/admin.php' class='btn'>Ir al panel admin</a>
      " . ($fromAdmin ? "<p class='redirect-note'>Volviendo al panel en 3 segundos...</p>" : "") . "
      <div class='brand'>Prado Barber Co.</div>
    </div>
  </div>
</body>
</html>";
    exit;
}