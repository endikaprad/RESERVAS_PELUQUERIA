<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/cancel-by-barber.php
//
//  POST { token, accion, motivo, [nueva_fecha, nueva_hora] }
//
//  accion = 'cancelar'    → cancela la reserva y notifica al cliente
//  accion = 'reprogramar' → propone nuevo horario al cliente
//
//  RONDA LOGIC (CORRECTED):
//  - ronda increments when the BARBER proposes (each barber proposal = new round).
//  - ronda 1 = barber's first proposal to the client
//  - ronda 2 = barber's second proposal (after client counter-proposed)
//  - Client counter-proposals do NOT increment ronda (they respond within same round).
// ============================================================

require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function apiOk(mixed $data): never
{
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function apiErr(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiErr('Método no permitido', 405);
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$token  = trim($body['token']  ?? '');
$accion = trim($body['accion'] ?? '');
$motivo = trim($body['motivo'] ?? '');

if (!$token)  apiErr('Token requerido');
if (!$accion) apiErr('Acción requerida');
if (!in_array($accion, ['cancelar', 'reprogramar'], true)) apiErr('Acción inválida');
if (!$motivo) apiErr('El motivo es obligatorio');

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

    if (!$reserva) apiErr('Reserva no encontrada');

    if (in_array($reserva['estado'], ['cancelada', 'denegada'], true)) {
        apiErr('Esta reserva ya está ' . $reserva['estado']);
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

    function formatFechaES(string $fecha, array $dias, array $meses): string
    {
        $dt = new DateTime($fecha);
        return $dias[$dt->format('w')] . ', ' .
            $dt->format('j') . ' de ' .
            $meses[(int)$dt->format('n') - 1] . ' de ' .
            $dt->format('Y');
    }

    $baseUrl       = 'https://pradopeluqueria.infinityfree.me';
    $fechaOriginal = formatFechaES($reserva['fecha'], $dias, $meses);
    $horaOriginal  = substr($reserva['hora'], 0, 5);

    // ════════════════════════════════════════════════════════
    //  ACCIÓN: DENEGAR
    // ════════════════════════════════════════════════════════
    if ($accion === 'denegar') {

        $db->prepare("UPDATE reservas SET estado = 'denegada' WHERE token = ?")
            ->execute([$token]);

        $htmlCliente = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#374151;padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>Cita denegada por el barbero</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>Prado Barber Co. &mdash; Bilbao</p>
    </div>
    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;margin-bottom:24px;'>
        Hola <strong>" . htmlspecialchars($reserva['cliente_nombre']) . "</strong>,<br><br>
        Lamentamos informarte de que tu cita ha sido <strong>denegada</strong> por el barbero.
      </p>
      <table style='width:100%;border-collapse:collapse;margin-bottom:20px;'>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>Servicio</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($reserva['servicio_nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Barbero</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>" . htmlspecialchars($reserva['barbero_nombre']) . "</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;'>{$fechaOriginal}</td></tr>
        <tr><td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:10px 0;border-bottom:1px solid #252530;color:#9ca3af;font-size:16px;font-weight:700;'>{$horaOriginal}</td></tr>
        <tr><td style='padding:10px 0;color:#7a7880;font-size:13px;'>Motivo</td>
            <td style='padding:10px 0;color:#f0ece3;font-size:13px;font-style:italic;'>" . htmlspecialchars($motivo) . "</td></tr>
      </table>
      <div style='background:#18181f;border:1px solid #2f2f3c;border-radius:8px;padding:16px;text-align:center;'>
        <p style='color:#7a7880;font-size:13px;margin:0 0 12px;'>¿Quieres reservar otro horario?</p>
        <a href='{$baseUrl}/reservas.html'
           style='display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;
                  padding:10px 24px;border-radius:6px;font-size:13px;font-weight:600;
                  letter-spacing:0.08em;text-transform:uppercase;'>Hacer nueva reserva</a>
      </div>
    </div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>
        2026 Prado Barber Co. &mdash; Hecho con precisión en Bilbao
      </p>
    </div>
  </div>
</body></html>";

        sendBrevo(
            $reserva['cliente_email'],
            $reserva['cliente_nombre'],
            'Cita denegada - Prado Barber Co.',
            $htmlCliente
        );

        apiOk(['mensaje' => 'Reserva denegada y cliente notificado']);
    }

    // ════════════════════════════════════════════════════════
    //  ACCIÓN: REPROGRAMAR
    //
    //  RONDA FIX: Barber proposal NOW increments ronda.
    //  ronda 1 = barber's first proposal
    //  ronda 2 = barber's second proposal (after client counter-proposed in round 1)
    //  Client counter-proposals stay in the same ronda (handled in reschedule-client-counter.php).
    // ════════════════════════════════════════════════════════
    if ($accion === 'reprogramar') {

        $nuevaFecha = trim($body['nueva_fecha'] ?? '');
        $nuevaHora  = trim($body['nueva_hora']  ?? '');

        if (!$nuevaFecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevaFecha)) apiErr('Fecha inválida');
        if (!$nuevaHora  || !preg_match('/^\d{2}:\d{2}$/', $nuevaHora))        apiErr('Hora inválida');

        // Comprobar slot libre
        $check = $db->prepare(
            "SELECT COUNT(*) FROM reservas
             WHERE barbero_id = ? AND fecha = ? AND hora = ?
               AND estado IN ('pendiente','aceptada')
               AND token != ?"
        );
        $check->execute([$reserva['barbero_id'], $nuevaFecha, $nuevaHora . ':00', $token]);
        if ((int)$check->fetchColumn() > 0) {
            apiErr('Ese horario ya está ocupado. Selecciona otro.');
        }

        // Migración automática de columnas
        foreach (
            [
                "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS ronda_negociacion INT NOT NULL DEFAULT 0",
                "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS nueva_fecha_propuesta DATE NULL",
                "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS nueva_hora_propuesta TIME NULL",
                "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS motivo_cambio TEXT NULL",
            ] as $sql
        ) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) { /* ya existe */
            }
        }

        // FIX: Barber proposal increments ronda.
        // ronda 0 → 1 = barber's first proposal
        // ronda 1 → 2 = barber's second proposal, etc.
        $ronda = (int)($reserva['ronda_negociacion'] ?? 0) + 1;

        $db->prepare(
            "UPDATE reservas
             SET estado = 'reprogramar_barbero',
                 ronda_negociacion     = ?,
                 nueva_fecha_propuesta = ?,
                 nueva_hora_propuesta  = ?,
                 motivo_cambio         = ?
             WHERE token = ?"
        )->execute([$ronda, $nuevaFecha, $nuevaHora . ':00', $motivo, $token]);

        $fechaNueva  = formatFechaES($nuevaFecha, $dias, $meses);
        $urlAceptar  = $baseUrl . '/backend/api/reschedule-response.php?pt=' . $token . '&accion=aceptar';
        $urlRechazar = $baseUrl . '/backend/api/reschedule-response.php?pt=' . $token . '&accion=rechazar';

        // Show ronda badge — ronda 1 is first proposal, always show it
        $rondaEmailNote = "<p style='color:#7a7880;font-size:12px;text-align:center;margin-bottom:20px;'>Ronda de negociación {$ronda}</p>";

        $htmlCliente = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#c9a84c;padding:24px 32px;'>
      <h1 style='margin:0;color:#000;font-size:20px;font-weight:700;'>Propuesta de cambio de horario</h1>
      <p style='margin:6px 0 0;color:rgba(0,0,0,0.65);font-size:14px;'>Prado Barber Co. &mdash; Bilbao</p>
    </div>
    <div style='padding:32px;'>
      {$rondaEmailNote}
      <p style='color:#f0ece3;font-size:15px;margin-bottom:20px;'>
        Hola <strong>" . htmlspecialchars($reserva['cliente_nombre']) . "</strong>,<br><br>
        <strong>" . htmlspecialchars($reserva['barbero_nombre']) . "</strong> necesita cambiar tu cita y te propone un nuevo horario.
      </p>
      <p style='color:#d4a84b;font-size:13px;font-style:italic;margin-bottom:20px;
                padding:10px 14px;background:rgba(201,168,76,0.08);
                border-left:3px solid #c9a84c;border-radius:4px;'>
        Motivo: " . htmlspecialchars($motivo) . "
      </p>
      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td colspan='2' style='padding:8px 0 4px;color:#7a7880;font-size:11px;letter-spacing:0.15em;text-transform:uppercase;'>Cita original</td></tr>
        <tr><td style='padding:6px 0;border-bottom:1px solid #1c1c26;color:#7a7880;font-size:13px;width:120px;'>Fecha</td>
            <td style='padding:6px 0;border-bottom:1px solid #1c1c26;color:#9ca3af;font-size:13px;text-decoration:line-through;'>{$fechaOriginal}</td></tr>
        <tr><td style='padding:6px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:6px 0;border-bottom:1px solid #252530;color:#9ca3af;font-size:13px;text-decoration:line-through;'>{$horaOriginal}</td></tr>
        <tr><td colspan='2' style='padding:14px 0 4px;color:#7a7880;font-size:11px;letter-spacing:0.15em;text-transform:uppercase;'>Nueva propuesta</td></tr>
        <tr><td style='padding:6px 0;border-bottom:1px solid #1c1c26;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:6px 0;border-bottom:1px solid #1c1c26;color:#f0ece3;font-size:13px;font-weight:600;'>{$fechaNueva}</td></tr>
        <tr><td style='padding:6px 0;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:6px 0;color:#c9a84c;font-size:16px;font-weight:700;'>{$nuevaHora}</td></tr>
      </table>
      <p style='color:#f0ece3;font-size:14px;font-weight:600;margin-bottom:16px;text-align:center;'>¿Aceptas el nuevo horario?</p>
      <table style='width:100%;border-collapse:collapse;'>
        <tr>
          <td style='padding-right:8px;'>
            <a href='{$urlAceptar}'
               style='display:block;background:#22c55e;color:#fff;text-decoration:none;
                      padding:14px;border-radius:6px;font-size:14px;font-weight:700;
                      letter-spacing:0.08em;text-transform:uppercase;text-align:center;'>✓ Aceptar cambio</a>
          </td>
          <td style='padding-left:8px;'>
            <a href='{$urlRechazar}'
               style='display:block;background:#374151;color:#fff;text-decoration:none;
                      padding:14px;border-radius:6px;font-size:14px;font-weight:700;
                      letter-spacing:0.08em;text-transform:uppercase;text-align:center;'>✕ No puedo en ese horario</a>
          </td>
        </tr>
      </table>
      <p style='color:#7a7880;font-size:12px;margin-top:20px;text-align:center;'>
        Si no puedes en ese horario, podrás proponer una alternativa o cancelar la cita.
      </p>
    </div>
    <div style='background:#18181f;padding:16px 32px;text-align:center;'>
      <p style='margin:0;color:#7a7880;font-size:11px;letter-spacing:0.1em;text-transform:uppercase;'>
        2026 Prado Barber Co. &mdash; Hecho con precisión en Bilbao
      </p>
    </div>
  </div>
</body></html>";

        sendBrevo(
            $reserva['cliente_email'],
            $reserva['cliente_nombre'],
            'Propuesta de cambio de horario - Prado Barber Co.',
            $htmlCliente
        );

        apiOk(['mensaje' => 'Propuesta enviada al cliente', 'ronda' => $ronda]);
    }
} catch (PDOException $e) {
    apiErr('Error de base de datos: ' . $e->getMessage(), 500);
}

// ── Envío via Brevo API ──────────────────────────────────────
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
