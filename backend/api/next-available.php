<?php
// ============================================================
//  GET /api/next-available.php
//
//  Devuelve el siguiente hueco libre (hoy o en días futuros)
//  respetando días bloqueados por vacaciones.
// ============================================================

require_once __DIR__ . '/helpers.php';

try {
    $db   = getDB();
    $now  = new DateTime('now');
    $hoy  = $now->format('Y-m-d');
    $horaActual = $now->format('H:i');

    // Cargar horario configurado una vez
    $horCfg       = getHorarioCfg($db);
    $intervalo    = max(1, (int)$horCfg['horario_intervalo']);
    $diasAbiertos = array_map('intval', explode(',', $horCfg['horario_dias_abiertos']));
    $slotsMan     = $horCfg['horario_manana_activo'] === '1'
        ? generarSlots($horCfg['horario_manana_inicio'], $horCfg['horario_manana_fin'], $intervalo) : [];
    $slotsTar     = $horCfg['horario_tarde_activo'] === '1'
        ? generarSlots($horCfg['horario_tarde_inicio'], $horCfg['horario_tarde_fin'], $intervalo) : [];
    $todosSlotsGlobal = array_values(array_merge($slotsMan, $slotsTar));

    // Cargar todos los días bloqueados de los próximos 14 días
    $stmt = $db->prepare(
        'SELECT fecha FROM dias_bloqueados WHERE fecha BETWEEN ? AND ?'
    );
    $hasta14 = (new DateTime($hoy))->modify('+14 days')->format('Y-m-d');
    $stmt->execute([$hoy, $hasta14]);
    $diasBloqueados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $diasBloqueados = array_flip($diasBloqueados); // para búsqueda O(1)

    for ($offset = 0; $offset < 14; $offset++) {
        $dt      = (new DateTime($hoy))->modify("+{$offset} days");
        $fecha   = $dt->format('Y-m-d');
        $diaSem  = (int)$dt->format('w');
        $slots   = in_array($diaSem, $diasAbiertos, true) ? $todosSlotsGlobal : [];

        if (empty($slots)) continue;

        // Saltar días bloqueados por vacaciones
        if (isset($diasBloqueados[$fecha])) continue;

        $stmt = $db->prepare(
            'SELECT TIME_FORMAT(hora, "%H:%i") AS hora, barbero_id
             FROM reservas WHERE fecha = ?'
        );
        $stmt->execute([$fecha]);
        $ocupadas = $stmt->fetchAll();

        $ocupadasPorBarbero = [];
        foreach ($ocupadas as $r) {
            $ocupadasPorBarbero[$r['barbero_id']][] = $r['hora'];
        }

        $barberos = $db->query('SELECT id, nombre, iniciales FROM barberos')->fetchAll();

        foreach ($slots as $slot) {
            if ($offset === 0 && $slot <= $horaActual) continue;

            foreach ($barberos as $b) {
                $ocupadasB = $ocupadasPorBarbero[$b['id']] ?? [];
                if (!in_array($slot, $ocupadasB, true)) {
                    if ($offset === 0) {
                        $etiqueta = 'Hoy · ' . $slot;
                    } elseif ($offset === 1) {
                        $etiqueta = 'Mañana · ' . $slot;
                    } else {
                        $etiqueta = $dt->format('d/m') . ' · ' . $slot;
                    }

                    jsonOk([
                        'barbero'    => $b['nombre'],
                        'iniciales'  => $b['iniciales'],
                        'barbero_id' => $b['id'],
                        'fecha'      => $fecha,
                        'hora'       => $slot,
                        'etiqueta'   => $etiqueta,
                    ]);
                }
            }
        }
    }

    jsonOk(['barbero' => null, 'etiqueta' => 'Sin disponibilidad']);

} catch (PDOException $e) {
    error_log('next-available.php PDO: ' . $e->getMessage());
    jsonError('Error interno del servidor', 500);
}