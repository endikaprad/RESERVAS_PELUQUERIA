<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/barber-accept-counter.php
//
//  GET ?token=XXX&accion=aceptar|cancelar
//
//  El barbero hace clic en el email de contrapropuesta del cliente.
//  aceptar  → pasa la reserva al nuevo horario del cliente, estado 'aceptada'
//  cancelar → estado 'cancelada', notifica al cliente
// ============================================================

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';

$token  = trim($_GET['token']  ?? '');
$accion = trim($_GET['accion'] ?? '');

if (!$token || !in_array($accion, ['aceptar', 'cancelar'], true)) {
    mostrarPagina('error', 'Enlace inválido', 'El enlace no es válido o está incompleto.');
}

try {
    $db = getDB();

    $stmt = $db->prepare(
        'SELECT r.*, s.nombre AS servicio_nombre,
                b.nombre AS barbero_nombre
         FROM reservas r
         JOIN servicios s ON s.id = r.servicio_id
         JOIN barberos  b ON b.id = r.barbero_id
         WHERE r.token = ?'
    );
    $stmt->execute([$token]);
    $r = $stmt->fetch();

    if (!$r) mostrarPagina('error', 'No encontrada', 'Esta reserva no existe o el enlace ha caducado.');

    if ($r['estado'] !== 'reprogramar_cliente') {
        $msg = in_array($r['estado'], ['aceptada', 'cancelada', 'denegada'])
            ? 'Esta reserva ya fue <strong>' . $r['estado'] . '</strong> anteriormente.'
            : 'Esta propuesta ya fue procesada.';
        mostrarPagina('info', 'Ya procesada', $msg);
    }

    $nuevaFecha = $r['nueva_fecha_propuesta'] ?? '';
    $nuevaHora  = $r['nueva_hora_propuesta']  ?? '';

    if (!$nuevaFecha || !$nuevaHora) {
        mostrarPagina('error', 'Datos incompletos', 'No se encontraron los datos de la contrapropuesta.');
    }

    $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $fmtFecha = function (string $f) use ($dias, $meses): string {
        $dt = new DateTime($f);
        return $dias[$dt->format('w')] . ', ' . $dt->format('j') . ' de ' . $meses[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y');
    };

    $baseUrl    = 'https://pradopeluqueria.infinityfree.me';
    $fechaNueva = $fmtFecha($nuevaFecha);
    $horaNueva  = substr($nuevaHora, 0, 5);

    // ════════════════════════════════════════════════════════
    //  ACEPTAR — confirmar con el horario del cliente
    // ════════════════════════════════════════════════════════
    if ($accion === 'aceptar') {
        // Verificar que el slot sigue libre
        $check = $db->prepare(
            "SELECT COUNT(*) FROM reservas
             WHERE barbero_id = ? AND fecha = ? AND hora = ?
               AND estado IN ('pendiente','aceptada')
               AND token != ?"
        );
        $check->execute([$r['barbero_id'], $nuevaFecha, $nuevaHora, $token]);
        if ((int)$check->fetchColumn() > 0) {
            mostrarPagina(
                'warn',
                'Horario ya ocupado',
                'El horario que el cliente propuso (<strong>' . $horaNueva . ' del ' . $fechaNueva . '</strong>) ya fue ocupado por otro cliente.<br><br>
                 Vuelve al <a href="' . $baseUrl . '/backend/admin.php" style="color:#d42b2b;">panel de administración</a> para gestionar la reserva.'
            );
        }

        $db->prepare(
            "UPDATE reservas
             SET fecha = ?, hora = ?, estado = 'aceptada',
                 nueva_fecha_propuesta = NULL, nueva_hora_propuesta = NULL
             WHERE token = ?"
        )->execute([$nuevaFecha, $nuevaHora, $token]);

        // Email al cliente confirmando
        $htmlCliente = buildEmail(
            '#22c55e',
            '¡Cita confirmada con tu horario!',
            'Prado Barber Co. &mdash; Bilbao',
            '<p style="color:#f0ece3;font-size:15px;margin-bottom:24px;">
              Hola <strong>' . htmlspecialchars($r['cliente_nombre']) . '</strong>,<br><br>
              El barbero ha <strong>aceptado</strong> tu horario propuesto. ¡Tu cita está confirmada!
            </p>' .
                buildTabla([
                    ['Servicio', htmlspecialchars($r['servicio_nombre']), ''],
                    ['Barbero',  htmlspecialchars($r['barbero_nombre']),  ''],
                    ['Fecha',    $fechaNueva,                             ''],
                    ['Hora',     $horaNueva,                              '#22c55e'],
                ]) .
                '<p style="color:#7a7880;font-size:13px;text-align:center;">
              ¿Necesitas cancelar? Llámanos al <a href="tel:+34944000000" style="color:#d42b2b;">+34 944 000 000</a>
            </p>'
        );
        sendBrevo($r['cliente_email'], $r['cliente_nombre'], '¡Cita confirmada! - Prado Barber Co.', $htmlCliente);

        mostrarPagina(
            'ok',
            '¡Cita confirmada!',
            'Has aceptado el horario de <strong>' . htmlspecialchars($r['cliente_nombre']) . '</strong>.<br>
             Nueva cita: <strong>' . $fechaNueva . '</strong> a las <strong>' . $horaNueva . '</strong>.<br><br>
             El cliente ha sido notificado por email.'
        );
    }

    // ════════════════════════════════════════════════════════
    //  CANCELAR — cancelar y notificar al cliente
    // ════════════════════════════════════════════════════════
    if ($accion === 'cancelar') {
        $db->prepare("UPDATE reservas SET estado = 'cancelada' WHERE token = ?")
            ->execute([$token]);

        $htmlCliente = buildEmail(
            '#374151',
            'Cita cancelada',
            'Prado Barber Co. &mdash; Bilbao',
            '<p style="color:#f0ece3;font-size:15px;margin-bottom:24px;">
              Hola <strong>' . htmlspecialchars($r['cliente_nombre']) . '</strong>,<br><br>
              Lamentamos informarte que el barbero no puede atenderte y la cita ha sido <strong>cancelada</strong>.
            </p>' .
                buildTabla([
                    ['Servicio', htmlspecialchars($r['servicio_nombre']), ''],
                    ['Barbero',  htmlspecialchars($r['barbero_nombre']),  ''],
                ]) .
                '<div style="text-align:center;margin-top:20px;">
               <a href="' . $baseUrl . '/reservas.html" style="display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;
                  padding:12px 28px;border-radius:6px;font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;">
                 Hacer nueva reserva
               </a>
             </div>'
        );
        sendBrevo($r['cliente_email'], $r['cliente_nombre'], 'Cita cancelada - Prado Barber Co.', $htmlCliente);

        mostrarPagina(
            'denied',
            'Cita cancelada',
            'Has cancelado la cita de <strong>' . htmlspecialchars($r['cliente_nombre']) . '</strong>.<br>
             El cliente ha sido notificado por email.'
        );
    }
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

function buildEmail(string $hc, string $title, string $sub, string $body): string
{
    return "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:{$hc};padding:24px 32px;'><h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>{$title}</h1>
    <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>{$sub}</p></div>
    <div style='padding:32px;'>{$body}</div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>
        2026 Prado Barber Co. &mdash; Hecho con precisión en Bilbao</p>
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
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['api-key: ' . $apiKey, 'Content-Type: application/json', 'Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode === 201;
}

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
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'>
<meta name='viewport' content='width=device-width,initial-scale=1.0'>
<title>{$titulo} — Prado Barber Co.</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
  .card{background:#111119;border:1px solid #252530;border-radius:16px;max-width:480px;width:100%;overflow:hidden;text-align:center;}
  .card-header{background:{$c['bg']};padding:2rem;color:{$c['text']};}
  .icon{width:64px;height:64px;border-radius:50%;background:rgba(0,0,0,.15);display:flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto 1rem;font-weight:700;}
  .card-header h1{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;}
  .card-body{padding:2rem;}
  .card-body p{color:#c0bcc9;font-size:.95rem;line-height:1.75;margin-bottom:1.5rem;}
  .card-body strong{color:#f0ece3;}
  .btn{display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;padding:.75rem 2rem;border-radius:4px;font-size:.75rem;font-weight:600;letter-spacing:.15em;text-transform:uppercase;margin:.35rem;}
  .brand{font-family:'Playfair Display',serif;font-style:italic;font-size:.9rem;color:#7a7880;margin-top:1.5rem;}
</style></head>
<body>
  <div class='card'>
    <div class='card-header'><div class='icon'>{$c['icon']}</div><h1>{$titulo}</h1></div>
    <div class='card-body'><p>{$mensaje}</p>
      <a href='{$baseUrl}/backend/admin.php' class='btn'>Ir al panel admin</a>
      <div class='brand'>Prado Barber Co.</div>
    </div>
  </div>
</body></html>";
    exit;
}
