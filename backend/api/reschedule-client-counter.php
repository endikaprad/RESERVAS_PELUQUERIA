<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/reschedule-client-counter.php
//
//  POST { token, nueva_fecha, nueva_hora }
//
//  The client proposes an alternative time to the barber.
//  Estado → 'reprogramar_cliente'.
//  The barber receives email with options: accept / cancel / counter-propose.
//
//  RONDA LOGIC (CORRECTED):
//  - ronda is set by the BARBER when proposing (in cancel-by-barber.php).
//  - Client counter-proposals do NOT increment ronda — they respond within the same round.
//  - ronda 1 = barber's first proposal (and client's response within that round)
//  - ronda 2 = barber's second proposal (after client declined round 1)
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

function apiOk(mixed $d): never
{
    echo json_encode(['ok' => true, 'data' => $d], JSON_UNESCAPED_UNICODE);
    exit;
}
function apiErr(string $m, int $c = 400): never
{
    http_response_code($c);
    echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') apiErr('Método no permitido', 405);

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];

$token      = trim($body['token']       ?? '');
$nuevaFecha = trim($body['nueva_fecha'] ?? '');
$nuevaHora  = trim($body['nueva_hora']  ?? '');

if (!$token) apiErr('Token requerido');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevaFecha)) apiErr('Fecha inválida');
if (!preg_match('/^\d{2}:\d{2}$/',        $nuevaHora))  apiErr('Hora inválida');

