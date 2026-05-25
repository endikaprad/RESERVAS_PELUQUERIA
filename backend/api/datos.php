<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/datos.php
//  Gestión de barberos y servicios desde el panel admin.
//
//  GET  ?tipo=barberos|servicios          → listar (incluye inactivos)
//  POST { accion, ... }                   → crear / editar / desactivar
//
//  Acciones disponibles:
//    barbero_crear  { nombre, especialidad, iniciales }
//    barbero_editar { id, nombre, especialidad, iniciales }
//    barbero_toggle { id }   → activa/desactiva
//
//    servicio_crear  { nombre, duracion, precio }
//    servicio_editar { id, nombre, duracion, precio }
//    servicio_toggle { id }  → activa/desactiva
// ============================================================

require_once __DIR__ . '/../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

function respOk(mixed $data): never {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
function respErr(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Asegurar columnas 'activo' ────────────────────────────────
function ensureActivo(PDO $db): void {
    foreach (['barberos', 'servicios'] as $tabla) {
        try {
            $db->exec("ALTER TABLE {$tabla} ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1");
        } catch (PDOException $e) {
            // Ya existe — ignorar
        }
    }
}

try {
    $db = getDB();
    ensureActivo($db);

    // ══════════ GET ════════════════════════════════════════════
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $tipo = $_GET['tipo'] ?? '';

        if ($tipo === 'barberos') {
            $rows = $db->query(
                "SELECT id, nombre, especialidad, iniciales, activo
                 FROM barberos ORDER BY nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            respOk($rows);
        }

        if ($tipo === 'servicios') {
            $rows = $db->query(
                "SELECT id, nombre, duracion, precio, activo
                 FROM servicios ORDER BY precio ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) $r['precio'] = (float)$r['precio'];
            respOk($rows);
        }

        respErr('Parámetro tipo inválido. Usa: barberos | servicios');
    }

    // ══════════ POST ═══════════════════════════════════════════
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = trim($body['accion'] ?? '');

    // ── BARBEROS ─────────────────────────────────────────────

    if ($accion === 'barbero_crear') {
        $nombre      = trim($body['nombre']      ?? '');
        $especialidad= trim($body['especialidad']?? '');
        $iniciales   = strtoupper(trim($body['iniciales'] ?? ''));

        if (!$nombre || !$iniciales) respErr('nombre e iniciales son obligatorios');
        if (strlen($iniciales) > 5)  respErr('iniciales máximo 5 caracteres');

        // ID: slug del nombre en minúsculas
        $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombre));
        if (!$id) respErr('Nombre no válido para generar ID');

        $stmt = $db->prepare(
            "INSERT INTO barberos (id, nombre, especialidad, iniciales, activo)
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute([$id, $nombre, $especialidad, $iniciales]);
        respOk(['id' => $id, 'nombre' => $nombre]);
    }

    if ($accion === 'barbero_editar') {
        $id          = trim($body['id']           ?? '');
        $nombre      = trim($body['nombre']       ?? '');
        $especialidad= trim($body['especialidad'] ?? '');
        $iniciales   = strtoupper(trim($body['iniciales'] ?? ''));

        if (!$id || !$nombre || !$iniciales) respErr('id, nombre e iniciales son obligatorios');

        $stmt = $db->prepare(
            "UPDATE barberos SET nombre=?, especialidad=?, iniciales=? WHERE id=?"
        );
        $stmt->execute([$nombre, $especialidad, $iniciales, $id]);
        respOk(['id' => $id]);
    }

    if ($accion === 'barbero_toggle') {
        $id = trim($body['id'] ?? '');
        if (!$id) respErr('id es obligatorio');

        $stmt = $db->prepare(
            "UPDATE barberos SET activo = 1 - activo WHERE id = ?"
        );
        $stmt->execute([$id]);

        $row = $db->prepare("SELECT activo FROM barberos WHERE id = ?");
        $row->execute([$id]);
        $activo = (int)$row->fetchColumn();
        respOk(['id' => $id, 'activo' => $activo]);
    }

    // ── SERVICIOS ────────────────────────────────────────────

    if ($accion === 'servicio_crear') {
        $nombre  = trim($body['nombre']  ?? '');
        $duracion= trim($body['duracion']?? '');
        $precio  = (float)($body['precio'] ?? 0);

        if (!$nombre || !$duracion || $precio <= 0) respErr('nombre, duracion y precio son obligatorios');

        // ID: slug
        $id = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace(' ', '-', $nombre)));
        // Evitar duplicado de ID
        $exists = $db->prepare("SELECT COUNT(*) FROM servicios WHERE id = ?");
        $exists->execute([$id]);
        if ((int)$exists->fetchColumn() > 0) $id .= '-' . time();

        $stmt = $db->prepare(
            "INSERT INTO servicios (id, nombre, duracion, precio, activo)
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute([$id, $nombre, $duracion, $precio]);
        respOk(['id' => $id, 'nombre' => $nombre]);
    }

    if ($accion === 'servicio_editar') {
        $id      = trim($body['id']      ?? '');
        $nombre  = trim($body['nombre']  ?? '');
        $duracion= trim($body['duracion']?? '');
        $precio  = (float)($body['precio'] ?? 0);

        if (!$id || !$nombre || !$duracion || $precio <= 0)
            respErr('id, nombre, duracion y precio son obligatorios');

        $stmt = $db->prepare(
            "UPDATE servicios SET nombre=?, duracion=?, precio=? WHERE id=?"
        );
        $stmt->execute([$nombre, $duracion, $precio, $id]);
        respOk(['id' => $id]);
    }

    if ($accion === 'servicio_toggle') {
        $id = trim($body['id'] ?? '');
        if (!$id) respErr('id es obligatorio');

        $stmt = $db->prepare(
            "UPDATE servicios SET activo = 1 - activo WHERE id = ?"
        );
        $stmt->execute([$id]);

        $row = $db->prepare("SELECT activo FROM servicios WHERE id = ?");
        $row->execute([$id]);
        $activo = (int)$row->fetchColumn();
        respOk(['id' => $id, 'activo' => $activo]);
    }

    respErr('Acción no reconocida: ' . $accion);

} catch (PDOException $e) {
    respErr('Error de base de datos: ' . $e->getMessage(), 500);
}