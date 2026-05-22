<?php
// ============================================================
//  GET /api/reserva-action.php?token=XXX&accion=aceptar|denegar
//
//  Llamado desde los botones del email del peluquero.
//  Actualiza el estado y envía email al cliente.
// ============================================================

require_once __DIR__ . '/helpers.php';

$token  = trim($_GET['token']  ?? '');
$accion = trim($_GET['accion'] ?? '');

if (!$token || !in_array($accion, ['aceptar', 'denegar'], true)) {
    mostrarPagina('error', 'Enlace inválido', 'El enlace no es válido o está incompleto.');
}

try {
    $db = getDB();

    // Buscar reserva por token
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
        mostrarPagina('error', 'Enlace no encontrado', 'Esta reserva no existe o el enlace ha caducado.');
    }

    // Si ya fue procesada
    if ($reserva['estado'] !== 'pendiente') {
        $estadoTexto = $reserva['estado'] === 'aceptada' ? 'aceptada ✓' : 'denegada ✕';
        mostrarPagina('info', 'Ya procesada', "Esta reserva ya fue <strong>{$estadoTexto}</strong> anteriormente.");
    }

    // Actualizar estado
    $nuevoEstado = $accion === 'aceptar' ? 'aceptada' : 'denegada';
    $upd = $db->prepare('UPDATE reservas SET estado = ? WHERE token = ?');
    $upd->execute([$nuevoEstado, $token]);

    // ── Formatear fecha en español ───────────────────────────
    $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['enero','febrero','marzo','abril','mayo','junio',
              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $dt    = new DateTime($reserva['fecha']);
    $fechaFormateada = $dias[$dt->format('w')] . ', ' .
                       $dt->format('j') . ' de ' .
                       $meses[(int)$dt->format('n') - 1] . ' de ' .
                       $dt->format('Y');
    $hora  = substr($reserva['hora'], 0, 5);

    // ── Email al CLIENTE ─────────────────────────────────────
    if ($accion === 'aceptar') {
        $asuntoCliente = '✅ Reserva confirmada · Prado Barber Co.';
        $colorHeader   = '#22c55e';
        $colorTexto    = '#000';
        $iconoHeader   = '✅ ¡Reserva confirmada!';
        $subtituloHeader = 'Prado Barber Co. · Bilbao';
        $mensajeCliente = "Tu cita ha sido <strong>confirmada</strong>. ¡Te esperamos!";
        $pieCliente     = "Si necesitas cancelar, llámanos al <a href='tel:+34944000000' style='color:#d42b2b;'>+34 944 000 000</a>";
    } else {
        $asuntoCliente = '❌ Reserva no disponible · Prado Barber Co.';
        $colorHeader   = '#d42b2b';
        $colorTexto    = '#fff';
        $iconoHeader   = '❌ Reserva no disponible';
        $subtituloHeader = 'Prado Barber Co. · Bilbao';
        $mensajeCliente = "Lo sentimos, <strong>{$reserva['barbero_nombre']}</strong> no está disponible para ese horario.<br>Por favor, reserva otra fecha u hora.";
        $pieCliente     = "Puedes hacer una nueva reserva en <a href='https://pradopeluqueria.infinityfree.me/reservas.html' style='color:#d42b2b;'>nuestra web</a> o llamarnos al <a href='tel:+34944000000' style='color:#d42b2b;'>+34 944 000 000</a>";
    }

    $cuerpoCliente = "
<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>

    <div style='background:{$colorHeader};padding:24px 32px;'>
      <h1 style='margin:0;color:{$colorTexto};font-size:20px;font-weight:700;'>{$iconoHeader}</h1>
      <p style='margin:6px 0 0;color:rgba(0,0,0,0.55);font-size:14px;'>{$subtituloHeader}</p>
    </div>

    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;margin-bottom:24px;'>
        Hola <strong>{$reserva['cliente_nombre']}</strong>,<br>
        {$mensajeCliente}
      </p>

      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Servicio</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-weight:600;'>{$reserva['servicio_nombre']}</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Barbero</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$reserva['barbero_nombre']}</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td></tr>
        <tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;color:{$colorHeader};font-size:16px;font-weight:700;'>{$hora}</td></tr>
      </table>

      <p style='color:#7a7880;font-size:13px;text-align:center;'>
        {$pieCliente}
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

    mail($reserva['cliente_email'], $asuntoCliente, $cuerpoCliente, $headersCliente);

    // ── Mostrar página de feedback al peluquero ──────────────
    if ($accion === 'aceptar') {
        mostrarPagina('ok', '¡Reserva aceptada!',
            "Has confirmado la cita de <strong>{$reserva['cliente_nombre']}</strong><br>" .
            "para el <strong>{$fechaFormateada}</strong> a las <strong>{$hora}</strong>.<br><br>" .
            "Se ha notificado al cliente por email."
        );
    } else {
        mostrarPagina('denied', 'Reserva denegada',
            "Has rechazado la cita de <strong>{$reserva['cliente_nombre']}</strong>.<br>" .
            "Se ha notificado al cliente para que elija otro horario."
        );
    }

} catch (PDOException $e) {
    mostrarPagina('error', 'Error de base de datos', $e->getMessage());
}

// ── Renderizar página de respuesta ───────────────────────────
function mostrarPagina(string $tipo, string $titulo, string $mensaje): never {
    $baseUrl = 'https://pradopeluqueria.infinityfree.me';

    $colores = [
        'ok'     => ['bg' => '#22c55e', 'icon' => '✓', 'text' => '#000'],
        'denied' => ['bg' => '#d42b2b', 'icon' => '✕', 'text' => '#fff'],
        'info'   => ['bg' => '#c9a84c', 'icon' => 'ℹ', 'text' => '#000'],
        'error'  => ['bg' => '#6b7280', 'icon' => '!', 'text' => '#fff'],
    ];
    $c = $colores[$tipo] ?? $colores['error'];

    echo "<!DOCTYPE html>
<html lang='es'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1.0'>
  <title>{$titulo} · Prado Barber Co.</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;
         display:flex;align-items:center;justify-content:center;padding:2rem;}
    .card{background:#111119;border:1px solid #252530;border-radius:16px;
          max-width:480px;width:100%;overflow:hidden;text-align:center;}
    .card-header{background:{$c['bg']};padding:2rem;color:{$c['text']};}
    .icon{width:64px;height:64px;border-radius:50%;background:rgba(0,0,0,0.15);
          display:flex;align-items:center;justify-content:center;
          font-size:1.75rem;margin:0 auto 1rem;}
    .card-header h1{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;}
    .card-body{padding:2rem;}
    .card-body p{color:#c0bcc9;font-size:.95rem;line-height:1.75;margin-bottom:1.5rem;}
    .card-body strong{color:#f0ece3;}
    .btn{display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;
         padding:.75rem 2rem;border-radius:4px;font-size:.75rem;font-weight:600;
         letter-spacing:.15em;text-transform:uppercase;}
    .brand{font-family:'Playfair Display',serif;font-style:italic;font-size:.9rem;
           color:#7a7880;margin-top:1.5rem;}
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
      <div class='brand'>Prado Barber Co.</div>
    </div>
  </div>
</body>
</html>";
    exit;
}