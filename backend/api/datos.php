<?php
// ============================================================
//  PRADO BARBER CO. — /backend/api/datos.php
//  Gestión de barberos y servicios desde el panel admin.
//
//  GET  ?tipo=barberos|servicios          → listar (incluye inactivos)
//  POST { accion, ... }                   → crear / editar / toggle / eliminar
//
//  Acciones disponibles:
//    barbero_crear   { nombre, especialidad, iniciales }
//    barbero_editar  { id, nombre, especialidad, iniciales }
//    barbero_toggle  { id }   → activa/desactiva
//    barbero_eliminar{ id }   → elimina (solo si no tiene reservas)
//
//    servicio_crear   { nombre, duracion, precio }
//    servicio_editar  { id, nombre, duracion, precio }
//    servicio_toggle  { id }  → activa/desactiva
//    servicio_eliminar{ id }  → elimina (solo si no tiene reservas)
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

const CATEGORIAS_VALIDAS = ['cortes', 'barba', 'packs'];

function ensureColumns(PDO $db): void {
    $migraciones = [
        "ALTER TABLE barberos  ADD COLUMN activo      TINYINT(1) NOT NULL DEFAULT 1   AFTER iniciales",
        "ALTER TABLE barberos  ADD COLUMN orden       SMALLINT   NOT NULL DEFAULT 0   AFTER activo",
        "ALTER TABLE barberos  ADD COLUMN bio         TEXT       NULL",
        "ALTER TABLE barberos  ADD COLUMN habilidades TEXT       NULL",
        "ALTER TABLE servicios ADD COLUMN activo      TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE servicios ADD COLUMN categoria   VARCHAR(30) NOT NULL DEFAULT 'cortes'",
        "ALTER TABLE servicios ADD COLUMN orden       SMALLINT   NOT NULL DEFAULT 0",
    ];
    foreach ($migraciones as $sql) {
        try { $db->exec($sql); } catch (PDOException $e) { /* ya existe */ }
    }
}

