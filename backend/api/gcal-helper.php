<?php
// ============================================================
//  PRADO BARBER CO. — gcal_helper.php  (v2 — ICS + Schema.org)
//
//  Estrategia de máxima compatibilidad con Gmail:
//
//  1. ICS adjunto  → Gmail detecta el evento y muestra banner nativo
//  2. Schema.org   → Google extrae el evento de forma semántica
//  3. Botón HTML   → Fallback visual para otros clientes de email
//
//  Uso en booking.php / reserva-action.php / etc.:
//
//    require_once __DIR__ . '/gcal_helper.php';
//
//    $dur = parseDuracionMinutos($servicio['duracion']);
//    $ics = buildIcsContent($fecha, $hora, $dur, $servicio, $barbero, $notas, $uid);
//
//    // Construir email con sendBrevoWithIcs() en lugar de sendBrevo()
//    sendBrevoWithIcs($email, $nombre, $asunto, $htmlBody, $ics, $fecha, $hora);
//
// ============================================================

// ── 1. PARSER DE DURACIÓN ────────────────────────────────────

function parseDuracionMinutos(string $duracion): int
{
    $duracion = strtolower(trim($duracion));
    $horas = 0; $minutos = 0;
    if (preg_match('/(\d+)\s*h(?:ora[s]?)?/i', $duracion, $mH)) $horas   = (int)$mH[1];
    if (preg_match('/(\d+)\s*m(?:in(?:utos?)?)?/i', $duracion, $mM)) $minutos = (int)$mM[1];
    if ($horas > 0 || $minutos > 0) return $horas * 60 + $minutos;
    if (preg_match('/(\d+)/', $duracion, $m)) return (int)$m[1];
    return 30;
}

// ── 2. GENERAR CONTENIDO ICS ─────────────────────────────────

/**
 * Genera el contenido de un archivo .ics (iCalendar).
 * Gmail, Apple Mail y Outlook lo detectan automáticamente
 * y muestran un banner de "Añadir al calendario".
 *
 * @param string $uid   Identificador único del evento (ej: "reserva-{$id}@pradobarber")
 */
function buildIcsContent(
    string $fecha,
    string $hora,
    int    $durMin,
    string $servicio,
    string $barbero,
    string $notas = '',
    string $uid   = ''
): string {
    $tz = new DateTimeZone('Europe/Madrid');

    $inicio = new DateTime($fecha . ' ' . $hora . ':00', $tz);
    $fin    = clone $inicio;
    $fin->modify('+' . $durMin . ' minutes');
    $ahora  = new DateTime('now', new DateTimeZone('UTC'));

    // ICS usa formato UTC
    $utc = new DateTimeZone('UTC');
    $inicio->setTimezone($utc);
    $fin->setTimezone($utc);

    $dtstart  = $inicio->format('Ymd\THis\Z');
    $dtend    = $fin->format('Ymd\THis\Z');
    $dtstamp  = $ahora->format('Ymd\THis\Z');

    if (!$uid) $uid = 'reserva-' . md5($fecha . $hora . $servicio) . '@pradobarber.es';

    $titulo      = $servicio . ' · Prado Barber Co.';
    $descripcion = 'Barbero: ' . $barbero . '\nPrado Barber Co. — Calle Gran Vía\\, 12\\, Bilbao';
    if ($notas) $descripcion .= '\nNotas: ' . str_replace([',', "\n"], ['\\,', '\n'], $notas);
    $ubicacion   = 'Calle Gran Vía\\, 12\\, 48001 Bilbao\\, España';

    // Fold lines > 75 chars según RFC 5545
    $fold = function (string $line): string {
        $out = '';
        while (mb_strlen($line, 'UTF-8') > 75) {
            $out  .= mb_substr($line, 0, 75, 'UTF-8') . "\r\n ";
            $line  = mb_substr($line, 75, null, 'UTF-8');
        }
        return $out . $line;
    };

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Prado Barber Co.//Reservas//ES',
        'CALSCALE:GREGORIAN',
        'METHOD:REQUEST',             // ← clave: Gmail muestra banner de RSVP
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . $dtstamp,
        'DTSTART:' . $dtstart,
        'DTEND:' . $dtend,
        $fold('SUMMARY:' . $titulo),
        $fold('DESCRIPTION:' . $descripcion),
        $fold('LOCATION:' . $ubicacion),
        'ORGANIZER;CN=Prado Barber Co.:mailto:endikapradodev@gmail.com',
        'STATUS:CONFIRMED',
        'SEQUENCE:0',
        'BEGIN:VALARM',
        'TRIGGER:-PT60M',             // recordatorio 60 min antes
        'ACTION:DISPLAY',
        'DESCRIPTION:Recordatorio de tu cita en Prado Barber Co.',
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    return implode("\r\n", $lines) . "\r\n";
}

// ── 3. SCHEMA.ORG PARA EL HTML DEL EMAIL ────────────────────

/**
 * Genera el bloque <script type="application/ld+json"> con Schema.org
 * que Google usa para extraer el evento del email.
 * Se coloca justo antes del </head> del HTML del email.
 */
