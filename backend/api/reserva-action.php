<?php
// ============================================================
//  GET /api/reserva-action.php?token=XXX&accion=aceptar|denegar
//  NO incluye helpers.php para evitar conflicto de headers JSON
// ============================================================

// 1. Headers HTML PRIMERO, antes de cualquier require
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

// 2. Ahora cargamos solo config (sin helpers que fuerza JSON)
require_once __DIR__ . '/../config.php';

$token     = trim($_GET['token']  ?? '');
$accion    = trim($_GET['accion'] ?? '');
$fromAdmin = isset($_GET['from']) && $_GET['from'] === 'admin';

if (!$token || !in_array($accion, ['aceptar', 'denegar'], true)) {
    mostrarPagina('error', 'Enlace inválido', 'El enlace no es válido o está incompleto.', $fromAdmin);
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

    $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
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
        $asuntoCliente  = '✅ Reserva confirmada - Prado Barber Co.';
        $colorHeader    = '#22c55e';
        $tituloHeader   = '¡Reserva confirmada!';
        $mensajeCliente = 'Tu cita ha sido <strong>confirmada</strong>. ¡Te esperamos!';
        $pieCliente     = 'Si necesitas cancelar, llámanos al <a href="tel:+34944000000" style="color:#d42b2b;">+34 944 000 000</a>';
    } else {
        $asuntoCliente  = '❌ Reserva no disponible - Prado Barber Co.';
        $colorHeader    = '#d42b2b';
        $tituloHeader   = 'Reserva no disponible';
        $mensajeCliente = 'Lo sentimos, <strong>' . htmlspecialchars($reserva['barbero_nombre']) . '</strong> no está disponible para ese horario. Por favor, reserva otra fecha u hora.';
        $pieCliente     = 'Puedes hacer una nueva reserva en <a href="' . $baseUrl . '/reservas.html" style="color:#d42b2b;">nuestra web</a> o llamarnos al <a href="tel:+34944000000" style="color:#d42b2b;">+34 944 000 000</a>';
    }

    $htmlCliente = generarEmailCliente(
        $reserva['cliente_nombre'],
        $colorHeader,
        $tituloHeader,
        $mensajeCliente,
        $reserva['servicio_nombre'],
        $reserva['barbero_nombre'],
        $fechaFormateada,
        $hora,
        $pieCliente
    );

    $resultado = sendResend($reserva['cliente_email'], $asuntoCliente, $htmlCliente);

    if ($accion === 'aceptar') {
        mostrarPagina('ok', '¡Reserva aceptada!',
            'Has confirmado la cita de <strong>' . htmlspecialchars($reserva['cliente_nombre']) . '</strong><br>' .
            'para el <strong>' . $fechaFormateada . '</strong> a las <strong>' . $hora . '</strong>.<br><br>' .
            'Se ha notificado al cliente por email.' .
            ($resultado ? '' : '<br><small style="color:#f59e0b;">⚠ El email tardó en enviarse, verifica Resend.</small>'),
            $fromAdmin
        );
    } else {
        mostrarPagina('denied', 'Reserva denegada',
            'Has rechazado la cita de <strong>' . htmlspecialchars($reserva['cliente_nombre']) . '</strong>.<br>' .
            'Se ha notificado al cliente para que elija otro horario.' .
            ($resultado ? '' : '<br><small style="color:#f59e0b;">⚠ El email tardó en enviarse, verifica Resend.</small>'),
            $fromAdmin
        );
    }

} catch (PDOException $e) {
    mostrarPagina('error', 'Error de base de datos', htmlspecialchars($e->getMessage()), $fromAdmin);
}

// ── Genera HTML del email para el cliente ──────────────────
function generarEmailCliente(
    string $nombre, string $colorHeader, string $titulo,
    string $mensaje, string $servicio, string $barbero,
    string $fecha, string $hora, string $pie
): string {
    return "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:{$colorHeader};padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>{$titulo}</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>Prado Barber Co. &mdash; Bilbao</p>
    </div>
    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;margin-bottom:24px;'>
        Hola <strong>" . htmlspecialchars($nombre) . "</strong>,<br>
        {$mensaje}
      </p>
      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr>
          <td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Servicio</td>
          <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-weight:600;'>" . htmlspecialchars($servicio) . "</td>
        </tr>
        <tr>
          <td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Barbero</td>
          <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($barbero) . "</td>
        </tr>
        <tr>
          <td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
          <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fecha}</td>
        </tr>
        <tr>
          <td style='padding:10px 0;color:#7a7880;font-size:13px;'>Hora</td>
          <td style='padding:10px 0;color:{$colorHeader};font-size:16px;font-weight:700;'>{$hora}</td>
        </tr>
      </table>
      <p style='color:#7a7880;font-size:13px;text-align:center;'>{$pie}</p>
    </div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>
        &copy; 2026 Prado Barber Co. &mdash; Hecho con precisión en Bilbao
      </p>
    </div>
  </div>
</body></html>";
}

// ── Envío via Resend ────────────────────────────────────────
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
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 200 || $httpCode === 201;
}

// ── Página de resultado ─────────────────────────────────────
function mostrarPagina(string $tipo, string $titulo, string $mensaje, bool $fromAdmin = false): never {
    $baseUrl = 'https://pradopeluqueria.infinityfree.me';
    $colores = [
        'ok'     => ['bg' => '#22c55e', 'icon' => '✓', 'text' => '#fff'],
        'denied' => ['bg' => '#d42b2b', 'icon' => '✕', 'text' => '#fff'],
        'info'   => ['bg' => '#c9a84c', 'icon' => 'i', 'text' => '#000'],
        'error'  => ['bg' => '#6b7280', 'icon' => '!', 'text' => '#fff'],
    ];
    $c = $colores[$tipo] ?? $colores['error'];

    $metaRefresh = $fromAdmin
        ? "<meta http-equiv='refresh' content='3;url={$baseUrl}/backend/admin.php'>"
        : '';

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
    .card-body small{display:block;margin-top:.5rem;}
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