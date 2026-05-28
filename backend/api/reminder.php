<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/reminder.php
//
//  Envía recordatorios por email 24 horas antes de cada cita.
//
//  EJECUCIÓN:
//    Cron job (cada hora):
//      0 * * * * php /ruta/a/backend/api/reminder.php >> /tmp/reminder.log 2>&1
//
//  O bien llamada HTTP (protegida por token):
//    GET /backend/api/reminder.php?secret=TU_SECRET_TOKEN
//
//  LÓGICA:
//    - Busca reservas con estado 'aceptada' cuya fecha sea MAÑANA
//    - Que no tengan recordatorio ya enviado
//    - Envía email al cliente con los detalles y enlace de cancelación
//    - Marca la reserva como recordatorio enviado
//    - Registra cada envío en la tabla reminder_log
// ============================================================

// ── Seguridad: proteger la llamada HTTP ──────────────────────
// Si se llama por HTTP (no CLI), exigir el token secreto
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');

    $secretToken = defined('REMINDER_SECRET') ? REMINDER_SECRET : 'prado-reminder-2026';
    $provided    = $_GET['secret'] ?? '';

    if (!hash_equals($secretToken, $provided)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
        exit;
    }
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/gcal-helper.php';

// ── Función de log ───────────────────────────────────────────
function logMsg(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    // También a error_log para que InfinityFree lo capture
    error_log('REMINDER: ' . $msg);
}

// ── Resultado acumulado para respuesta JSON ──────────────────
$resultado = [
    'ok'       => true,
    'enviados' => 0,
    'omitidos' => 0,
    'errores'  => 0,
    'detalle'  => [],
];

