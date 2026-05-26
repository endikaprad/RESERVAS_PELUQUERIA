<?php
// ============================================================
//  GET /api/slots.php?fecha=YYYY-MM-DD&barbero=endika
//
//  FIX #4: Los slots propuestos por el barbero en una negociación
//  (estado 'reprogramar_barbero') también se marcan como ocupados,
//  para que nadie pueda reservar ese horario mientras está pendiente.
// ============================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

function slots_ok($data) {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function slots_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config.php';

$fecha   = trim($_GET['fecha']   ?? '');
$barbero = trim($_GET['barbero'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    slots_err('Parametro fecha invalido. Formato esperado: YYYY-MM-DD');
}
if ($barbero === '') {
    slots_err('Parametro barbero requerido');
}

try {
    $db = getDB();

    // ¿Dia bloqueado por vacaciones?
    try {
        $bloqStmt = $db->prepare('SELECT motivo FROM dias_bloqueados WHERE fecha = ? LIMIT 1');
        $bloqStmt->execute([$fecha]);
        $bloqueado = $bloqStmt->fetch();
    } catch (Exception $e) {
        $bloqueado = false;
    }

    if ($bloqueado) {
        slots_ok([
            'ocupadas'  => [],
            'bloqueado' => true,
            'motivo'    => $bloqueado['motivo'],
            'fecha'     => $fecha,
            'barbero'   => $barbero,
        ]);
    }

    // ── Slots ocupados por reservas normales ──────────────────
    // Estados que bloquean: pendiente, aceptada, denegada
    // (cancelada libera el slot)
    $stmt = $db->prepare(
        "SELECT TIME_FORMAT(hora, '%H:%i') AS hora
         FROM reservas
         WHERE barbero_id = ?
           AND fecha      = ?
           AND estado     IN ('pendiente', 'aceptada', 'denegada')"
    );
    $stmt->execute([$barbero, $fecha]);
    $ocupadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // ── FIX #4: Slots propuestos en negociación activa ─────────
    // Si el barbero propuso un horario nuevo (reprogramar_barbero),
    // ese slot de la propuesta también debe bloquearse para nuevas reservas.
    // Usamos nueva_fecha_propuesta y nueva_hora_propuesta.
    $stmtProp = $db->prepare(
        "SELECT TIME_FORMAT(nueva_hora_propuesta, '%H:%i') AS hora
         FROM reservas
         WHERE barbero_id              = ?
           AND nueva_fecha_propuesta   = ?
           AND nueva_hora_propuesta    IS NOT NULL
           AND estado                  IN ('reprogramar_barbero', 'reprogramar_cliente')"
    );
    $stmtProp->execute([$barbero, $fecha]);
    $ocupadasPropuesta = $stmtProp->fetchAll(PDO::FETCH_COLUMN);

    // Combinar y deduplicar
    $todasOcupadas = array_values(
        array_unique(
            array_merge($ocupadas, $ocupadasPropuesta)
        )
    );

    slots_ok([
        'ocupadas'  => $todasOcupadas,
        'bloqueado' => false,
        'motivo'    => null,
        'fecha'     => $fecha,
        'barbero'   => $barbero,
    ]);

} catch (PDOException $e) {
    slots_err('Error de base de datos: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    slots_err('Error: ' . $e->getMessage(), 500);
}