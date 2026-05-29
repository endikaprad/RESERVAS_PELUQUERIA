<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/reminder-status.php
//
//  GET → devuelve estado de recordatorios del panel admin:
//    - Reservas de mañana y si tienen recordatorio enviado
//    - Últimos 20 registros del log de envíos
//    - Estadísticas de la semana
// ============================================================

require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function rsOk(mixed $data): never
{
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function rsErr(string $msg, int $code = 500): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDB();
    $tz = new DateTimeZone('Europe/Madrid');

    $hoy   = (new DateTime('now', $tz))->format('Y-m-d');
    $manana = (new DateTime('now', $tz))->modify('+1 day')->format('Y-m-d');

    // ── Asegurar que la columna existe ────────────────────────
    try {
        $db->exec("ALTER TABLE reservas ADD COLUMN recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0");
    } catch (PDOException $e) { /* ya existe */
    }

    // ── Reservas de mañana ────────────────────────────────────
    $stmt = $db->prepare("
        SELECT
            r.id,
            r.fecha,
            TIME_FORMAT(r.hora, '%H:%i') AS hora,
            r.cliente_nombre,
            r.cliente_email,
            r.estado,
            r.recordatorio_enviado,
            s.nombre AS servicio,
            b.nombre AS barbero
        FROM reservas r
        JOIN servicios s ON s.id = r.servicio_id
        JOIN barberos  b ON b.id = r.barbero_id
        WHERE r.fecha = ? AND r.estado = 'aceptada'
        ORDER BY r.hora ASC
    ");
    $stmt->execute([$manana]);
    $mananaReservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Log de envíos recientes (últimos 30) ──────────────────
    $logRecientes = [];
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS reminder_log (
                id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                reserva_id      INT UNSIGNED NOT NULL,
                enviado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resultado       ENUM('ok','error') NOT NULL DEFAULT 'ok',
                detalle         VARCHAR(300) NOT NULL DEFAULT '',
                cliente_nombre  VARCHAR(120) NOT NULL DEFAULT '',
                fecha_cita      DATE         DEFAULT NULL,
                hora_cita       TIME         DEFAULT NULL,
                INDEX idx_reserva (reserva_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        foreach ([
            "ALTER TABLE reminder_log ADD COLUMN cliente_nombre VARCHAR(120) NOT NULL DEFAULT ''",
            "ALTER TABLE reminder_log ADD COLUMN fecha_cita DATE DEFAULT NULL",
            "ALTER TABLE reminder_log ADD COLUMN hora_cita TIME DEFAULT NULL",
        ] as $mig) {
            try { $db->exec($mig); } catch (PDOException $e) { /* ya existe */ }
        }

        $logStmt = $db->query("
            SELECT rl.id, rl.reserva_id, rl.enviado_en, rl.resultado, rl.detalle,
                   COALESCE(r.cliente_nombre, rl.cliente_nombre) AS cliente_nombre,
                   r.cliente_email,
                   COALESCE(DATE_FORMAT(r.fecha,'%Y-%m-%d'), DATE_FORMAT(rl.fecha_cita,'%Y-%m-%d')) AS fecha_cita,
                   COALESCE(TIME_FORMAT(r.hora,'%H:%i'), TIME_FORMAT(rl.hora_cita,'%H:%i'))          AS hora_cita
            FROM reminder_log rl
            LEFT JOIN reservas r ON r.id = rl.reserva_id
            WHERE COALESCE(r.cliente_nombre, rl.cliente_nombre) != ''
            ORDER BY rl.enviado_en DESC
            LIMIT 30
        ");
        $logRecientes = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $logRecientes = [];
    }

    // ── Estadísticas: últimos 7 días ──────────────────────────
    $stats = ['total_enviados' => 0, 'total_errores' => 0, 'pendientes_manana' => 0];
    try {
        $hace7 = (new DateTime('now', $tz))->modify('-7 days')->format('Y-m-d H:i:s');
        $s = $db->prepare("
            SELECT resultado, COUNT(DISTINCT reserva_id) AS cnt
            FROM reminder_log
            WHERE enviado_en >= ?
            GROUP BY resultado
        ");
        $s->execute([$hace7]);
        foreach ($s->fetchAll() as $row) {
            if ($row['resultado'] === 'ok')    $stats['total_enviados'] = (int)$row['cnt'];
            if ($row['resultado'] === 'error') $stats['total_errores']  = (int)$row['cnt'];
        }
    } catch (PDOException $e) { /* tabla no existe aún */
    }

    $stats['pendientes_manana'] = count(array_filter(
        $mananaReservas,
        fn($r) => $r['recordatorio_enviado'] == 0
    ));

    // ── URL del cron / webhook ────────────────────────────────
    $baseUrl     = 'https://pradopeluqueria.infinityfree.me';
    $secretToken = defined('REMINDER_SECRET') ? REMINDER_SECRET : 'prado-reminder-2026';
    $cronUrl     = $baseUrl . '/backend/api/reminder.php?secret=' . $secretToken;

    rsOk([
        'manana'         => $manana,
        'hoy'            => $hoy,
        'reservas_manana' => $mananaReservas,
        'log_recientes'  => $logRecientes,
        'stats'          => $stats,
        'cron_url'       => $cronUrl,
        'cron_cmd'       => '0 9 * * * curl -s "' . $cronUrl . '" > /dev/null',
    ]);
} catch (PDOException $e) {
    rsErr('Error de base de datos: ' . $e->getMessage());
}
