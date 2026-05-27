<?php
// ============================================================
//  GET /api/slots.php?fecha=YYYY-MM-DD&barbero=endika
//
//  Devuelve slots ocupados para un barbero en una fecha dada.
//  ESTADOS QUE BLOQUEAN EL SLOT:
//    - pendiente, aceptada         → cita activa
//    - reprogramar_barbero/cliente → negociación en curso
//  ESTADOS QUE LIBERAN EL SLOT:
//    - cancelada, denegada         → slot libre para nuevas reservas
// ============================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function slots_ok(array $data): never
{
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function slots_err(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config.php';

$fecha   = trim($_GET['fecha']   ?? '');
$barbero = trim($_GET['barbero'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    slots_err('Parámetro fecha inválido. Formato esperado: YYYY-MM-DD');
}
if ($barbero === '') {
    slots_err('Parámetro barbero requerido');
}

try {
    $db = getDB();

    // ── ¿Día bloqueado por vacaciones? ───────────────────────
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

    // ── 1. Slots de reservas activas (excluye cancelada y denegada) ──
    $stmtNormal = $db->prepare(
        "SELECT TIME_FORMAT(hora, '%H:%i') AS hora
         FROM reservas
         WHERE barbero_id = ?
           AND fecha      = ?
           AND estado     IN ('pendiente', 'aceptada')"
    );
    $stmtNormal->execute([$barbero, $fecha]);
    $ocupadasNormal = $stmtNormal->fetchAll(PDO::FETCH_COLUMN);

    // ── 2. Slots ORIGINALES de reservas en negociación ───────
    // El slot original sigue "tomado" mientras la negociación esté activa
    $stmtNegOrig = $db->prepare(
        "SELECT TIME_FORMAT(hora, '%H:%i') AS hora
         FROM reservas
         WHERE barbero_id = ?
           AND fecha      = ?
           AND estado     IN ('reprogramar_barbero', 'reprogramar_cliente')"
    );
    $stmtNegOrig->execute([$barbero, $fecha]);
    $ocupadasNegOrig = $stmtNegOrig->fetchAll(PDO::FETCH_COLUMN);

    // ── 3. Slots PROPUESTOS de reservas en negociación ───────
    // La hora propuesta también se bloquea mientras espera confirmación
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

    // ── Combinar y deduplicar ────────────────────────────────
    // NOTA: 'cancelada' y 'denegada' NO están en ninguna de estas
    // consultas, por lo que sus slots quedan libres correctamente.
    $todasOcupadas = array_values(
        array_unique(
            array_merge($ocupadasNormal, $ocupadasNegOrig, $ocupadasPropuesta)
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