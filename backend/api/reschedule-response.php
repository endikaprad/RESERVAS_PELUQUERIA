<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/reschedule-response.php
//
//  GET ?pt=TOKEN&accion=aceptar|rechazar
//
//  El cliente hace clic en el email de propuesta de cambio
//  y este script procesa su respuesta.
// ============================================================

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';

$token  = trim($_GET['pt']     ?? '');
$accion = trim($_GET['accion'] ?? '');

if (!$token || !in_array($accion, ['aceptar', 'rechazar'], true)) {
    mostrarPagina('error', 'Enlace inválido', 'El enlace no es válido o está incompleto.');
}

try {
    $db = getDB();

    // ── Buscar la reserva ─────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT r.*, s.nombre AS servicio_nombre,
                b.nombre AS barbero_nombre, b.id AS barbero_id_val
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

    // Solo válido si está en estado de propuesta del barbero
    if ($reserva['estado'] !== 'reprogramar_barbero') {
        $msg = in_array($reserva['estado'], ['cancelada', 'denegada'])
            ? 'Esta reserva ya fue <strong>' . $reserva['estado'] . '</strong>.'
            : 'Esta propuesta ya fue procesada anteriormente.';
        mostrarPagina('info', 'Ya procesada', $msg);
    }

    // Obtener nueva fecha/hora propuesta
    $nuevaFecha = $reserva['nueva_fecha_propuesta'] ?? '';
    $nuevaHora  = $reserva['nueva_hora_propuesta']  ?? '';
    $motivoCambio = $reserva['motivo_cambio'] ?? '';

    if (!$nuevaFecha || !$nuevaHora) {
        mostrarPagina('error', 'Datos incompletos', 'No se encontraron los datos de la nueva propuesta.');
    }

    // ── Helpers ───────────────────────────────────────────────
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

    function fmtFecha(string $f, array $d, array $m): string
    {
        $dt = new DateTime($f);
        return $d[$dt->format('w')] . ', ' . $dt->format('j') . ' de ' .
            $m[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y');
    }

    $baseUrl       = 'https://pradopeluqueria.infinityfree.me';
    $fechaOriginal = fmtFecha($reserva['fecha'], $dias, $meses);
    $horaOriginal  = substr($reserva['hora'], 0, 5);
    $fechaNueva    = fmtFecha($nuevaFecha, $dias, $meses);
    $horaNueva     = substr($nuevaHora, 0, 5);

    // ════════════════════════════════════════════════════════
    //  ACEPTAR — actualizar fecha/hora y confirmar
    // ════════════════════════════════════════════════════════
    if ($accion === 'aceptar') {

        // Verificar que el slot sigue libre
        $check = $db->prepare(
            "SELECT COUNT(*) FROM reservas
             WHERE barbero_id = ? AND fecha = ? AND hora = ?
               AND estado IN ('pendiente','aceptada')
               AND token != ?"
        );
        $check->execute([$reserva['barbero_id'], $nuevaFecha, $nuevaHora, $token]);

        if ((int)$check->fetchColumn() > 0) {
            // Slot ya ocupado — notificar al cliente y volver a pendiente
            $db->prepare("UPDATE reservas SET estado = 'pendiente' WHERE token = ?")
                ->execute([$token]);

            mostrarPagina(
                'warn',
                'Horario ya ocupado',
                'Lo sentimos, el horario propuesto (<strong>' . $horaNueva . ' del ' . $fechaNueva . '</strong>) ya fue reservado por otro cliente.<br><br>' .
                    'El barbero será notificado para ofrecerte una nueva alternativa.<br><br>' .
                    '<a href="' . $baseUrl . '/reservas.html" style="color:#d42b2b;">También puedes hacer una nueva reserva aquí.</a>'
            );
        }

        // Actualizar reserva con nueva fecha/hora
        $db->prepare(
            "UPDATE reservas
             SET fecha = ?, hora = ?, estado = 'aceptada',
                 nueva_fecha_propuesta = NULL, nueva_hora_propuesta = NULL
             WHERE token = ?"
        )->execute([$nuevaFecha, $nuevaHora, $token]);

        // Email de confirmación al cliente
        $htmlCliente = buildEmailBase(
            '#22c55e',
            '¡Cambio de horario confirmado!',
            'Prado Barber Co. &mdash; Bilbao',
            '<p style="color:#f0ece3;font-size:15px;margin-bottom:24px;">
              Hola <strong>' . htmlspecialchars($reserva['cliente_nombre']) . '</strong>,<br><br>
              Has <strong>aceptado</strong> el cambio de horario. Tu nueva cita queda confirmada.
            </p>' .
                buildTabla([
                    ['Servicio', htmlspecialchars($reserva['servicio_nombre']), ''],
                    ['Barbero',  htmlspecialchars($reserva['barbero_nombre']),  ''],
                    ['Fecha',    $fechaNueva,                                   ''],
                    ['Hora',     $horaNueva,                                    '#22c55e'],
                ]) .
                '<p style="color:#7a7880;font-size:13px;text-align:center;">
              ¿Necesitas cancelar? Llámanos al <a href="tel:+34944000000" style="color:#d42b2b;">+34 944 000 000</a>
            </p>'
        );

        // Email de aviso al barbero
        $htmlBarbero = buildEmailBase(
            '#22c55e',
            'Cliente aceptó el cambio de horario',
            'Prado Barber Co. &mdash; Admin',
            '<p style="color:#f0ece3;font-size:15px;margin-bottom:24px;">
              <strong>' . htmlspecialchars($reserva['cliente_nombre']) . '</strong> ha aceptado el nuevo horario.
            </p>' .
                buildTabla([
                    ['Cliente',    htmlspecialchars($reserva['cliente_nombre']),   ''],
                    ['Teléfono',   htmlspecialchars($reserva['cliente_telefono']), ''],
                    ['Nueva fecha', $fechaNueva,                                    ''],
                    ['Nueva hora', $horaNueva,                                     '#22c55e'],
                ]) .
                '<p style="color:#7a7880;font-size:12px;text-align:center;">
              <a href="' . $baseUrl . '/backend/admin.php" style="color:#22c55e;">Ver en el panel</a>
            </p>'
        );

        sendBrevo(
            $reserva['cliente_email'],
            $reserva['cliente_nombre'],
            'Cambio de horario confirmado - Prado Barber Co.',
            $htmlCliente
        );
        sendBrevo(
            'endikapradodev@gmail.com',
            'Prado Barber Co.',
            'Cliente aceptó cambio - ' . $reserva['cliente_nombre'] . ' - ' . $fechaNueva . ' ' . $horaNueva,
            $htmlBarbero
        );

        mostrarPagina(
            'ok',
            '¡Cambio confirmado!',
            'Tu nueva cita queda fijada para el <strong>' . $fechaNueva . '</strong> a las <strong>' . $horaNueva . '</strong>.<br><br>' .
                'Hemos enviado la confirmación a tu email.'
        );
    }

    // ════════════════════════════════════════════════════════
    //  RECHAZAR — cancelar la reserva y notificar al barbero
    // ════════════════════════════════════════════════════════
    if ($accion === 'rechazar') {

        $db->prepare("UPDATE reservas SET estado = 'cancelada' WHERE token = ?")
            ->execute([$token]);

        // Email al barbero avisando del rechazo
        $htmlBarbero = buildEmailBase(
            '#d42b2b',
            'Cliente rechazó el cambio de horario',
            'Prado Barber Co. &mdash; Admin',
            '<p style="color:#f0ece3;font-size:15px;margin-bottom:24px;">
              <strong>' . htmlspecialchars($reserva['cliente_nombre']) . '</strong> ha rechazado la propuesta de cambio.
              La reserva ha sido <strong>cancelada</strong>.
            </p>' .
                buildTabla([
                    ['Cliente',   htmlspecialchars($reserva['cliente_nombre']),   ''],
                    ['Teléfono',  htmlspecialchars($reserva['cliente_telefono']), ''],
                    ['Email',     htmlspecialchars($reserva['cliente_email']),    ''],
                    ['Propuesta', $fechaNueva . ' ' . $horaNueva,                '#d42b2b'],
                ]) .
                '<p style="color:#7a7880;font-size:12px;text-align:center;">
              <a href="' . $baseUrl . '/backend/admin.php" style="color:#d42b2b;">Ver en el panel</a>
            </p>'
        );

        // Email al cliente confirmando la cancelación
        $htmlCliente = buildEmailBase(
            '#374151',
            'Propuesta rechazada — Reserva cancelada',
            'Prado Barber Co. &mdash; Bilbao',
            '<p style="color:#f0ece3;font-size:15px;margin-bottom:24px;">
              Hola <strong>' . htmlspecialchars($reserva['cliente_nombre']) . '</strong>,<br><br>
              Has rechazado el cambio de horario. Tu reserva ha sido cancelada.<br>
              Puedes hacer una nueva reserva cuando quieras.
            </p>' .
                '<div style="text-align:center;margin-top:20px;">
               <a href="' . $baseUrl . '/reservas.html"
                  style="display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;
                         padding:12px 28px;border-radius:6px;font-size:13px;font-weight:600;
                         letter-spacing:0.08em;text-transform:uppercase;">
                 Nueva reserva
               </a>
             </div>'
        );

        sendBrevo(
            'endikapradodev@gmail.com',
            'Prado Barber Co.',
            'Cliente rechazó cambio - ' . $reserva['cliente_nombre'],
            $htmlBarbero
        );
        sendBrevo(
            $reserva['cliente_email'],
            $reserva['cliente_nombre'],
            'Propuesta rechazada - Prado Barber Co.',
            $htmlCliente
        );

        mostrarPagina(
            'denied',
            'Propuesta rechazada',
            'Has rechazado el cambio de horario. Tu reserva ha sido cancelada.<br><br>' .
                'El barbero ha sido notificado.<br><br>' .
                '<a href="' . $baseUrl . '/reservas.html" style="color:#d42b2b;">Haz una nueva reserva</a>'
        );
    }
} catch (PDOException $e) {
    mostrarPagina('error', 'Error de base de datos', htmlspecialchars($e->getMessage()));
}

// ── Helpers HTML ─────────────────────────────────────────────
function buildTabla(array $filas): string
{
    $html = "<table style='width:100%;border-collapse:collapse;margin-bottom:24px;'>";
    foreach ($filas as [$label, $valor, $color]) {
        $style = $color ? "color:{$color};font-size:16px;font-weight:700;" : 'color:#f0ece3;font-size:13px;';
        $html .= "<tr>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:130px;'>{$label}</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;{$style}'>{$valor}</td>
          </tr>";
    }
    $html .= "</table>";
    return $html;
}

function buildEmailBase(string $headerColor, string $titulo, string $subtitulo, string $contenido): string
{
    return "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:{$headerColor};padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>{$titulo}</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>{$subtitulo}</p>
    </div>
    <div style='padding:32px;'>{$contenido}</div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>
        2026 Prado Barber Co. &mdash; Hecho con precisión en Bilbao
      </p>
    </div>
  </div>
</body></html>";
}

// ── Página de resultado ───────────────────────────────────────
function mostrarPagina(string $tipo, string $titulo, string $mensaje): never
{
    $baseUrl = 'https://pradopeluqueria.infinityfree.me';
    $colores = [
        'ok'     => ['bg' => '#22c55e', 'icon' => '✓', 'text' => '#fff'],
        'denied' => ['bg' => '#374151', 'icon' => '✕', 'text' => '#fff'],
        'warn'   => ['bg' => '#f59e0b', 'icon' => '⚠', 'text' => '#000'],
        'info'   => ['bg' => '#c9a84c', 'icon' => 'i', 'text' => '#000'],
        'error'  => ['bg' => '#6b7280', 'icon' => '!', 'text' => '#fff'],
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
    .icon{width:64px;height:64px;border-radius:50%;background:rgba(0,0,0,0.15);display:flex;align-items:center;
          justify-content:center;font-size:1.75rem;margin:0 auto 1rem;font-weight:700;}
    .card-header h1{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;}
    .card-body{padding:2rem;}
    .card-body p{color:#c0bcc9;font-size:.95rem;line-height:1.75;margin-bottom:1.5rem;}
    .card-body strong{color:#f0ece3;}
    a{color:#d42b2b;}
    .btn{display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;padding:.75rem 2rem;
         border-radius:4px;font-size:.75rem;font-weight:600;letter-spacing:.15em;text-transform:uppercase;margin:.35rem;}
    .btn-outline{background:transparent;border:1px solid #252530;color:#7a7880;}
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
      <a href='{$baseUrl}' class='btn btn-outline'>Inicio</a>
      <div class='brand'>Prado Barber Co.</div>
    </div>
  </div>
</body>
</html>";
    exit;
}

// ── Envío via Brevo ───────────────────────────────────────────
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
