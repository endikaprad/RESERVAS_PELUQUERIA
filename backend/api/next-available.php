<?php
// ============================================================
//  GET /api/next-available.php
//
//  Devuelve el siguiente hueco libre (hoy o en días futuros)
//  respetando días bloqueados por vacaciones.
// ============================================================

require_once __DIR__ . '/helpers.php';

const TODOS_LOS_SLOTS = [
    '09:00','09:30','10:00','10:30','11:00','11:30',
    '12:00','12:30','13:00','13:30',
    '16:00','16:30','17:00','17:30','18:00','18:30','19:00','19:30',
];

function slotsParaDia(int $diaSemana): array {
    if ($diaSemana === 0) return [];
    if ($diaSemana === 6) {
        return array_filter(TODOS_LOS_SLOTS, fn($t) => $t < '14:00');
    }
    return TODOS_LOS_SLOTS;
}

try {
    $db   = getDB();
    $now  = new DateTime('now');
    $hoy  = $now->format('Y-m-d');
    $horaActual = $now->format('H:i');

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
        $slots   = slotsParaDia($diaSem);

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
    jsonError('Error de base de datos: ' . $e->getMessage(), 500);
}