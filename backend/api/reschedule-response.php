<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/reschedule-response.php
//
//  El CLIENTE responde a una propuesta de cambio de horario.
//  ?pt=TOKEN&accion=aceptar|rechazar
//  Con botón de Google Calendar en el email de confirmación.
// ============================================================

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/gcal-helper.php';   // ← NUEVO

$token  = trim($_GET['pt']     ?? '');
$accion = trim($_GET['accion'] ?? '');

if (!$token || !in_array($accion, ['aceptar', 'rechazar'], true)) {
  mostrarPagina('error', 'Enlace inválido', 'El enlace no es válido o está incompleto.');
}

try {
  $db = getDB();

  $stmt = $db->prepare(
    'SELECT r.*, s.nombre AS servicio_nombre, s.duracion,
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

  $estadosValidos = ['reprogramar_barbero', 'reprogramar_cliente'];

  if (!in_array($reserva['estado'], $estadosValidos, true)) {
    $estadoActual = $reserva['estado'];
    if ($estadoActual === 'aceptada') {
      $msg = 'Esta reserva ya fue <strong>confirmada</strong>. ¡Te esperamos!';
    } elseif ($estadoActual === 'cancelada') {
      $msg = 'Esta reserva ya fue <strong>cancelada</strong>.';
    } elseif ($estadoActual === 'denegada') {
      $msg = 'Esta reserva ya fue <strong>denegada</strong>.';
    } elseif ($estadoActual === 'pendiente') {
      $msg = 'Esta reserva está <strong>pendiente</strong> de confirmación por el barbero.';
    } else {
      $msg = 'Esta propuesta ya fue procesada anteriormente.';
    }
    mostrarPagina('info', 'Ya procesada', $msg);
  }

  $nuevaFecha    = $reserva['nueva_fecha_propuesta'] ?? '';
  $nuevaHora     = $reserva['nueva_hora_propuesta']  ?? '';
  $motivoCambio  = $reserva['motivo_cambio'] ?? '';
  $rondaActual   = (int)($reserva['ronda_negociacion'] ?? 0);

  if (!$nuevaFecha || !$nuevaHora) {
    mostrarPagina('error', 'Datos incompletos', 'No se encontraron los datos de la nueva propuesta.');
  }

  $dias  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
  $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  function fmtFecha(string $f, array $d, array $m): string
  {
    $dt = new DateTime($f);
    return $d[$dt->format('w')] . ', ' . $dt->format('j') . ' de ' . $m[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y');
  }

  $baseUrl       = 'https://pradopeluqueria.infinityfree.me';
  $fechaOriginal = fmtFecha($reserva['fecha'], $dias, $meses);
  $horaOriginal  = substr($reserva['hora'], 0, 5);
  $fechaNueva    = fmtFecha($nuevaFecha, $dias, $meses);
  $horaNueva     = substr($nuevaHora, 0, 5);

  // ════════════════════════════════════════════════════════
  //  ACEPTAR — el cliente acepta el horario propuesto
  // ════════════════════════════════════════════════════════
  if ($accion === 'aceptar') {
    // Verificar que el slot propuesto sigue libre
    $check = $db->prepare(
      "SELECT COUNT(*) FROM reservas
             WHERE barbero_id = ? AND fecha = ? AND hora = ?
               AND estado IN ('pendiente','aceptada')
               AND token != ?"
    );
    $check->execute([$reserva['barbero_id'], $nuevaFecha, $nuevaHora, $token]);
    if ((int)$check->fetchColumn() > 0) {
      mostrarPagina(
        'warn',
        'Horario ya ocupado',
        'Lo sentimos, el horario propuesto (<strong>' . $horaNueva . ' del ' . $fechaNueva . '</strong>) ya fue ocupado por otro cliente.<br><br>
                 Vuelve a <a href="' . $baseUrl . '/reservas.html" style="color:#d42b2b;">reservar una nueva cita</a> o llámanos al <a href="tel:+34944000000" style="color:#d42b2b;">+34 944 000 000</a>.'
      );
    }

    // Actualizar: la fecha/hora pasa a ser la propuesta, estado=aceptada
    $db->prepare(
      "UPDATE reservas
             SET fecha = ?, hora = ?, estado = 'aceptada',
                 nueva_fecha_propuesta = NULL,
                 nueva_hora_propuesta  = NULL,
                 ronda_negociacion     = 0
             WHERE token = ?"
    )->execute([$nuevaFecha, $nuevaHora, $token]);

    // ── Google Calendar ──────────────────────────────────────────
    $duracionMinutos = parseDuracionMinutos($reserva['duracion'] ?? '30 min');
    $gcalUrl   = buildGCalUrl(
      $nuevaFecha,
      $horaNueva,
      $duracionMinutos,
      $reserva['servicio_nombre'],
      $reserva['barbero_nombre'],
      $reserva['notas'] ?? ''
    );
    $gcalBlock  = buildGCalBlock($gcalUrl);
    $icsUid     = 'reserva-' . $reserva['id'] . '-rr@pradobarber.es';
    $icsContent = buildIcsContent(
      $nuevaFecha,
      $horaNueva,
      $duracionMinutos,
      $reserva['servicio_nombre'],
      $reserva['barbero_nombre'],
      $reserva['notas'] ?? '',
      $icsUid
    );

    // ── Botón cancelar ───────────────────────────────────────────
    $urlCancelarCliente = $baseUrl . '/backend/api/cancel-booking.php?token=' . $token;
    $cancelBox = "
      <div style='background:#18181f;border:1px solid #252530;border-radius:8px;padding:16px;margin-bottom:24px;text-align:center;'>
        <p style='color:#7a7880;font-size:13px;margin:0 0 12px;'>
          ¿Necesitas cancelar tu reserva?
        </p>
        <a href='{$urlCancelarCliente}'
           style='display:inline-block;background:#374151;color:#f0ece3;text-decoration:none;
                  padding:10px 24px;border-radius:6px;font-size:13px;font-weight:600;
                  letter-spacing:0.08em;text-transform:uppercase;border:1px solid #4b5563;'>
          Cancelar reserva
        </a>
        <p style='color:#52525b;font-size:11px;margin:10px 0 0;'>
          Solo disponible hasta las 23:59 del día anterior a tu cita.
        </p>
      </div>";

    // Email al cliente confirmando
    $htmlCliente = buildEmailBase(
      '#22c55e',
      '¡Cambio de horario confirmado!',
      'Prado Barber Co. — Bilbao',
      '<p style="color:#f0ece3;font-size:15px;margin-bottom:24px;">
              Hola <strong>' . htmlspecialchars($reserva['cliente_nombre']) . '</strong>,<br><br>
              Has <strong>aceptado</strong> el cambio de horario. Tu nueva cita queda confirmada. ¡Te esperamos!
            </p>' .
        buildTabla([
          ['Servicio', htmlspecialchars($reserva['servicio_nombre']), ''],
          ['Barbero',  htmlspecialchars($reserva['barbero_nombre']),  ''],
          ['Fecha',    $fechaNueva,                                   ''],
          ['Hora',     $horaNueva,                                    '#22c55e'],
        ]) .
        $gcalBlock .
        $cancelBox
    );

    // Email al barbero
    $htmlBarbero = buildEmailBase(
      '#22c55e',
      'Cliente aceptó el cambio de horario',
      'Prado Barber Co. — Admin',
      '<p style="color:#f0ece3;font-size:15px;margin-bottom:24px;">
              <strong>' . htmlspecialchars($reserva['cliente_nombre']) . '</strong> ha aceptado el nuevo horario.
            </p>' .
        buildTabla([
          ['Cliente',     htmlspecialchars($reserva['cliente_nombre']), ''],
          ['Nueva fecha', $fechaNueva,                                  ''],
          ['Nueva hora',  $horaNueva,                                   '#22c55e'],
        ]) .
        '<p style="color:#7a7880;font-size:12px;text-align:center;">
              <a href="' . $baseUrl . '/backend/admin.php" style="color:#22c55e;">Ver en el panel</a>
            </p>'
    );

    sendBrevoWithIcs(
      $reserva['cliente_email'],
      $reserva['cliente_nombre'],
      'Cambio de horario confirmado - Prado Barber Co.',
      $htmlCliente,
      $icsContent
    );
    sendBrevo(
      'endikapradodev@gmail.com',
      'Prado Barber Co.',
      'Cliente aceptó cambio - ' . $reserva['cliente_nombre'],
      $htmlBarbero
    );

    mostrarPagina(
      'ok',
      '¡Cambio confirmado!',
      'Tu nueva cita queda fijada para el <strong>' . $fechaNueva . '</strong> a las <strong>' . $horaNueva . '</strong>.<br><br>Hemos enviado la confirmación con el enlace de Google Calendar a tu email.'
    );
  }

  // ════════════════════════════════════════════════════════
  //  RECHAZAR — el cliente rechaza el horario propuesto
  // ════════════════════════════════════════════════════════
  if ($accion === 'rechazar') {
    mostrarPantallaRechazar($reserva, $fechaOriginal, $horaOriginal, $fechaNueva, $horaNueva, $motivoCambio, $rondaActual, $baseUrl, $token);
  }
} catch (PDOException $e) {
  mostrarPagina('error', 'Error de base de datos', htmlspecialchars($e->getMessage()));
}

// ════════════════════════════════════════════════════════════
//  Pantalla de rechazo
// ════════════════════════════════════════════════════════════
function mostrarPantallaRechazar(
  array  $reserva,
  string $fechaOriginal,
  string $horaOriginal,
  string $fechaNueva,
  string $horaNueva,
  string $motivoCambio,
  int    $ronda,
  string $baseUrl,
  string $token
): never {

  $barberoId    = $reserva['barbero_id'];
  $tokenEnc     = urlencode($token);
  $cancelApiUrl = $baseUrl . '/backend/api/cancel-by-barber.php';

  $rondaBadgeHtml = "<div class=\"ronda-badge\">⇄ Ronda de negociación {$ronda}</div>";

  $motivoHtml = '';
  if ($motivoCambio) {
    $motivoClean = explode(' | ', $motivoCambio)[0];
    $motivoEsc   = htmlspecialchars(trim($motivoClean));
    $motivoHtml  = "<div class=\"motivo-box\">Motivo del barbero: {$motivoEsc}</div>";
  }

  $svNombre   = htmlspecialchars($reserva['servicio_nombre']);
  $bNombre    = htmlspecialchars($reserva['barbero_nombre']);

  echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Responder propuesta — Prado Barber Co.</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=DM+Sans:wght@300;400;500&display=swap');
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.25rem;}
    .card{background:#111119;border:1px solid #252530;border-radius:16px;max-width:520px;width:100%;overflow:hidden;}
    .card-header{background:linear-gradient(135deg,#1a1a2e,#111119);border-bottom:1px solid #252530;padding:1.75rem 2rem;}
    .brand{font-family:'Playfair Display',serif;font-style:italic;font-size:.85rem;color:#7a7880;margin-bottom:.6rem;}
    .card-header h1{font-family:'Playfair Display',serif;font-size:1.35rem;font-weight:700;margin-bottom:.3rem;}
    .card-header p{color:#7a7880;font-size:.82rem;line-height:1.6;}
    .card-body{padding:1.75rem 2rem;}
    .info-box{background:#18181f;border:1px solid #2f2f3c;border-radius:10px;padding:14px 16px;margin-bottom:1.5rem;}
    .info-box table{width:100%;border-collapse:collapse;}
    .info-box td{padding:7px 0;border-bottom:1px solid #1c1c26;font-size:.85rem;}
    .info-box tr:last-child td{border-bottom:none;}
    .info-box td:first-child{color:#7a7880;width:110px;}
    .info-box td:last-child{color:#f0ece3;font-weight:500;}
    .badge-old{text-decoration:line-through;color:#9ca3af!important;font-weight:400!important;}
    .badge-new{color:#c9a84c!important;font-weight:700!important;}
    .motivo-box{background:rgba(201,168,76,.06);border-left:3px solid #c9a84c;border-radius:0 6px 6px 0;padding:10px 14px;margin-bottom:1.5rem;font-size:.8rem;color:#d4a84b;font-style:italic;}
    .mode-tabs{display:flex;gap:.5rem;margin-bottom:1.5rem;}
    .mode-tab{flex:1;padding:.75rem .5rem;background:#18181f;border:1px solid #252530;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#7a7880;cursor:pointer;transition:all .2s;}
    .mode-tab:hover{border-color:#7a7880;color:#f0ece3;}
    .mode-tab.active.counter{background:rgba(201,168,76,.1);border-color:rgba(201,168,76,.5);color:#c9a84c;}
    .mode-tab.active.cancel{background:rgba(107,114,128,.12);border-color:rgba(107,114,128,.5);color:#9ca3af;}
    .pane{display:none;}
    .pane.active{display:block;}
    .cal-wrap{background:#18181f;border:1px solid #252530;border-radius:10px;padding:1rem;margin-bottom:1rem;}
    .cal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;}
    .cal-title-txt{font-size:.9rem;font-weight:600;}
    .cal-nav{display:flex;gap:.3rem;}
    .cal-nav button{width:28px;height:28px;border:1px solid #252530;border-radius:5px;background:transparent;color:#7a7880;font-size:.85rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;font-family:'DM Sans',sans-serif;}
    .cal-nav button:hover{border-color:#c9a84c;color:#c9a84c;}
    .dow-row{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-bottom:.3rem;}
    .dow-lbl{text-align:center;font-size:.55rem;letter-spacing:.08em;text-transform:uppercase;color:#7a7880;padding:.2rem 0;}
    .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;}
    .cal-cell{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:.75rem;cursor:pointer;transition:all .15s;border:1px solid transparent;}
    .cal-cell:hover:not(.c-dis):not(.c-empty){border-color:rgba(201,168,76,.4);color:#c9a84c;}
    .cal-cell.c-today:not(.c-sel){border-color:rgba(212,43,43,.35);color:#d42b2b;}
    .cal-cell.c-dis{color:#2a2a38;cursor:not-allowed;}
    .cal-cell.c-sel{background:rgba(201,168,76,.18);border-color:#c9a84c;color:#c9a84c;font-weight:700;}
    .cal-cell.c-empty{cursor:default;}
    .slots-wrap{margin-bottom:1.25rem;}
    .slots-label{font-size:.65rem;letter-spacing:.15em;text-transform:uppercase;color:#7a7880;margin-bottom:.6rem;}
    .slots-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.4rem;}
    .slot{padding:.5rem .25rem;border:1px solid #252530;border-radius:6px;text-align:center;font-size:.8rem;color:#7a7880;cursor:pointer;transition:all .18s;background:#18181f;}
    .slot:hover:not(.s-taken):not(.s-past){border-color:#c9a84c;color:#c9a84c;}
    .slot.s-sel{background:rgba(201,168,76,.12);border-color:#c9a84c;color:#c9a84c;font-weight:600;}
    .slot.s-taken{opacity:.3;cursor:not-allowed;text-decoration:line-through;}
    .slot.s-past{opacity:.2;cursor:not-allowed;text-decoration:line-through;}
    .slots-msg{text-align:center;padding:.85rem;color:#7a7880;font-size:.8rem;grid-column:1/-1;}
    .btn-counter{width:100%;padding:.9rem;border-radius:8px;background:linear-gradient(135deg,#c9a84c,#a17c2d);border:none;color:#000;font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;transition:all .25s;margin-bottom:.65rem;}
    .btn-counter:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 8px 24px rgba(201,168,76,.3);}
    .btn-counter:disabled{opacity:.4;cursor:not-allowed;transform:none;}
    .btn-cancel-all{width:100%;padding:.9rem;border-radius:8px;background:rgba(107,114,128,.1);border:1px solid rgba(107,114,128,.4);color:#9ca3af;font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;transition:all .25s;}
    .btn-cancel-all:hover:not(:disabled){background:#4b5563;color:#fff;border-color:#6b7280;}
    .btn-cancel-all:disabled{opacity:.4;cursor:not-allowed;}
    .warn-box{background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.78rem;color:#d4a84b;line-height:1.6;}
    .status-msg{padding:.65rem 1rem;border-radius:8px;font-size:.78rem;margin-top:.75rem;display:none;align-items:center;gap:.5rem;}
    .status-msg.ok{display:flex;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#22c55e;}
    .status-msg.err{display:flex;background:rgba(212,43,43,.1);border:1px solid rgba(212,43,43,.25);color:#d42b2b;}
    .ronda-badge{display:inline-block;padding:.2rem .65rem;background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);border-radius:100px;font-size:.7rem;color:#f59e0b;margin-bottom:1rem;}
  </style>
</head>
<body>
  <div class="card">
    <div class="card-header">
      <div class="brand">Prado Barber Co. · Bilbao</div>
      <h1>No puedes en ese horario</h1>
      <p>Puedes proponer un horario alternativo o cancelar la cita.</p>
    </div>
    <div class="card-body">
      {$rondaBadgeHtml}
      <div class="info-box">
        <table>
          <tr><td>Servicio</td><td>{$svNombre}</td></tr>
          <tr><td>Barbero</td><td>{$bNombre}</td></tr>
          <tr><td>Cita original</td><td class="badge-old">{$fechaOriginal} · {$horaOriginal}</td></tr>
          <tr><td>Nueva propuesta</td><td class="badge-new">{$fechaNueva} · {$horaNueva}</td></tr>
        </table>
      </div>
      {$motivoHtml}

      <div class="mode-tabs">
        <button class="mode-tab active counter" id="tab-counter" onclick="switchMode('counter')">⇄ Proponer alternativa</button>
        <button class="mode-tab cancel"         id="tab-cancel"  onclick="switchMode('cancel')">✕ Cancelar cita</button>
      </div>

      <div class="pane active" id="pane-counter">
        <p style="font-size:.82rem;color:#7a7880;margin-bottom:1rem;line-height:1.6;">
          Elige el día y la hora que mejor te vengan. El barbero recibirá tu propuesta.
        </p>
        <div class="cal-wrap">
          <div class="cal-header">
            <span class="cal-title-txt" id="cal-title">—</span>
            <div class="cal-nav">
              <button type="button" onclick="calNav(-1)">&#8249;</button>
              <button type="button" onclick="calNav(1)">&#8250;</button>
            </div>
          </div>
          <div class="dow-row">
            <div class="dow-lbl">L</div><div class="dow-lbl">M</div><div class="dow-lbl">X</div>
            <div class="dow-lbl">J</div><div class="dow-lbl">V</div><div class="dow-lbl">S</div>
            <div class="dow-lbl">D</div>
          </div>
          <div class="cal-grid" id="cal-grid"></div>
        </div>
        <div class="slots-wrap">
          <div class="slots-label">Horarios disponibles</div>
          <div class="slots-grid" id="slots-grid">
            <div class="slots-msg">Selecciona un día del calendario</div>
          </div>
        </div>
        <button class="btn-counter" id="btn-send-counter" disabled onclick="sendCounter()">
          ⇄ Enviar propuesta al barbero
        </button>
        <div class="status-msg" id="counter-status"></div>
      </div>

      <div class="pane" id="pane-cancel">
        <div class="warn-box">
          ⚠ Al cancelar se liberará el hueco. Podrás hacer una nueva reserva cuando quieras.
        </div>
        <button class="btn-cancel-all" id="btn-do-cancel" onclick="doCancel()">
          ✕ Sí, cancelar mi cita definitivamente
        </button>
        <div class="status-msg" id="cancel-status"></div>
      </div>
    </div>
  </div>

  <script>
    var TOKEN      = '{$tokenEnc}';
    var BARBERO_ID = '{$barberoId}';
    var BASE_URL   = '{$baseUrl}';
    var CANCEL_API = '{$cancelApiUrl}';

    var ALL_SLOTS = [
      '09:00','09:30','10:00','10:30','11:00','11:30',
      '12:00','12:30','13:00','13:30',
      '16:00','16:30','17:00','17:30','18:00','18:30','19:00','19:30'
    ];
    var MONTHS_ES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                     'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    var calDate      = new Date();
    var selectedDate = null;
    var selectedSlot = null;
    var takenSlots   = [];

    document.addEventListener('DOMContentLoaded', function() { renderCal(); });

    function switchMode(mode) {
      document.querySelectorAll('.mode-tab').forEach(function(t) { t.classList.remove('active'); });
      document.querySelectorAll('.pane').forEach(function(p) { p.classList.remove('active'); });
      document.getElementById('tab-'  + mode).classList.add('active');
      document.getElementById('pane-' + mode).classList.add('active');
    }

    function pad2(n) { return String(n).padStart(2,'0'); }
    function isoDate(y, m, d) { return y + '-' + pad2(m + 1) + '-' + pad2(d); }

    function renderCal() {
      var y = calDate.getFullYear(), m = calDate.getMonth();
      var titleEl = document.getElementById('cal-title');
      if (titleEl) titleEl.textContent = MONTHS_ES[m] + ' ' + y;
      var today    = new Date(); today.setHours(0,0,0,0);
      var tomorrow = new Date(today); tomorrow.setDate(tomorrow.getDate() + 1);
      var firstDay = new Date(y, m, 1).getDay();
      var offset   = (firstDay + 6) % 7;
      var daysIn   = new Date(y, m + 1, 0).getDate();
      var html = '';
      for (var i = 0; i < offset; i++) html += '<div class="cal-cell c-empty"></div>';
      for (var d = 1; d <= daysIn; d++) {
        var dt    = new Date(y, m, d);
        var isSun = (dt.getDay() === 0);
        var isPast= (dt < tomorrow);
        var isTod = (dt.getTime() === today.getTime());
        var iso   = isoDate(y, m, d);
        var isSel = (selectedDate === iso);
        var cls   = 'cal-cell';
        if (isPast || isSun) cls += ' c-dis';
        else if (isTod)      cls += ' c-today';
        if (isSel)           cls += ' c-sel';
        var onclick = (isPast || isSun) ? '' : 'onclick="selectDate(\'' + iso + '\')"';
        html += '<div class="' + cls + '" ' + onclick + '>' + d + '</div>';
      }
      var grid = document.getElementById('cal-grid');
      if (grid) grid.innerHTML = html;
    }

    function calNav(dir) {
      calDate.setMonth(calDate.getMonth() + dir);
      renderCal();
    }

    function selectDate(iso) {
      selectedDate = iso;
      selectedSlot = null;
      document.getElementById('btn-send-counter').disabled = true;
      renderCal();
      loadSlots(iso);
    }

    function loadSlots(fecha) {
      var grid = document.getElementById('slots-grid');
      grid.innerHTML = '<div class="slots-msg">Cargando horarios\u2026</div>';
      fetch(BASE_URL + '/backend/api/slots.php?fecha=' + fecha + '&barbero=' + BARBERO_ID)
        .then(function(r) { return r.json(); })
        .then(function(json) {
          if (json.ok && json.data.bloqueado) {
            grid.innerHTML = '<div class="slots-msg" style="color:#d42b2b;">\uD83D\uDD12 D\u00eda no disponible: ' + (json.data.motivo || '') + '</div>';
            return;
          }
          takenSlots = json.ok ? (json.data.ocupadas || []) : [];
          renderSlots(fecha);
        })
        .catch(function() {
          grid.innerHTML = '<div class="slots-msg" style="color:#d42b2b;">Error al cargar horarios</div>';
        });
    }

    function renderSlots(fecha) {
      var grid   = document.getElementById('slots-grid');
      var now    = new Date();
      var parts  = fecha.split('-');
      var dtF    = new Date(+parts[0], +parts[1] - 1, +parts[2]);
      var isToday= (fecha === isoDate(now.getFullYear(), now.getMonth(), now.getDate()));
      var curHHMM= pad2(now.getHours()) + ':' + pad2(now.getMinutes());
      var esSab  = (dtF.getDay() === 6);
      var slots  = ALL_SLOTS.filter(function(s) { return !esSab || s < '14:00'; });
      if (!slots.length) {
        grid.innerHTML = '<div class="slots-msg">No hay horarios disponibles</div>';
        return;
      }
      grid.innerHTML = slots.map(function(s) {
        var taken = takenSlots.indexOf(s) !== -1;
        var past  = isToday && s <= curHHMM;
        var sel   = (selectedSlot === s);
        var cls   = 'slot';
        if (taken) cls += ' s-taken'; else if (past) cls += ' s-past';
        if (sel)   cls += ' s-sel';
        var disabled = taken || past;
        var onclick  = disabled ? '' : 'onclick="selectSlot(\'' + s + '\')"';
        return '<div class="' + cls + '" ' + onclick + '>' + s + '</div>';
      }).join('');
    }

    function selectSlot(s) {
      selectedSlot = s;
      renderSlots(selectedDate);
      document.getElementById('btn-send-counter').disabled = false;
    }

    function sendCounter() {
      if (!selectedDate || !selectedSlot) return;
      var btn = document.getElementById('btn-send-counter');
      btn.disabled = true; btn.textContent = 'Enviando\u2026';
      fetch(BASE_URL + '/backend/api/reschedule-client-counter.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: decodeURIComponent(TOKEN), nueva_fecha: selectedDate, nueva_hora: selectedSlot })
      })
      .then(function(r) { return r.json(); })
      .then(function(json) {
        showStatus('counter-status', json.ok,
          json.ok ? '\u2713 Propuesta enviada. El barbero te responder\u00e1 por email.'
                  : '\u26A0 ' + (json.error || 'Error al enviar'));
        if (json.ok) {
          btn.textContent = '\u2713 Enviado';
        } else {
          btn.disabled = false;
          btn.textContent = '\u21C4 Enviar propuesta al barbero';
        }
      })
      .catch(function() {
        showStatus('counter-status', false, '\u26A0 Error de conexi\u00F3n. Int\u00E9ntalo de nuevo.');
        btn.disabled = false;
        btn.textContent = '\u21C4 Enviar propuesta al barbero';
      });
    }

    function doCancel() {
      if (!confirm('\u00BFSeguro que quieres cancelar la cita? Esta acci\u00F3n no se puede deshacer.')) return;
      var btn = document.getElementById('btn-do-cancel');
      btn.disabled = true; btn.textContent = 'Cancelando\u2026';
      fetch(CANCEL_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token: decodeURIComponent(TOKEN),
          accion: 'cancelar',
          motivo: 'Cancelaci\u00F3n solicitada por el cliente al rechazar la propuesta de cambio de horario'
        })
      })
      .then(function(r) { return r.json(); })
      .then(function(json) {
        if (json.ok) {
          showStatus('cancel-status', true, '\u2713 Cita cancelada. Recibir\u00E1s un email de confirmaci\u00F3n.');
          btn.textContent = '\u2713 Cancelada';
          setTimeout(function() { window.location.href = BASE_URL; }, 2500);
        } else {
          showStatus('cancel-status', false, '\u26A0 ' + (json.error || 'Error al cancelar'));
          btn.disabled = false;
          btn.textContent = '\u2715 S\u00ED, cancelar mi cita definitivamente';
        }
      })
      .catch(function() {
        showStatus('cancel-status', false, '\u26A0 Error de conexi\u00F3n. Ll\u00E1manos al +34 944 000 000.');
        btn.disabled = false;
        btn.textContent = '\u2715 S\u00ED, cancelar mi cita definitivamente';
      });
    }

    function showStatus(id, ok, msg) {
      var el = document.getElementById(id);
      if (!el) return;
      el.className = 'status-msg ' + (ok ? 'ok' : 'err');
      el.textContent = msg;
    }
  </script>
</body>
</html>
HTML;
  exit;
}

// ── Helpers email ─────────────────────────────────────────────
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
        2026 Prado Barber Co. &mdash; Hecho con precisión en Bilbao</p>
    </div>
  </div>
</body></html>";
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
  body{background:#09080f;color:#f0ece3;font-family:'DM Sans',sans-serif;min-height:100vh;
       display:flex;align-items:center;justify-content:center;padding:2rem;}
  .card{background:#111119;border:1px solid #252530;border-radius:16px;max-width:480px;width:100%;overflow:hidden;text-align:center;}
  .card-header{background:{$c['bg']};padding:2rem;color:{$c['text']};}
  .icon{width:64px;height:64px;border-radius:50%;background:rgba(0,0,0,.15);display:flex;align-items:center;
        justify-content:center;font-size:1.75rem;margin:0 auto 1rem;font-weight:700;}
  .card-header h1{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;}
  .card-body{padding:2rem;}
  .card-body p{color:#c0bcc9;font-size:.95rem;line-height:1.75;margin-bottom:1.5rem;}
  .card-body strong{color:#f0ece3;}
  .btn{display:inline-block;background:#d42b2b;color:#fff;text-decoration:none;
       padding:.75rem 2rem;border-radius:4px;font-size:.75rem;font-weight:600;
       letter-spacing:.15em;text-transform:uppercase;margin:.35rem;}
  .btn-outline{background:transparent;border:1px solid #252530;color:#7a7880;}
  .btn-outline:hover{border-color:#f0ece3;color:#f0ece3;}
  .brand{font-family:'Playfair Display',serif;font-style:italic;font-size:.9rem;color:#7a7880;margin-top:1.5rem;}
</style></head>
<body>
  <div class='card'>
    <div class='card-header'><div class='icon'>{$c['icon']}</div><h1>{$titulo}</h1></div>
    <div class='card-body'>
      <p>{$mensaje}</p>
      <a href='{$baseUrl}/reservas.html' class='btn'>Nueva reserva</a>
      <a href='{$baseUrl}' class='btn btn-outline'>Inicio</a>
      <div class='brand'>Prado Barber Co.</div>
    </div>
  </div>
</body></html>";
  exit;
}

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
  curl_close($ch);
  return $httpCode === 201;
}