try {
    $db = getDB();
    $tz = new DateTimeZone('Europe/Madrid');

    // ── Migración: crear tabla de log si no existe ────────────
    $db->exec("
        CREATE TABLE IF NOT EXISTS reminder_log (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            reserva_id  INT UNSIGNED NOT NULL,
            enviado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resultado   ENUM('ok','error') NOT NULL DEFAULT 'ok',
            detalle     VARCHAR(300) NOT NULL DEFAULT '',
            INDEX idx_reserva (reserva_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Migración: añadir columna recordatorio_enviado si no existe
    try {
        $db->exec(
            "ALTER TABLE reservas
             ADD COLUMN recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0"
        );
        logMsg('Columna recordatorio_enviado creada en reservas.');
    } catch (PDOException $e) {
        // Ya existe — ignorar
    }

    // ── Calcular "mañana" en zona horaria Madrid ──────────────
    $ahora   = new DateTime('now', $tz);
    $manana  = (clone $ahora)->modify('+1 day')->format('Y-m-d');

    logMsg("Buscando citas para mañana: {$manana}");

    // ── Buscar reservas a las que hay que recordar ────────────
    //  • estado = 'aceptada'   (confirmadas)
    //  • fecha  = mañana
    //  • recordatorio_enviado = 0
    $stmt = $db->prepare("
        SELECT
            r.id,
            r.fecha,
            TIME_FORMAT(r.hora, '%H:%i') AS hora,
            r.cliente_nombre,
            r.cliente_email,
            r.cliente_telefono,
            r.notas,
            r.token,
            s.nombre   AS servicio,
            s.duracion AS duracion,
            b.nombre   AS barbero
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
        JOIN barberos  b ON b.id = r.barbero_id
        WHERE r.estado               = 'aceptada'
          AND r.fecha                = ?
          AND r.recordatorio_enviado = 0
        ORDER BY r.hora ASC
    ");
    $stmt->execute([$manana]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logMsg("Encontradas " . count($reservas) . " reservas para enviar recordatorio.");

    if (empty($reservas)) {
        logMsg("Nada que enviar. Fin.");
        outputResult($resultado);
        exit;
    }

    // ── Helpers de fecha en español ───────────────────────────
    $diasES  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $mesesES = [
        'enero','febrero','marzo','abril','mayo','junio',
        'julio','agosto','septiembre','octubre','noviembre','diciembre'
    ];

    function formatFechaES(string $fecha, array $dias, array $meses): string {
        $dt = new DateTime($fecha);
        return $dias[$dt->format('w')] . ', ' .
               $dt->format('j') . ' de ' .
               $meses[(int)$dt->format('n') - 1] . ' de ' .
               $dt->format('Y');
    }

    // ── Preparar sentencias de actualización ──────────────────
    $stmtMarcado = $db->prepare(
        "UPDATE reservas SET recordatorio_enviado = 1 WHERE id = ?"
    );
    $stmtLog = $db->prepare(
        "INSERT INTO reminder_log (reserva_id, resultado, detalle) VALUES (?, ?, ?)"
    );

    $baseUrl = 'https://pradopeluqueria.infinityfree.me';

    // ── Procesar cada reserva ─────────────────────────────────
    foreach ($reservas as $r) {
        $fechaFormateada = formatFechaES($r['fecha'], $diasES, $mesesES);
        $hora            = $r['hora'];
        $urlCancelar     = $baseUrl . '/backend/api/cancel-booking.php?token=' . $r['token'];

        // Google Calendar link
        $durMin   = parseDuracionMinutos($r['duracion'] ?? '30 min');
        $gcalUrl  = buildGCalUrl($r['fecha'], $hora, $durMin, $r['servicio'], $r['barbero'], $r['notas'] ?? '');

        // ── HTML del email de recordatorio ────────────────────
        $notasHtml = '';
        if (!empty($r['notas'])) {
            $notasHtml = "
            <tr>
                <td style='padding:10px 0;border-bottom:1px solid #252530;color:#7a7880;font-size:13px;width:120px;'>
                    Notas
                </td>
                <td style='padding:10px 0;border-bottom:1px solid #252530;color:#f0ece3;font-size:13px;font-style:italic;'>
                    " . htmlspecialchars($r['notas']) . "
                </td>
            </tr>";
        }

        $html = buildReminderEmail(
            $r['cliente_nombre'],
            $r['barbero'],
            $r['servicio'],
            $r['duracion'],
            $fechaFormateada,
            $hora,
            $notasHtml,
            $gcalUrl,
            $urlCancelar,
            $baseUrl
        );

        // ── Intentar enviar ───────────────────────────────────
        $enviado = sendBrevoReminder(
            $r['cliente_email'],
            $r['cliente_nombre'],
            "Recordatorio de tu cita mañana · Prado Barber Co.",
            $html
        );

        if ($enviado) {
            $stmtMarcado->execute([$r['id']]);
            $stmtLog->execute([$r['id'], 'ok', 'Recordatorio enviado a ' . $r['cliente_email']]);
            $resultado['enviados']++;
            $resultado['detalle'][] = [
                'id'      => $r['id'],
                'cliente' => $r['cliente_nombre'],
                'email'   => $r['cliente_email'],
                'fecha'   => $r['fecha'],
                'hora'    => $hora,
                'estado'  => 'enviado',
            ];
            logMsg("OK → #{$r['id']} {$r['cliente_nombre']} <{$r['cliente_email']}> — {$fechaFormateada} {$hora}");
        } else {
            $stmtLog->execute([$r['id'], 'error', 'Fallo al enviar a ' . $r['cliente_email']]);
            $resultado['errores']++;
            $resultado['detalle'][] = [
                'id'      => $r['id'],
                'cliente' => $r['cliente_nombre'],
                'email'   => $r['cliente_email'],
                'fecha'   => $r['fecha'],
                'hora'    => $hora,
                'estado'  => 'error_envio',
            ];
            logMsg("ERROR → #{$r['id']} {$r['cliente_nombre']} <{$r['cliente_email']}>");
        }
    }

    logMsg("Resumen: {$resultado['enviados']} enviados, {$resultado['errores']} errores.");

} catch (PDOException $e) {
    $resultado['ok']    = false;
    $resultado['error'] = 'BD: ' . $e->getMessage();
    logMsg("EXCEPCIÓN: " . $e->getMessage());
} catch (Exception $e) {
    $resultado['ok']    = false;
    $resultado['error'] = $e->getMessage();
    logMsg("EXCEPCIÓN: " . $e->getMessage());
}

outputResult($resultado);


// ════════════════════════════════════════════════════════════
//  FUNCIONES
// ════════════════════════════════════════════════════════════

function outputResult(array $r): void {
    if (php_sapi_name() === 'cli') {
        echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
    }
}

// ── Email HTML de recordatorio ───────────────────────────────
function buildReminderEmail(
    string $nombre,
    string $barbero,
    string $servicio,
    string $duracion,
    string $fechaFormateada,
    string $hora,
    string $notasHtml,
    string $gcalUrl,
    string $urlCancelar,
    string $baseUrl
): string {
    $nombreEsc   = htmlspecialchars($nombre);
    $barberoEsc  = htmlspecialchars($barbero);
    $servicioEsc = htmlspecialchars($servicio);
    $duracionEsc = htmlspecialchars($duracion);

    return "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'></head>
<body style='margin:0;padding:0;background:#09080f;font-family:Arial,sans-serif;'>
  <div style='max-width:560px;margin:0 auto;background:#111119;border:1px solid #252530;
              border-radius:12px;overflow:hidden;'>

    <!-- CABECERA -->
    <div style='background:linear-gradient(135deg,#1a1a2e 0%,#111119 100%);
                border-bottom:3px solid #c9a84c;padding:28px 32px;'>
      <div style='display:flex;align-items:center;gap:12px;margin-bottom:8px;'>
        <div style='width:40px;height:40px;border-radius:10px;background:rgba(201,168,76,.15);
                    border:1px solid rgba(201,168,76,.3);display:flex;align-items:center;
                    justify-content:center;font-size:18px;'>⏰</div>
        <div>
          <div style='font-size:11px;letter-spacing:.2em;text-transform:uppercase;
                      color:#c9a84c;font-weight:600;margin-bottom:2px;'>Recordatorio</div>
          <h1 style='margin:0;color:#f0ece3;font-size:19px;font-weight:700;'>
            Tu cita es mañana
          </h1>
        </div>
      </div>
      <p style='margin:0;color:#7a7880;font-size:13px;'>Prado Barber Co. &mdash; Bilbao</p>
    </div>

    <!-- CUERPO -->
    <div style='padding:32px;'>
      <p style='color:#f0ece3;font-size:15px;line-height:1.65;margin-bottom:24px;'>
        Hola <strong>{$nombreEsc}</strong>,<br><br>
        Solo un recordatorio: tienes una cita con
        <strong>{$barberoEsc}</strong> <strong>mañana</strong>.
        ¡Ya casi es la hora!
      </p>

      <!-- DETALLE CITA -->
      <div style='background:#18181f;border:1px solid #252530;border-radius:10px;
                  overflow:hidden;margin-bottom:24px;'>
        <div style='background:rgba(201,168,76,.07);border-bottom:1px solid #252530;
                    padding:10px 16px;'>
          <span style='font-size:11px;letter-spacing:.18em;text-transform:uppercase;
                       color:#c9a84c;font-weight:600;'>Detalles de la cita</span>
        </div>
        <table style='width:100%;border-collapse:collapse;'>
          <tr>
            <td style='padding:11px 16px;border-bottom:1px solid #1a1a26;
                       color:#7a7880;font-size:13px;width:110px;'>Servicio</td>
            <td style='padding:11px 16px;border-bottom:1px solid #1a1a26;
                       color:#f0ece3;font-size:13px;font-weight:600;'>{$servicioEsc}</td>
          </tr>
          <tr>
            <td style='padding:11px 16px;border-bottom:1px solid #1a1a26;
                       color:#7a7880;font-size:13px;'>Barbero</td>
            <td style='padding:11px 16px;border-bottom:1px solid #1a1a26;
                       color:#f0ece3;font-size:13px;'>{$barberoEsc}</td>
          </tr>
          <tr>
            <td style='padding:11px 16px;border-bottom:1px solid #1a1a26;
                       color:#7a7880;font-size:13px;'>Fecha</td>
            <td style='padding:11px 16px;border-bottom:1px solid #1a1a26;
                       color:#f0ece3;font-size:13px;'>{$fechaFormateada}</td>
          </tr>
          <tr>
            <td style='padding:11px 16px;border-bottom:1px solid #1a1a26;
                       color:#7a7880;font-size:13px;'>Hora</td>
            <td style='padding:11px 16px;border-bottom:1px solid #1a1a26;'>
              <span style='color:#c9a84c;font-size:22px;font-weight:700;
                           font-family:Georgia,serif;'>{$hora}</span>
            </td>
          </tr>
          <tr>
            <td style='padding:11px 16px;border-bottom:" . ($notasHtml ? '1px solid #1a1a26' : 'none') . ";
                       color:#7a7880;font-size:13px;'>Duración</td>
            <td style='padding:11px 16px;border-bottom:" . ($notasHtml ? '1px solid #1a1a26' : 'none') . ";
                       color:#f0ece3;font-size:13px;'>{$duracionEsc}</td>
          </tr>
          {$notasHtml}
        </table>
      </div>

      <!-- DIRECCIÓN -->
      <div style='background:rgba(201,168,76,.05);border:1px solid rgba(201,168,76,.15);
                  border-radius:8px;padding:14px 16px;margin-bottom:24px;
                  display:flex;align-items:flex-start;gap:10px;'>
        <span style='font-size:16px;margin-top:1px;'>📍</span>
        <div>
          <div style='color:#c9a84c;font-size:12px;font-weight:600;letter-spacing:.08em;
                      text-transform:uppercase;margin-bottom:3px;'>Dirección</div>
          <div style='color:#f0ece3;font-size:14px;'>Calle Gran Vía, 12</div>
          <div style='color:#7a7880;font-size:12px;'>48001 Bilbao, Bizkaia</div>
        </div>
      </div>

      <!-- BOTÓN GOOGLE CALENDAR -->
      <div style='text-align:center;margin-bottom:20px;'>
        <a href='{$gcalUrl}' target='_blank'
           style='display:inline-flex;align-items:center;gap:10px;
                  background:#1a73e8;color:#fff;text-decoration:none;
                  padding:12px 26px;border-radius:8px;
                  font-family:Arial,sans-serif;font-size:13px;font-weight:700;
                  letter-spacing:.04em;box-shadow:0 4px 16px rgba(26,115,232,.35);'>
          <svg xmlns='http://www.w3.org/2000/svg' width='18' height='18'
               viewBox='0 0 24 24' fill='#fff'>
            <path d='M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3.9 3 3 5v14c0 1.1.9 2 2 2h14
                     c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z'/>
          </svg>
          Ver en Google Calendar
        </a>
      </div>

      <!-- CANCELAR -->
      <div style='background:#18181f;border:1px solid #252530;border-radius:8px;
                  padding:16px;text-align:center;'>
        <p style='color:#7a7880;font-size:12px;margin:0 0 10px;line-height:1.5;'>
          ¿No puedes venir? Cancela antes de las <strong style='color:#f0ece3;'>23:59 de hoy</strong>
          para liberar el hueco.
        </p>
        <a href='{$urlCancelar}'
           style='display:inline-block;background:#374151;color:#f0ece3;
                  text-decoration:none;padding:9px 22px;border-radius:6px;
                  font-size:12px;font-weight:600;letter-spacing:.08em;
                  text-transform:uppercase;border:1px solid #4b5563;'>
          Cancelar cita
        </a>
      </div>
    </div>

    <!-- PIE -->
    <div style='background:#18181f;padding:16px 32px;
                border-top:1px solid #252530;text-align:center;'>
      <p style='margin:0 0 6px;color:#7a7880;font-size:11px;
                letter-spacing:.1em;text-transform:uppercase;'>
        2026 Prado Barber Co. &mdash; Hecho con precisión en Bilbao
      </p>
      <p style='margin:0;font-size:11px;color:#4a4a58;'>
        Recibiste este email porque tienes una cita confirmada.
        <a href='{$baseUrl}' style='color:#555;'>pradopeluqueria.infinityfree.me</a>
      </p>
    </div>
  </div>
</body>
</html>";
}

// ── Envío via Brevo ──────────────────────────────────────────
function sendBrevoReminder(
    string $toEmail,
    string $toName,
    string $subject,
    string $html
): bool {
    $apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : '';
    if (!$apiKey) {
        logMsg("WARN: BREVO_API_KEY no definida.");
        return false;
    }

    $payload = json_encode([
        'sender'      => [
            'name'  => 'Prado Barber Co.',
            'email' => 'endikapradodev@gmail.com',
        ],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'htmlContent' => $html,
        'tags'        => ['recordatorio'],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 201) {
        logMsg("Brevo error [{$httpCode}] to:{$toEmail} curl:{$curlErr} resp:{$resp}");
        return false;
    }

    return true;
}