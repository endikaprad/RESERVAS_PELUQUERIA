<?php
// ============================================================
//  PRADO BARBER CO. — gcal_helper.php
//
//  Funciones de ayuda para generar enlaces de Google Calendar
//  y el bloque HTML del botón en los emails de confirmación.
//
//  Uso:
//    require_once __DIR__ . '/gcal_helper.php';
//    $duracion  = parseDuracionMinutos('45 min');
//    $gcalUrl   = buildGCalUrl($fecha, $hora, $duracion, $servicio, $barbero, $notas);
//    $gcalBlock = buildGCalBlock($gcalUrl);
// ============================================================

/**
 * Parsea cadenas como "30 min", "1h", "1h30", "75 min", "1 hora 15 min"
 * y devuelve los minutos totales como entero.
 */
function parseDuracionMinutos(string $duracion): int
{
    $duracion = strtolower(trim($duracion));

    // Primero intentar extraer horas y minutos por separado
    // Ej: "1 hora 30 min", "1h 30m", "1h30"
    $horas   = 0;
    $minutos = 0;

    if (preg_match('/(\d+)\s*h(?:ora[s]?)?/i', $duracion, $mH)) {
        $horas = (int)$mH[1];
    }
    if (preg_match('/(\d+)\s*m(?:in(?:utos?)?)?/i', $duracion, $mM)) {
        $minutos = (int)$mM[1];
    }

    if ($horas > 0 || $minutos > 0) {
        return $horas * 60 + $minutos;
    }

    // Fallback: si solo hay dígitos, tratarlos como minutos
    if (preg_match('/(\d+)/', $duracion, $m)) {
        return (int)$m[1];
    }

    return 30; // valor por defecto
}

/**
 * Genera la URL de Google Calendar para "Añadir evento".
 *
 * @param string $fecha      YYYY-MM-DD
 * @param string $hora       HH:MM
 * @param int    $durMin     duración en minutos
 * @param string $servicio   nombre del servicio
 * @param string $barbero    nombre del barbero
 * @param string $notas      notas adicionales (opcional)
 *
 * @return string URL de Google Calendar
 */
function buildGCalUrl(
    string $fecha,
    string $hora,
    int    $durMin,
    string $servicio,
    string $barbero,
    string $notas = ''
): string {
    $tz = new DateTimeZone('Europe/Madrid');

    // Inicio del evento
    $inicio = new DateTime($fecha . ' ' . $hora . ':00', $tz);

    // Fin del evento
    $fin = clone $inicio;
    $fin->modify('+' . $durMin . ' minutes');

    // Google Calendar usa UTC en formato YYYYMMDDTHHmmssZ
    $utc = new DateTimeZone('UTC');
    $inicio->setTimezone($utc);
    $fin->setTimezone($utc);

    $startStr = $inicio->format('Ymd\THis\Z');
    $endStr   = $fin->format('Ymd\THis\Z');

    $titulo      = $servicio . ' · Prado Barber Co.';
    $descripcion = 'Barbero: ' . $barbero . "\n" . 'Prado Barber Co. — Calle Gran Vía, 12, Bilbao';
    if ($notas) {
        $descripcion .= "\n\nNotas: " . $notas;
    }
    $ubicacion = 'Calle Gran Vía, 12, 48001 Bilbao, España';

    $params = http_build_query([
        'action'   => 'TEMPLATE',
        'text'     => $titulo,
        'dates'    => $startStr . '/' . $endStr,
        'details'  => $descripcion,
        'location' => $ubicacion,
        'sf'       => 'true',
        'output'   => 'xml',
    ]);

    return 'https://calendar.google.com/calendar/render?' . $params;
}

/**
 * Devuelve el bloque HTML del botón "Añadir a Google Calendar"
 * listo para incrustar en un email HTML.
 *
 * @param string $gcalUrl  URL generada por buildGCalUrl()
 * @return string HTML
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
            <path d='M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h14c1.1 0
                     2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z'/>
          </svg>
          Añadir a Google Calendar
        </a>
      </div>";
}
