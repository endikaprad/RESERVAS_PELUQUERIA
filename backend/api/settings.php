<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/settings.php
//  GET  → devuelve configuración actual
//  POST → guarda configuración
// ============================================================

require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function jsonOkS(mixed $data): never {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE); exit;
}
function jsonErrorS(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE); exit;
}

try {
    $db = getDB();

    // Crear tabla si no existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS configuracion (
            clave   VARCHAR(60)  NOT NULL PRIMARY KEY,
            valor   TEXT         NOT NULL,
            updated DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Crear tabla días bloqueados si no existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS dias_bloqueados (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            fecha       DATE         NOT NULL,
            motivo      VARCHAR(200) NOT NULL DEFAULT 'Vacaciones',
            creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_fecha (fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // Leer configuración
        $rows = $db->query("SELECT clave, valor FROM configuracion")->fetchAll();
        $cfg  = [];
        foreach ($rows as $r) $cfg[$r['clave']] = $r['valor'];

        $autoAceptar      = $cfg['auto_aceptar']       ?? 'no';
        $autoAceptarHasta = $cfg['auto_aceptar_hasta'] ?? '';

        // ── FIX: si la fecha límite ya pasó, resetear a 'no' en BD y en respuesta ──
        if ($autoAceptar !== 'no' && $autoAceptarHasta !== '' && $autoAceptarHasta !== '9999-12-31') {
            $hoy = (new DateTime('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d');
            if ($hoy > $autoAceptarHasta) {
                // Caducado — persistir el reset en BD para que no haya que recalcular cada vez
                $db->prepare("INSERT INTO configuracion (clave,valor) VALUES ('auto_aceptar','no')
                              ON DUPLICATE KEY UPDATE valor='no'")->execute();
                $db->prepare("INSERT INTO configuracion (clave,valor) VALUES ('auto_aceptar_hasta','')
                              ON DUPLICATE KEY UPDATE valor=''")->execute();
                $autoAceptar      = 'no';
                $autoAceptarHasta = '';
            }
        }

        // Leer días bloqueados
        $dias = $db->query("SELECT fecha, motivo FROM dias_bloqueados ORDER BY fecha ASC")->fetchAll();

        jsonOkS([
            'auto_aceptar'       => $autoAceptar,
            'auto_aceptar_hasta' => $autoAceptarHasta,
            'dias_bloqueados'    => $dias,
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $raw  = file_get_contents('php://input');
        $body = json_decode($raw, true) ?? [];
        $accion = $body['accion'] ?? '';

        // ── Guardar auto-aceptar ──────────────────────────────
        if ($accion === 'auto_aceptar') {
            $valor = $body['valor'] ?? 'no';
            if (!in_array($valor, ['no','hoy','semana','mes','siempre'], true))
                jsonErrorS('Valor inválido');

            $hasta = '';
            $hoy   = new DateTime('now', new DateTimeZone('Europe/Madrid'));
            switch ($valor) {
                case 'hoy':    $hasta = $hoy->format('Y-m-d'); break;
                case 'semana': $hasta = (clone $hoy)->modify('+7 days')->format('Y-m-d'); break;
                case 'mes':    $hasta = (clone $hoy)->modify('+1 month')->format('Y-m-d'); break;
                case 'siempre':$hasta = '9999-12-31'; break;
            }

            $db->prepare("INSERT INTO configuracion (clave,valor) VALUES ('auto_aceptar',?)
                          ON DUPLICATE KEY UPDATE valor=VALUES(valor)")
               ->execute([$valor]);
            $db->prepare("INSERT INTO configuracion (clave,valor) VALUES ('auto_aceptar_hasta',?)
                          ON DUPLICATE KEY UPDATE valor=VALUES(valor)")
               ->execute([$hasta]);

            jsonOkS(['auto_aceptar' => $valor, 'auto_aceptar_hasta' => $hasta]);
        }

        // ── Añadir día bloqueado ──────────────────────────────
        if ($accion === 'bloquear_dia') {
            $fecha  = trim($body['fecha']  ?? '');
            $motivo = trim($body['motivo'] ?? 'Vacaciones');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) jsonErrorS('Fecha inválida');
            $db->prepare("INSERT IGNORE INTO dias_bloqueados (fecha, motivo) VALUES (?,?)")
               ->execute([$fecha, $motivo]);
            jsonOkS(['fecha' => $fecha]);
        }

        // ── Añadir rango de días ──────────────────────────────
        if ($accion === 'bloquear_rango') {
            $desde  = trim($body['desde']  ?? '');
            $hasta  = trim($body['hasta']  ?? '');
            $motivo = trim($body['motivo'] ?? 'Vacaciones');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) ||
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta))
                jsonErrorS('Fechas inválidas');

            $d = new DateTime($desde);
            $h = new DateTime($hasta);
            if ($d > $h) jsonErrorS('La fecha de inicio debe ser anterior al final');

            $stmt = $db->prepare("INSERT IGNORE INTO dias_bloqueados (fecha, motivo) VALUES (?,?)");
            $cur  = clone $d;
            while ($cur <= $h) {
                $stmt->execute([$cur->format('Y-m-d'), $motivo]);
                $cur->modify('+1 day');
            }
            jsonOkS(['desde' => $desde, 'hasta' => $hasta]);
        }

        // ── Desbloquear día ───────────────────────────────────
        if ($accion === 'desbloquear_dia') {
            $fecha = trim($body['fecha'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) jsonErrorS('Fecha inválida');
            $db->prepare("DELETE FROM dias_bloqueados WHERE fecha = ?")
               ->execute([$fecha]);
            jsonOkS(['fecha' => $fecha]);
        }

        jsonErrorS('Acción no reconocida');
    }

} catch (PDOException $e) {
    jsonErrorS('Error de base de datos: ' . $e->getMessage(), 500);
}