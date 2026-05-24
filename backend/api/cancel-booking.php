<?php
// ============================================================
//  GET /backend/api/cancel-booking.php?token=XXX
//
//  Permite al cliente cancelar su reserva (pendiente o aceptada)
//  únicamente hasta las 23:59 del día anterior a la cita.
// ============================================================

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';

$token = trim($_GET['token'] ?? '');

if (!$token) {
    mostrarPagina('error', 'Enlace inválido', 'El enlace de cancelación no es válido o está incompleto.');
}

try {
    $db = getDB();

    // ── Buscar la reserva ─────────────────────────────────────
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
        mostrarPagina('error', 'No encontrada', 'Esta reserva no existe o el enlace ha caducado.');
    }

    // ── Comprobar si ya está cancelada ────────────────────────
    if ($reserva['estado'] === 'cancelada') {
        mostrarPagina('info', 'Ya cancelada', 'Esta reserva ya fue <strong>cancelada</strong> anteriormente.');
    }

    // ── Comprobar si ya está denegada ─────────────────────────
    if ($reserva['estado'] === 'denegada') {
        mostrarPagina('info', 'Reserva denegada', 'Esta reserva ya fue <strong>denegada</strong> por el barbero, por lo que no es necesario cancelarla.');
    }

    // ── Formatear fechas para mostrar ─────────────────────────
    $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $meses = [
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre'
    ];
    $dt    = new DateTime($reserva['fecha']);
    $fechaFormateada = $dias[$dt->format('w')] . ', ' .
        $dt->format('j') . ' de ' .
        $meses[(int)$dt->format('n') - 1] . ' de ' .
        $dt->format('Y');
    $hora = substr($reserva['hora'], 0, 5);

    // ── Validar ventana de cancelación ────────────────────────
    // Límite: 23:59:59 del día anterior a la cita (zona Madrid)
    $tz  = new DateTimeZone('Europe/Madrid');
    $now = new DateTime('now', $tz);

    // Día anterior a la cita a las 23:59:59
    $limiteCancelacion = new DateTime($reserva['fecha'], $tz);
    $limiteCancelacion->setTime(23, 59, 59);
    $limiteCancelacion->modify('-1 day');

    if ($now > $limiteCancelacion) {
        // Calcular cuándo era el límite para el mensaje
        $limiteStr = $limiteCancelacion->format('d/m/Y') . ' a las 23:59';
        mostrarPagina(
            'late',
            'Plazo de cancelación superado',
            "El plazo para cancelar esta reserva venció el <strong>{$limiteStr}</strong>.<br><br>" .
                "Si necesitas cancelar con menos de 24 horas de antelación, llámanos directamente al " .
                "<a href='tel:+34944000000' style='color:#d42b2b;'>+34 944 000 000</a>."
        );
    }

    // ── Si se ha confirmado el GET con ?confirmar=1, procesar ─
    if (isset($_GET['confirmar']) && $_GET['confirmar'] === '1') {

        // Doble check de la ventana (por si acaso pasó tiempo entre render y click)
        $now2 = new DateTime('now', $tz);
        if ($now2 > $limiteCancelacion) {
            mostrarPagina('late', 'Plazo superado', 'El plazo de cancelación venció mientras procesabas la solicitud.');
        }

        // Marcar como cancelada
        $upd = $db->prepare("UPDATE reservas SET estado = 'cancelada' WHERE token = ?");
        $upd->execute([$token]);

        // ── Email al cliente confirmando la cancelación ───────
        $baseUrl = 'https://pradopeluqueria.infinityfree.me';

        $htmlCliente = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#6b7280;padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>Reserva cancelada</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>Prado Barber Co. — Bilbao</p>
    </div>
    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;margin-bottom:24px;'>
        Hola <strong>" . htmlspecialchars($reserva['cliente_nombre']) . "</strong>,<br><br>
        Tu reserva ha sido <strong>cancelada</strong> correctamente.
      </p>
      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Servicio</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($reserva['servicio_nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Barbero</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($reserva['barbero_nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td></tr>
        <tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;color:#9ca3af;font-size:16px;font-weight:700;'>{$hora}</td></tr>
      </table>
      <p style='color:#7a7880;font-size:13px;text-align:center;'>
        ¿Quieres hacer una nueva reserva? Visita <a href='{$baseUrl}/reservas.html' style='color:#d42b2b;'>{$baseUrl}/reservas.html</a>
      </p>
    </div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>2026 Prado Barber Co. — Hecho con precisión en Bilbao</p>
    </div>
  </div>
</body></html>";

        // ── Email al peluquero avisando de la cancelación ─────
        $htmlPeluquero = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#374151;padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>Reserva cancelada por el cliente</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>Prado Barber Co. — Panel Admin</p>
    </div>
    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;margin-bottom:24px;'>
        <strong>" . htmlspecialchars($reserva['cliente_nombre']) . "</strong> ha cancelado su cita.
      </p>
      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Cliente</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-weight:600;'>" . htmlspecialchars($reserva['cliente_nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Teléfono</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($reserva['cliente_telefono']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Servicio</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($reserva['servicio_nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td></tr>
        <tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;color:#9ca3af;font-size:16px;font-weight:700;'>{$hora}</td></tr>
      </table>
      <p style='color:#7a7880;font-size:12px;text-align:center;'>
        <a href='https://pradopeluqueria.infinityfree.me/backend/admin.php' style='color:#d42b2b;'>Ver panel de administración</a>
      </p>
    </div>
  </div>
</body></html>";

        sendBrevo(
            $reserva['cliente_email'],
            $reserva['cliente_nombre'],
            'Cancelación confirmada - Prado Barber Co.',
            $htmlCliente
        );
        sendBrevo(
            'endikapradodev@gmail.com',
            'Prado Barber Co.',
            "Reserva cancelada por cliente - {$reserva['cliente_nombre']} - {$fechaFormateada} {$hora}",
            $htmlPeluquero
        );

        mostrarPagina(
            'cancelled',
            'Reserva cancelada',
            'Tu reserva ha sido cancelada correctamente.<br>Hemos enviado una confirmación a <strong>' .
                htmlspecialchars($reserva['cliente_email']) . '</strong>.'
        );
    }

    // ── Mostrar página de confirmación (antes de cancelar) ────
    // El cliente ve los detalles y un botón para confirmar la cancelación
    $urlConfirmar = '?token=' . urlencode($token) . '&confirmar=1';
    $baseUrl = 'https://pradopeluqueria.infinityfree.me';

    $limiteStr = $limiteCancelacion->format('d/m/Y') . ' a las 23:59';
    $estadoBadge = $reserva['estado'] === 'aceptada'
        ? "<span style='background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.35);color:#22c55e;padding:.25rem .75rem;border-radius:100px;font-size:.72rem;font-weight:600;'>✓ Confirmada</span>"
        : "<span style='background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.35);color:#f59e0b;padding:.25rem .75rem;border-radius:100px;font-size:.72rem;font-weight:600;'>⏳ Pendiente</span>";

    echo "<!DOCTYPE html>
<html lang='es'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1.0'>
  <title>Cancelar reserva — Prado Barber Co.</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;
         display:flex;align-items:center;justify-content:center;padding:1.5rem;}
    .card{background:#111119;border:1px solid #252530;border-radius:16px;max-width:520px;width:100%;overflow:hidden;}
    .card-header{background:linear-gradient(135deg,#1a1a24,#111119);border-bottom:1px solid #252530;padding:2rem 2rem 1.5rem;}
    .brand{font-family:'Playfair Display',serif;font-style:italic;font-size:.85rem;color:#7a7880;margin-bottom:.75rem;}
    .card-header h1{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;margin-bottom:.35rem;}
    .card-header p{color:#7a7880;font-size:.82rem;line-height:1.6;}
    .card-body{padding:1.75rem 2rem;}
    table{width:100%;border-collapse:collapse;margin-bottom:1.5rem;}
    td{padding:.75rem 0;border-bottom:1px solid #1c1c26;font-size:.88rem;}
    tr:last-child td{border-bottom:none;}
    td:first-child{color:#7a7880;width:110px;}
    td:last-child{color:#f0ece3;font-weight:500;}
    .warning-box{background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:10px;
                 padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;gap:.75rem;align-items:flex-start;}
    .warning-icon{font-size:1.1rem;flex-shrink:0;margin-top:.05rem;}
    .warning-text{font-size:.8rem;color:#d4a84b;line-height:1.6;}
    .warning-text strong{color:#f59e0b;}
    .btn-cancel{width:100%;background:linear-gradient(135deg,#374151,#1f2937);color:#f0ece3;
                border:1px solid #4b5563;border-radius:8px;padding:1rem;font-family:'DM Sans',sans-serif;
                font-size:.82rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
                cursor:pointer;transition:all .25s;text-decoration:none;display:block;text-align:center;margin-bottom:.75rem;}
    .btn-cancel:hover{background:linear-gradient(135deg,#4b5563,#374151);border-color:#6b7280;}
    .btn-keep{width:100%;background:linear-gradient(135deg,#d42b2b,#a81e1e);color:#fff;
              border:none;border-radius:8px;padding:1rem;font-family:'DM Sans',sans-serif;
              font-size:.82rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
              cursor:pointer;transition:all .25s;text-decoration:none;display:block;text-align:center;}
    .btn-keep:hover{box-shadow:0 8px 24px rgba(212,43,43,.4);transform:translateY(-1px);}
    .deadline{font-size:.72rem;color:#7a7880;text-align:center;margin-top:1rem;}
    .deadline strong{color:#f59e0b;}
  </style>
</head>
<body>
  <div class='card'>
    <div class='card-header'>
      <div class='brand'>Prado Barber Co. · Bilbao</div>
      <h1>Cancelar reserva</h1>
      <p>Revisa los detalles de tu cita antes de cancelar.</p>
    </div>
    <div class='card-body'>
      <table>
        <tr><td>Estado</td><td>{$estadoBadge}</td></tr>
        <tr><td>Servicio</td><td>" . htmlspecialchars($reserva['servicio_nombre']) . "</td></tr>
        <tr><td>Barbero</td><td>" . htmlspecialchars($reserva['barbero_nombre']) . "</td></tr>
        <tr><td>Fecha</td><td>{$fechaFormateada}</td></tr>
        <tr><td>Hora</td><td><span style='color:#d42b2b;font-size:1.05rem;font-weight:700;'>{$hora}</span></td></tr>
      </table>
      <div class='warning-box'>
        <span class='warning-icon'>⚠</span>
        <div class='warning-text'>
          Esta acción <strong>no se puede deshacer</strong>. Si cancelas, el hueco quedará libre para otros clientes.
          Para una nueva cita tendrás que reservar de nuevo.
        </div>
      </div>
      <a href='{$urlConfirmar}' class='btn-cancel'>Sí, cancelar mi reserva</a>
      <a href='{$baseUrl}' class='btn-keep'>No, mantener la reserva</a>
      <p class='deadline'>Plazo límite de cancelación: <strong>{$limiteStr}</strong></p>
    </div>
  </div>
</body>
</html>";
    exit;
} catch (PDOException $e) {
    mostrarPagina('error', 'Error de base de datos', htmlspecialchars($e->getMessage()));
}

// ── Envío via Brevo API ──────────────────────────────────────
function sendBrevo(string $toEmail, string $toName, string $subject, string $html): bool
{
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

    return $httpCode === 201;
}

// ── Página de resultado HTML ─────────────────────────────────
function mostrarPagina(string $tipo, string $titulo, string $mensaje): never
{
    $baseUrl = 'https://pradopeluqueria.infinityfree.me';
    $colores = [
        'cancelled' => ['bg' => '#374151', 'icon' => '✕', 'text' => '#fff'],
        'late'      => ['bg' => '#92400e', 'icon' => '⏰', 'text' => '#fff'],
        'info'      => ['bg' => '#c9a84c', 'icon' => 'i', 'text' => '#000'],
        'error'     => ['bg' => '#6b7280', 'icon' => '!', 'text' => '#fff'],
    ];
    $c = $colores[$tipo] ?? $colores['error'];

    echo "<!DOCTYPE html>
<html lang='es'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1.0'>
  <title>{$titulo} — Prado Barber Co.</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;
         display:flex;align-items:center;justify-content:center;padding:2rem;}
    .card{background:#111119;border:1px solid #252530;border-radius:16px;max-width:480px;width:100%;overflow:hidden;text-align:center;}
    .card-header{background:{$c['bg']};padding:2rem;color:{$c['text']};}
    .icon{width:64px;height:64px;border-radius:50%;background:rgba(0,0,0,.15);display:flex;align-items:center;
          justify-content:center;font-size:1.75rem;margin:0 auto 1rem;}
    .card-header h1{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;}
    .card-body{padding:2rem;}
    .card-body p{color:#c0bcc9;font-size:.95rem;line-height:1.75;margin-bottom:1.5rem;}
    .card-body strong{color:#f0ece3;}
    .btn{display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;padding:.75rem 2rem;
         border-radius:4px;font-size:.75rem;font-weight:600;letter-spacing:.15em;text-transform:uppercase;margin:.35rem;}
    .btn-outline{background:transparent;border:1px solid #252530;color:#7a7880;}
    .btn-outline:hover{border-color:#f0ece3;color:#f0ece3;}
    .brand{font-family:'Playfair Display',serif;font-style:italic;font-size:.9rem;color:#7a7880;margin-top:1.5rem;}
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
      <a href='{$baseUrl}/reservas.html' class='btn'>Nueva reserva</a>
      <a href='{$baseUrl}' class='btn btn-outline'>Inicio</a>
      <div class='brand'>Prado Barber Co.</div>
    </div>
  </div>
</body>
</html>";
    exit;
}