try {
    $db = getDB();

    // ── Buscar reserva ───────────────────────────────────────
    $stmt = $db->prepare(
        'SELECT r.*, s.nombre AS servicio_nombre,
                b.nombre AS barbero_nombre, b.id AS barbero_id_val
         FROM reservas r
         JOIN servicios s ON s.id = r.servicio_id
         JOIN barberos  b ON b.id = r.barbero_id
         WHERE r.token = ?'
    );
    $stmt->execute([$token]);
    $r = $stmt->fetch();

    if (!$r) apiErr('Reserva no encontrada');

    // Solo se puede contraproponar si el barbero acaba de proponer
    if ($r['estado'] !== 'reprogramar_barbero') {
        apiErr('Esta propuesta ya fue procesada o el estado no es válido.');
    }

    // Verificar que el slot esté libre para ese barbero
    $check = $db->prepare(
        "SELECT COUNT(*) FROM reservas
         WHERE barbero_id = ? AND fecha = ? AND hora = ?
           AND estado IN ('pendiente','aceptada')
           AND token != ?"
    );
    $check->execute([$r['barbero_id'], $nuevaFecha, $nuevaHora . ':00', $token]);
    if ((int)$check->fetchColumn() > 0) {
        apiErr('Ese horario ya está ocupado. Elige otro.');
    }

    // Migración columnas (por si acaso)
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
        } catch (PDOException $e) {
        }
    }

    // FIX: Client counter-proposal KEEPS the current ronda (does NOT increment).
    // The ronda was set by the barber when proposing. Client is responding within that round.
    $ronda = (int)($r['ronda_negociacion'] ?? 0);

    // Actualizar reserva con la contrapropuesta del cliente
    $db->prepare(
        "UPDATE reservas
         SET estado = 'reprogramar_cliente',
             ronda_negociacion     = ?,
             nueva_fecha_propuesta = ?,
             nueva_hora_propuesta  = ?,
             motivo_cambio         = CONCAT(IFNULL(motivo_cambio,''), ' | Contrapropuesta cliente (ronda {$ronda})')
         WHERE token = ?"
    )->execute([$ronda, $nuevaFecha, $nuevaHora . ':00', $token]);

    // ── Helpers fecha ────────────────────────────────────────
    $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $fmtFecha = function (string $f) use ($dias, $meses): string {
        $dt = new DateTime($f);
        return $dias[$dt->format('w')] . ', ' . $dt->format('j') . ' de ' . $meses[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y');
    };

    $baseUrl       = 'https://pradopeluqueria.infinityfree.me';
    $fechaOriginal = $fmtFecha($r['fecha']);
    $horaOriginal  = substr($r['hora'], 0, 5);
    $fechaNueva    = $fmtFecha($nuevaFecha);

    // URLs para el barbero
    $urlAceptar  = $baseUrl . '/backend/api/barber-accept-counter.php?token=' . urlencode($token) . '&accion=aceptar';
    $urlCancelar = $baseUrl . '/backend/api/barber-accept-counter.php?token=' . urlencode($token) . '&accion=cancelar';
    $urlPanel    = $baseUrl . '/backend/admin.php?reschedule_pt=' . urlencode($token) . '&raccion=reproponer';

    $rondaLabel = "Ronda de negociación {$ronda}";

    $htmlBarbero = "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;border-radius:12px;overflow:hidden;'>
    <div style='background:#2550a0;padding:24px 32px;'>
      <h1 style='margin:0;color:#fff;font-size:20px;font-weight:700;'>El cliente propone otro horario</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,0.8);font-size:14px;'>Prado Barber Co. &mdash; {$rondaLabel}</p>
    </div>
    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;margin-bottom:20px;'>
        <strong>" . htmlspecialchars($r['cliente_nombre']) . "</strong> no puede en el horario que propusiste 
        y te ofrece una alternativa.
      </p>
      <table style='width:100%;border-collapse:collapse;margin-bottom:28px;'>
        <tr><td colspan='2' style='padding:6px 0 4px;color:#7a7880;font-size:11px;letter-spacing:.15em;text-transform:uppercase;'>Tu propuesta anterior</td></tr>
        <tr><td style='padding:6px 0;border-bottom:1px solid #1c1c26;color:#7a7880;font-size:13px;width:110px;'>Fecha</td>
            <td style='padding:6px 0;border-bottom:1px solid #1c1c26;color:#9ca3af;font-size:13px;text-decoration:line-through;'>" . htmlspecialchars($fmtFecha($r['nueva_fecha_propuesta'] ?? $r['fecha'])) . "</td></tr>
        <tr><td style='padding:6px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:6px 0;border-bottom:1px solid #252530;color:#9ca3af;font-size:13px;text-decoration:line-through;'>" . substr($r['nueva_hora_propuesta'] ?? $r['hora'], 0, 5) . "</td></tr>
        <tr><td colspan='2' style='padding:14px 0 4px;color:#7a7880;font-size:11px;letter-spacing:.15em;text-transform:uppercase;'>Propuesta del cliente</td></tr>
        <tr><td style='padding:6px 0;border-bottom:1px solid #1c1c26;color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:6px 0;border-bottom:1px solid #1c1c26;color:#f0ece3;font-size:13px;font-weight:600;'>{$fechaNueva}</td></tr>
        <tr><td style='padding:6px 0;color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:6px 0;color:#6b9fff;font-size:16px;font-weight:700;'>{$nuevaHora}</td></tr>
      </table>
      <p style='color:#f0ece3;font-size:14px;font-weight:600;margin-bottom:16px;text-align:center;'>¿Qué quieres hacer?</p>
      <table style='width:100%;border-collapse:collapse;margin-bottom:10px;'>
        <tr>
          <td style='padding-right:6px;'>
            <a href='{$urlAceptar}' style='display:block;background:#22c55e;color:#fff;text-decoration:none;
               padding:13px;border-radius:6px;font-size:13px;font-weight:700;letter-spacing:.08em;
               text-transform:uppercase;text-align:center;'>✓ Aceptar horario</a>
          </td>
          <td style='padding:0 3px;'>
            <a href='{$urlCancelar}' style='display:block;background:#d42b2b;color:#fff;text-decoration:none;
               padding:13px;border-radius:6px;font-size:13px;font-weight:700;letter-spacing:.08em;
               text-transform:uppercase;text-align:center;'>✕ Cancelar cita</a>
          </td>
        </tr>
      </table>
      <a href='{$urlPanel}' style='display:block;background:#374151;color:#f0ece3;text-decoration:none;
         padding:12px;border-radius:6px;font-size:13px;font-weight:700;letter-spacing:.08em;
         text-transform:uppercase;text-align:center;margin-top:8px;border:1px solid #4b5563;'>
        ⇄ Proponer otro horario (panel admin)
      </a>
      <p style='color:#7a7880;font-size:12px;margin-top:20px;text-align:center;'>
        {$rondaLabel} &middot; <a href='{$baseUrl}/backend/admin.php' style='color:#6b9fff;'>Ver panel</a>
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
        'endikapradodev@gmail.com',
        'Prado Barber Co.',
        "Cliente propone horario alternativo ({$rondaLabel}) - {$r['cliente_nombre']} - {$fechaNueva} {$nuevaHora}",
        $htmlBarbero
    );

    apiOk(['mensaje' => 'Propuesta enviada al barbero. Te notificaremos cuando responda.', 'ronda' => $ronda]);
} catch (PDOException $e) {
    apiErr('Error de base de datos: ' . $e->getMessage(), 500);
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
    if ($httpCode !== 201) error_log("Brevo error [{$httpCode}] to:{$toEmail} resp:{$resp}");
    return $httpCode === 201;
}