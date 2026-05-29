<?php
// ============================================================
//  GET  /api/horario-negocio.php          → devuelve config
//  GET  /api/horario-negocio.php?slots=1  → devuelve slots generados
//  POST { accion:'guardar', ... }         → guarda config
// ============================================================

require_once __DIR__ . '/helpers.php';

$CAMPOS = [
    'horario_manana_activo', 'horario_manana_inicio', 'horario_manana_fin',
    'horario_tarde_activo',  'horario_tarde_inicio',  'horario_tarde_fin',
    'horario_intervalo',     'horario_dias_abiertos',
];

try {
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $cfg = getHorarioCfg($db);

        if (isset($_GET['slots'])) {
            $intervalo    = max(1, (int)$cfg['horario_intervalo']);
            $manana       = $cfg['horario_manana_activo'] === '1'
                ? generarSlots($cfg['horario_manana_inicio'], $cfg['horario_manana_fin'], $intervalo) : [];
            $tarde        = $cfg['horario_tarde_activo'] === '1'
                ? generarSlots($cfg['horario_tarde_inicio'], $cfg['horario_tarde_fin'], $intervalo) : [];
            $diasAbiertos = array_values(array_map('intval', explode(',', $cfg['horario_dias_abiertos'])));
            jsonOk([
                'manana'        => array_values($manana),
                'tarde'         => array_values($tarde),
                'todos'         => array_values(array_merge($manana, $tarde)),
                'dias_abiertos' => $diasAbiertos,
            ]);
        }

        jsonOk($cfg);
    }

    $body   = readBody();
    $accion = $body['accion'] ?? 'guardar';

    if ($accion === 'guardar') {
        $stmt = $db->prepare(
            "INSERT INTO configuracion (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
        );
        foreach ($CAMPOS as $c) {
            if (array_key_exists($c, $body)) {
                $stmt->execute([$c, $body[$c]]);
            }
        }
        jsonOk(['guardado' => true]);
    }

    jsonError('Acción no reconocida');
} catch (PDOException $e) {
    error_log('horario-negocio.php PDO: ' . $e->getMessage());
    jsonError('Error interno del servidor', 500);
}