try {
    $db = getDB();
    ensureColumns($db);

    // ══════════ GET ════════════════════════════════════════════
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $tipo = $_GET['tipo'] ?? '';

        if ($tipo === 'barberos') {
            $rows = $db->query(
                "SELECT id, nombre, especialidad, iniciales, activo, orden, bio, habilidades
                 FROM barberos ORDER BY orden ASC, nombre ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
            respOk($rows);
        }

        if ($tipo === 'servicios') {
            $rows = $db->query(
                "SELECT id, nombre, duracion, precio, activo, categoria, orden
                 FROM servicios ORDER BY categoria ASC, orden ASC, precio ASC"
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
        $nombre       = trim($body['nombre']       ?? '');
        $especialidad = trim($body['especialidad'] ?? '');
        $iniciales    = strtoupper(trim($body['iniciales'] ?? ''));
        $bio          = trim($body['bio']          ?? '') ?: null;
        $habilidades  = trim($body['habilidades']  ?? '') ?: null;

        if (!$nombre || !$iniciales) respErr('nombre e iniciales son obligatorios');
        if (strlen($iniciales) > 5)  respErr('iniciales máximo 5 caracteres');

        $id = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombre));
        if (!$id) respErr('Nombre no válido para generar ID');

        $exists = $db->prepare("SELECT COUNT(*) FROM barberos WHERE id = ?");
        $exists->execute([$id]);
        if ((int)$exists->fetchColumn() > 0) $id .= '-' . substr(time(), -4);

        $maxOrden = (int)$db->query("SELECT COALESCE(MAX(orden),0) FROM barberos")->fetchColumn();

        $stmt = $db->prepare(
            "INSERT INTO barberos (id, nombre, especialidad, iniciales, activo, orden, bio, habilidades)
             VALUES (?, ?, ?, ?, 1, ?, ?, ?)"
        );
        $stmt->execute([$id, $nombre, $especialidad, $iniciales, $maxOrden + 1, $bio, $habilidades]);
        respOk(['id' => $id, 'nombre' => $nombre]);
    }

    if ($accion === 'barbero_editar') {
        $id           = trim($body['id']           ?? '');
        $nombre       = trim($body['nombre']       ?? '');
        $especialidad = trim($body['especialidad'] ?? '');
        $iniciales    = strtoupper(trim($body['iniciales'] ?? ''));
        $bio          = trim($body['bio']          ?? '') ?: null;
        $habilidades  = trim($body['habilidades']  ?? '') ?: null;

        if (!$id || !$nombre || !$iniciales) respErr('id, nombre e iniciales son obligatorios');

        $stmt = $db->prepare(
            "UPDATE barberos SET nombre=?, especialidad=?, iniciales=?, bio=?, habilidades=? WHERE id=?"
        );
        $stmt->execute([$nombre, $especialidad, $iniciales, $bio, $habilidades, $id]);
        respOk(['id' => $id]);
    }

    if ($accion === 'barbero_toggle') {
        $id = trim($body['id'] ?? '');
        if (!$id) respErr('id es obligatorio');

        $stmt = $db->prepare("UPDATE barberos SET activo = 1 - activo WHERE id = ?");
        $stmt->execute([$id]);

        $row = $db->prepare("SELECT activo FROM barberos WHERE id = ?");
        $row->execute([$id]);
        respOk(['id' => $id, 'activo' => (int)$row->fetchColumn()]);
    }

    if ($accion === 'barbero_eliminar') {
        $id = trim($body['id'] ?? '');
        if (!$id) respErr('id es obligatorio');

        $check = $db->prepare("SELECT COUNT(*) FROM reservas WHERE barbero_id = ?");
        $check->execute([$id]);
        if ((int)$check->fetchColumn() > 0)
            respErr('No se puede eliminar: este barbero tiene reservas registradas. Desactívalo en su lugar.');

        $stmt = $db->prepare("DELETE FROM barberos WHERE id = ?");
        $stmt->execute([$id]);
        respOk(['id' => $id, 'eliminado' => true]);
    }

    if ($accion === 'barbero_reordenar') {
        $ids = $body['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) respErr('ids requerido');

        $upd = $db->prepare("UPDATE barberos SET orden=? WHERE id=?");
        foreach ($ids as $i => $id) {
            $upd->execute([$i, trim($id)]);
        }
        respOk(['reordenados' => count($ids)]);
    }

    // ── SERVICIOS ────────────────────────────────────────────

    if ($accion === 'servicio_crear') {
        $nombre    = trim($body['nombre']    ?? '');
        $duracion  = trim($body['duracion']  ?? '');
        $precio    = (float)($body['precio'] ?? 0);
        $categoria = trim($body['categoria'] ?? 'cortes');

        if (!$nombre || !$duracion || $precio <= 0) respErr('nombre, duracion y precio son obligatorios');
        if (!in_array($categoria, CATEGORIAS_VALIDAS)) respErr('categoría no válida');

        $id = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', str_replace(' ', '-', $nombre)));
        $exists = $db->prepare("SELECT COUNT(*) FROM servicios WHERE id = ?");
        $exists->execute([$id]);
        if ((int)$exists->fetchColumn() > 0) $id .= '-' . substr(time(), -4);

        $maxStmt = $db->prepare("SELECT COALESCE(MAX(orden),0) FROM servicios WHERE categoria=?");
        $maxStmt->execute([$categoria]);
        $maxOrden = (int)$maxStmt->fetchColumn();

        $stmt = $db->prepare(
            "INSERT INTO servicios (id, nombre, duracion, precio, activo, categoria, orden)
             VALUES (?, ?, ?, ?, 1, ?, ?)"
        );
        $stmt->execute([$id, $nombre, $duracion, $precio, $categoria, $maxOrden + 1]);
        respOk(['id' => $id, 'nombre' => $nombre]);
    }

    if ($accion === 'servicio_editar') {
        $id        = trim($body['id']        ?? '');
        $nombre    = trim($body['nombre']    ?? '');
        $duracion  = trim($body['duracion']  ?? '');
        $precio    = (float)($body['precio'] ?? 0);
        $categoria = trim($body['categoria'] ?? 'cortes');

        if (!$id || !$nombre || !$duracion || $precio <= 0)
            respErr('id, nombre, duracion y precio son obligatorios');
        if (!in_array($categoria, CATEGORIAS_VALIDAS)) respErr('categoría no válida');

        $stmt = $db->prepare(
            "UPDATE servicios SET nombre=?, duracion=?, precio=?, categoria=? WHERE id=?"
        );
        $stmt->execute([$nombre, $duracion, $precio, $categoria, $id]);
        respOk(['id' => $id]);
    }

    if ($accion === 'servicio_toggle') {
        $id = trim($body['id'] ?? '');
        if (!$id) respErr('id es obligatorio');

        $stmt = $db->prepare("UPDATE servicios SET activo = 1 - activo WHERE id = ?");
        $stmt->execute([$id]);

        $row = $db->prepare("SELECT activo FROM servicios WHERE id = ?");
        $row->execute([$id]);
        respOk(['id' => $id, 'activo' => (int)$row->fetchColumn()]);
    }

    if ($accion === 'servicio_eliminar') {
        $id     = trim($body['id'] ?? '');
        $forzar = !empty($body['forzar']);
        if (!$id) respErr('id es obligatorio');

        $check = $db->prepare("SELECT COUNT(*) FROM reservas WHERE servicio_id = ?");
        $check->execute([$id]);
        $numReservas = (int)$check->fetchColumn();

        if ($numReservas > 0 && !$forzar) {
            echo json_encode(['ok' => false, 'reservas' => $numReservas, 'confirmar' => true]);
            exit;
        }

        if ($numReservas > 0 && $forzar) {
            $db->prepare("UPDATE reservas SET estado = 'denegada' WHERE servicio_id = ? AND estado NOT IN ('denegada','cancelada')")->execute([$id]);
        }

        $db->exec("SET FOREIGN_KEY_CHECKS=0");
        $stmt = $db->prepare("DELETE FROM servicios WHERE id = ?");
        $stmt->execute([$id]);
        $db->exec("SET FOREIGN_KEY_CHECKS=1");
        respOk(['id' => $id, 'eliminado' => true]);
    }

    if ($accion === 'servicio_reordenar') {
        $categoria = trim($body['categoria'] ?? '');
        $ids       = $body['ids'] ?? [];
        if (!in_array($categoria, CATEGORIAS_VALIDAS)) respErr('categoría no válida');
        if (!is_array($ids) || empty($ids)) respErr('ids requerido');

        $upd = $db->prepare("UPDATE servicios SET orden=? WHERE id=? AND categoria=?");
        foreach ($ids as $i => $id) {
            $upd->execute([$i, trim($id), $categoria]);
        }
        respOk(['reordenados' => count($ids)]);
    }

    respErr('Acción no reconocida: ' . $accion);

} catch (PDOException $e) {
    error_log('datos.php PDO: ' . $e->getMessage());
    respErr('Error interno del servidor', 500);
}