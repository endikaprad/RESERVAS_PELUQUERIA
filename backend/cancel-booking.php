<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/cancel-booking.php
//
//  GET ?token=XXX&confirmar=1
//  Cancela la cita del cliente y devuelve una pantalla HTML.
// ============================================================

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';

$token     = trim($_GET['token'] ?? '');
$confirmar = trim($_GET['confirmar'] ?? '');

if (!$token || $confirmar !== '1') {
    mostrarPagina('error', 'Enlace inválido', 'El enlace no es válido o está incompleto.');
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
        mostrarPagina('error', 'No encontrada', 'Esta reserva no existe o el enlace ha caducado.');
    }

    if ($reserva['estado'] === 'cancelada') {
        mostrarPagina('info', 'Ya cancelada', 'Esta cita ya estaba cancelada.');
    }

    $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    $fmtFecha = function (string $f) use ($dias, $meses): string {
        $dt = new DateTime($f);
        return $dias[$dt->format('w')] . ', ' . $dt->format('j') . ' de ' . $meses[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y');
    };

    $baseUrl      = 'https://pradopeluqueria.infinityfree.me';
    $fecha        = $fmtFecha($reserva['fecha']);
    $hora         = substr($reserva['hora'], 0, 5);
    $servicio     = htmlspecialchars($reserva['servicio_nombre']);
    $barbero      = htmlspecialchars($reserva['barbero_nombre']);
    $cliente      = htmlspecialchars($reserva['cliente_nombre']);
    $motivoEmail  = htmlspecialchars($reserva['notas'] ?? 'Cancelación solicitada por el cliente');

    $db->prepare(
        "UPDATE reservas
         SET estado = 'cancelada',
             nueva_fecha_propuesta = NULL,
             nueva_hora_propuesta  = NULL
         WHERE token = ?"
    )->execute([$token]);

    $htmlCliente = buildEmailBase(
        '#374151',
        'Cita cancelada',
        'Prado Barber Co. — Bilbao',
        "
        <p style='color:#f0ece3;font-size:15px;margin-bottom:24px;'>
            Hola <strong>{$cliente}</strong>,<br><br>
            Tu cita ha sido <strong>cancelada</strong>.
        </p>
        " . buildTabla([
            ['Servicio', $servicio, ''],
            ['Barbero',  $barbero,  ''],
            ['Fecha',    $fecha,    ''],
            ['Hora',     $hora,     '#9ca3af'],
        ]) . "
        <div style='text-align:center;margin-top:20px;'>
            <a href='{$baseUrl}/reservas.html' style='display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;
               padding:12px 28px;border-radius:6px;font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;'>
               Hacer nueva reserva
            </a>
        </div>
        "
    );

    sendBrevo(
        $reserva['cliente_email'],
        $reserva['cliente_nombre'],
        'Cita cancelada - Prado Barber Co.',
        $htmlCliente
    );

    mostrarPagina(
        'ok',
        'Cita cancelada',
        'La cita de <strong>' . $cliente . '</strong> ha sido cancelada correctamente.<br><br>
         El cliente recibirá un email de confirmación.'
    );
} catch (PDOException $e) {
    mostrarPagina('error', 'Error de base de datos', htmlspecialchars($e->getMessage()));
}

// ── Helpers ───────────────────────────────────────────────────
function buildTabla(array $filas): string
{
    $html = "<table style='width:100%;border-collapse:collapse;margin-bottom:24px;'>";
    foreach ($filas as [$label, $valor, $color]) {
        $style = $color ? "color:{$color};font-size:16px;font-weight:700;" : 'color:#f0ece3;font-size:13px;';
        $html .= "<tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:130px;'>{$label}</td>
                  <td style='padding:10px 0;border-bottom:1px solid #252530;{$style}'>{$valor}</td></tr>";
    }
    return $html . '</table>';
}

function buildEmailBase(string $hc, string $titulo, string $sub, string $contenido): string
{
    return "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:{$hc};padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>{$titulo}</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>{$sub}</p>
    </div>
    <div style='padding:32px;'>{$contenido}</div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>
        2026 Prado Barber Co. — Hecho con precisión en Bilbao
      </p>
    </div>
  </div>
</body></html>";
}

function sendBrevo(string $toEmail, string $toName, string $subject, string $html): bool
{
    $apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : '';
    if (!$apiKey) return false;

    $payload = json_encode([
        'sender'      => ['name' => 'Prado Barber Co.', 'email' => 'endikapradodev@gmail.com'],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
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
            'content-type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response !== false;
}

function mostrarPagina(string $tipo, string $titulo, string $mensaje): never
{
    $baseUrl = 'https://pradopeluqueria.infinityfree.me';
    $colores = [
        'ok' => ['bg' => '#22c55e', 'icon' => '✓', 'text' => '#000'],
        'info' => ['bg' => '#374151', 'icon' => 'i', 'text' => '#fff'],
        'warn' => ['bg' => '#f59e0b', 'icon' => '!', 'text' => '#000'],
        'error' => ['bg' => '#d42b2b', 'icon' => '×', 'text' => '#fff'],
        'denied' => ['bg' => '#d42b2b', 'icon' => '×', 'text' => '#fff'],
    ];
    $c = $colores[$tipo] ?? $colores['error'];

    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1.0'>
<title>{$titulo} — Prado Barber Co.</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
  .card{background:#111119;border:1px solid #252530;border-radius:16px;max-width:480px;width:100%;overflow:hidden;text-align:center;}
  .card-header{background:{$c['bg']};padding:2rem;color:{$c['text']};}
  .icon{width:64px;height:64px;border-radius:50%;background:rgba(0,0,0,15);display:flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto 1rem;font-weight:700;}
  .card-header h1{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;}
  .card-body{padding:2rem;}
  .card-body p{color:#c0bcc9;font-size:.95rem;line-height:1.75;margin-bottom:1.5rem;}
  .card-body strong{color:#f0ece3;}
  .btn{display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;padding:.75rem 2rem;border-radius:4px;font-size:.75rem;font-weight:600;letter-spacing:.15em;text-transform:uppercase;margin:.35rem;}
  .btn-outline{background:transparent;border:1px solid #252530;color:#7a7880;}
  .brand{font-family:'Playfair Display',serif;font-style:italic;font-size:.9rem;color:#7a7880;margin-top:1.5rem;}
</style></head>
<body>
  <div class='card'>
    <div class='card-header'><div class='icon'>{$c['icon']}</div><h1>{$titulo}</h1></div>
    <div class='card-body'><p>{$mensaje}</p>
      <a href='{$baseUrl}/reservas.html' class='btn'>Nueva reserva</a>
      <a href='{$baseUrl}' class='btn btn-outline'>Inicio</a>
      <div class='brand'>Prado Barber Co.</div>
    </div>
  </div>
</body></html>";
    exit;
}
