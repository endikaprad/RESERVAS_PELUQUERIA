<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/blocked-slots.php
//
//  Gestiona horarios bloqueados para días concretos.
//  Un slot bloqueado aparece como ocupado para todos los barberos.
//
//  GET  ?year=2026&month=6          → mapa de slots bloqueados del mes
//  POST { accion, ... }             → bloquear / desbloquear slots
//
//  Acciones:
//    bloquear_slot    { fecha, hora }           → bloquea 1 slot
//    bloquear_slots   { fecha, horas: [] }      → bloquea varios slots
//    desbloquear_slot { fecha, hora }           → desbloquea 1 slot
//    listar_fecha     { fecha }                 → slots bloqueados de un día
// ============================================================

require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function slotOk(mixed $data): never
{
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function slotErr(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDB();

    // Crear tabla si no existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS slots_bloqueados (
            id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            fecha     DATE         NOT NULL,
            hora      TIME         NOT NULL,
            motivo    VARCHAR(200) NOT NULL DEFAULT 'No disponible',
            creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fecha_hora (fecha, hora)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ══ GET ════════════════════════════════════════════════════
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Listar slots de un día concreto
        if (isset($_GET['fecha'])) {
            $fecha = trim($_GET['fecha']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) slotErr('Fecha inválida');

            $stmt = $db->prepare(
                "SELECT TIME_FORMAT(hora, '%H:%i') AS hora, motivo
                 FROM slots_bloqueados
                 WHERE fecha = ?
                 ORDER BY hora ASC"
            );
            $stmt->execute([$fecha]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            slotOk($rows);
        }

        // Listar slots de un mes completo
        $year  = (int)($_GET['year']  ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));

        if ($year < 2020 || $year > 2100 || $month < 1 || $month > 12) {
            slotErr('Parámetros inválidos');
        }

        $desde = sprintf('%04d-%02d-01', $year, $month);
        $hasta = date('Y-m-t', strtotime($desde));

        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(fecha, '%Y-%m-%d') AS fecha,
                    TIME_FORMAT(hora, '%H:%i') AS hora,
                    motivo
             FROM slots_bloqueados
             WHERE fecha BETWEEN ? AND ?
             ORDER BY fecha ASC, hora ASC"
        );
        $stmt->execute([$desde, $hasta]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar por fecha: { "2026-06-10": ["09:00","09:30"], ... }
        $map = [];
        foreach ($rows as $r) {
            $map[$r['fecha']][] = ['hora' => $r['hora'], 'motivo' => $r['motivo']];
        }

        slotOk($map);
    }

    // ══ POST ═══════════════════════════════════════════════════
    $raw    = file_get_contents('php://input');
    $body   = json_decode($raw, true) ?? [];
    $accion = trim($body['accion'] ?? '');

    // ── Bloquear un slot ───────────────────────────────────────
    if ($accion === 'bloquear_slot') {
        $fecha  = trim($body['fecha']  ?? '');
        $hora   = trim($body['hora']   ?? '');
        $motivo = trim($body['motivo'] ?? 'No disponible');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) slotErr('Fecha inválida');
        if (!preg_match('/^\d{2}:\d{2}$/', $hora))        slotErr('Hora inválida');

        $db->prepare(
            "INSERT INTO slots_bloqueados (fecha, hora, motivo)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE motivo = VALUES(motivo)"
        )->execute([$fecha, $hora . ':00', $motivo]);

        slotOk(['fecha' => $fecha, 'hora' => $hora, 'motivo' => $motivo]);
    }

    // ── Bloquear varios slots a la vez ─────────────────────────
    if ($accion === 'bloquear_slots') {
        $fecha  = trim($body['fecha']  ?? '');
        $horas  = $body['horas']  ?? [];
        $motivo = trim($body['motivo'] ?? 'No disponible');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) slotErr('Fecha inválida');
        if (!is_array($horas) || empty($horas))            slotErr('Horas requeridas');

        $stmt = $db->prepare(
            "INSERT INTO slots_bloqueados (fecha, hora, motivo)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE motivo = VALUES(motivo)"
        );

        foreach ($horas as $h) {
            if (preg_match('/^\d{2}:\d{2}$/', $h)) {
                $stmt->execute([$fecha, $h . ':00', $motivo]);
            }
        }

        slotOk(['fecha' => $fecha, 'count' => count($horas)]);
    }

    // ── Desbloquear un slot ────────────────────────────────────
    if ($accion === 'desbloquear_slot') {
        $fecha = trim($body['fecha'] ?? '');
        $hora  = trim($body['hora']  ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) slotErr('Fecha inválida');
        if (!preg_match('/^\d{2}:\d{2}$/', $hora))        slotErr('Hora inválida');

        $db->prepare(
            "DELETE FROM slots_bloqueados WHERE fecha = ? AND hora = ?"
        )->execute([$fecha, $hora . ':00']);

        slotOk(['fecha' => $fecha, 'hora' => $hora]);
    }

    // ── Desbloquear todos los slots de un día ──────────────────
    if ($accion === 'desbloquear_dia') {
        $fecha = trim($body['fecha'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) slotErr('Fecha inválida');

        $db->prepare("DELETE FROM slots_bloqueados WHERE fecha = ?")
            ->execute([$fecha]);

        slotOk(['fecha' => $fecha]);
    }

    slotErr('Acción no reconocida: ' . $accion);
} catch (PDOException $e) {
    slotErr('Error de base de datos: ' . $e->getMessage(), 500);
}
