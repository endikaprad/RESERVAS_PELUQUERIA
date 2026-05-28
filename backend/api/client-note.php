<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/client-note.php
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

function notaOk(mixed $data): never {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function notaErr(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') notaErr('Método no permitido', 405);

$raw      = file_get_contents('php://input');
$body     = json_decode($raw, true) ?? [];
$telefono = trim($body['telefono'] ?? '');
$nota     = trim($body['nota']     ?? '');

if (!$telefono) notaErr('Teléfono requerido');

try {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS notas_cliente (
        id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        telefono  VARCHAR(60)  NOT NULL,
        nota      TEXT         NOT NULL,
        updated   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_telefono (telefono)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Normalizar teléfono: quitar espacios y guiones
    $telefonoLimpio = preg_replace('/[\s\-]/', '', $telefono);

    if ($nota === '') {
        // Borrar la nota si está vacía
        $db->prepare("DELETE FROM notas_cliente WHERE telefono = ?")
           ->execute([$telefonoLimpio]);
    } else {
        $db->prepare(
            "INSERT INTO notas_cliente (telefono, nota)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE nota = VALUES(nota), updated = NOW()"
        )->execute([$telefonoLimpio, $nota]);
    }

    notaOk(['telefono' => $telefonoLimpio, 'nota' => $nota]);

} catch (PDOException $e) {
    notaErr('Error de base de datos: ' . $e->getMessage(), 500);
}