function buildSchemaOrgScript(
    string $fecha,
    string $hora,
    int    $durMin,
    string $servicio,
    string $barbero
): string {
    $tz = new DateTimeZone('Europe/Madrid');

    $inicio = new DateTime($fecha . ' ' . $hora . ':00', $tz);
    $fin    = clone $inicio;
    $fin->modify('+' . $durMin . ' minutes');

    $startIso = $inicio->format(DateTime::ATOM);
    $endIso   = $fin->format(DateTime::ATOM);

    $schema = [
        '@context'    => 'http://schema.org',
        '@type'       => 'EventReservation',
        'reservationStatus' => 'http://schema.org/ReservationConfirmed',
        'reservationFor'    => [
            '@type'     => 'Event',
            'name'      => $servicio . ' · Prado Barber Co.',
            'startDate' => $startIso,
            'endDate'   => $endIso,
            'location'  => [
                '@type'   => 'Place',
                'name'    => 'Prado Barber Co.',
                'address' => [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => 'Calle Gran Vía, 12',
                    'addressLocality' => 'Bilbao',
                    'postalCode'      => '48001',
                    'addressCountry'  => 'ES',
                ],
            ],
            'performer' => [
                '@type' => 'Person',
                'name'  => $barbero,
            ],
        ],
        'provider' => [
            '@type' => 'LocalBusiness',
            'name'  => 'Prado Barber Co.',
            'url'   => 'https://pradopeluqueria.infinityfree.me',
        ],
    ];

    $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return "<script type='application/ld+json'>\n{$json}\n</script>";
}

// ── 4. BOTÓN FALLBACK HTML ───────────────────────────────────

function buildGCalUrl(
    string $fecha,
    string $hora,
    int    $durMin,
    string $servicio,
    string $barbero,
    string $notas = ''
): string {
    $tz = new DateTimeZone('Europe/Madrid');
    $inicio = new DateTime($fecha . ' ' . $hora . ':00', $tz);
    $fin    = clone $inicio;
    $fin->modify('+' . $durMin . ' minutes');
    $utc = new DateTimeZone('UTC');
    $inicio->setTimezone($utc);
    $fin->setTimezone($utc);

    $descripcion = 'Barbero: ' . $barbero . "\nPrado Barber Co. — Calle Gran Vía, 12, Bilbao";
    if ($notas) $descripcion .= "\n\nNotas: " . $notas;

    $params = http_build_query([
        'action'   => 'TEMPLATE',
        'text'     => $servicio . ' · Prado Barber Co.',
        'dates'    => $inicio->format('Ymd\THis\Z') . '/' . $fin->format('Ymd\THis\Z'),
        'details'  => $descripcion,
        'location' => 'Calle Gran Vía, 12, 48001 Bilbao, España',
        'sf'       => 'true',
        'output'   => 'xml',
    ]);
    return 'https://calendar.google.com/calendar/render?' . $params;
}

/**
 * Bloque HTML del botón (fallback para clientes que no interpreten el ICS).
 */
function buildGCalBlock(string $gcalUrl): string
{
    return "
      <div style='text-align:center;margin-bottom:24px;'>
        <a href='{$gcalUrl}'
           target='_blank'
           style='display:inline-flex;align-items:center;gap:10px;
                  background:#1a73e8;color:#fff;text-decoration:none;
                  padding:13px 28px;border-radius:8px;
                  font-family:Arial,sans-serif;font-size:14px;font-weight:700;
                  letter-spacing:0.04em;
                  box-shadow:0 4px 16px rgba(26,115,232,0.35);'>
          <svg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24'
               fill='#fff' style='flex-shrink:0;'>
            <path d='M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3.9 3 3 5v14c0 1.1.9 2 2 2h14c1.1 0
                     2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z'/>
          </svg>
          Añadir a Google Calendar
        </a>
      </div>";
}

// ── 5. ENVÍO CON BREVO + ICS ADJUNTO ────────────────────────

/**
 * Envía un email a través de Brevo con el ICS adjunto.
 * El adjunto es lo que hace que Gmail muestre el banner nativo.
 *
 * Sustituye a sendBrevo() cuando la reserva está CONFIRMADA.
 *
 * @param string $icsContent  Contenido del archivo .ics (buildIcsContent())
 */
function sendBrevoWithIcs(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlContent,
    string $icsContent
): bool {
    $apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : '';
    if (!$apiKey) return false;

    // Brevo acepta adjuntos en base64
    $icsBase64 = base64_encode($icsContent);

    $payload = json_encode([
        'sender'      => ['name' => 'Prado Barber Co.', 'email' => 'endikapradodev@gmail.com'],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'htmlContent' => $htmlContent,
        'attachment'  => [[
            'name'    => 'cita-prado-barber.ics',
            'content' => $icsBase64,
        ]],
    ], JSON_UNESCAPED_UNICODE);

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
        error_log("Brevo+ICS error [{$httpCode}] to:{$toEmail} curl:{$error} resp:{$resp}");
    }
    return $httpCode === 201;
}

/**
 * Inyecta el Schema.org en el HTML del email (antes de </head> o al inicio del body).
 * Llama a esto antes de construir el HTML del email.
 */
function injectSchemaOrg(string $html, string $schemaScript): string
{
    // Intentar insertar antes de </head>
    if (stripos($html, '</head>') !== false) {
        return str_ireplace('</head>', $schemaScript . "\n</head>", $html);
    }
    // Si no hay <head>, insertar al inicio
    return $schemaScript . "\n" . $html;
}