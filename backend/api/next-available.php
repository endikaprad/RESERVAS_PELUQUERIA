<?php
// ============================================================
//  GET /api/next-available.php
//
//  Devuelve el siguiente hueco libre (hoy o en días futuros)
//  para mostrar en el widget de la página de inicio.
//
//  Respuesta:
//  {
//    "ok": true,
//    "data": {
//      "barbero":   "Endika Prado",
//      "iniciales": "EP",
//      "fecha":     "2026-05-22",
//      "hora":      "17:00",
//      "etiqueta":  "Hoy · 17:00"   ← texto listo para el widget
//    }
//  }
// ============================================================

require_once __DIR__ . '/helpers.php';

// Huecos disponibles del negocio (igual que TIME_SLOTS en el JS)
const TODOS_LOS_SLOTS = [
    '09:00','09:30','10:00','10:30','11:00','11:30',
    '12:00','12:30','13:00','13:30',
    '16:00','16:30','17:00','17:30','18:00','18:30','19:00','19:30',
];

// Horario: Lun-Vie 09-20, Sáb 09-14, Dom cerrado
// Solo se filtran los slots que caen dentro del horario del día
function slotsParaDia(int $diaSemana): array {
    if ($diaSemana === 0) return [];          // Domingo
    if ($diaSemana === 6) {                   // Sábado → hasta 14:00
        return array_filter(TODOS_LOS_SLOTS, fn($t) => $t < '14:00');
    }
    return TODOS_LOS_SLOTS;                  // Lun–Vie → todos
}

try {
    $db   = getDB();
    $now  = new DateTime('now');
    $hoy  = $now->format('Y-m-d');
    $horaActual = $now->format('H:i');

    // Recorremos los próximos 14 días buscando el primer hueco
    for ($offset = 0; $offset < 14; $offset++) {
        $dt      = (new DateTime($hoy))->modify("+{$offset} days");
        $fecha   = $dt->format('Y-m-d');
        $diaSem  = (int)$dt->format('w');   // 0=Dom … 6=Sáb
        $slots   = slotsParaDia($diaSem);

        if (empty($slots)) continue;

        // Obtener horas ya ocupadas ese día (cualquier barbero)
        $stmt = $db->prepare(
            'SELECT TIME_FORMAT(hora, "%H:%i") AS hora, barbero_id
             FROM reservas WHERE fecha = ?'
        );
        $stmt->execute([$fecha]);
        $ocupadas = $stmt->fetchAll();      // [['hora'=>'09:00','barbero_id'=>'endika'], …]

        // Agrupar por barbero: qué horas tiene ocupadas
        $ocupadasPorBarbero = [];
        foreach ($ocupadas as $r) {
            $ocupadasPorBarbero[$r['barbero_id']][] = $r['hora'];
        }

        // Obtener la lista de barberos
        $barberos = $db->query('SELECT id, nombre, iniciales FROM barberos')->fetchAll();

        foreach ($slots as $slot) {
            // Si es hoy, saltar horas que ya pasaron
            if ($offset === 0 && $slot <= $horaActual) continue;

            foreach ($barberos as $b) {
                $ocupadasB = $ocupadasPorBarbero[$b['id']] ?? [];
                if (!in_array($slot, $ocupadasB, true)) {
                    // ¡Encontrado!
                    if ($offset === 0) {
                        $etiqueta = 'Hoy · ' . $slot;
                    } elseif ($offset === 1) {
                        $etiqueta = 'Mañana · ' . $slot;
                    } else {
                        $etiqueta = $dt->format('d/m') . ' · ' . $slot;
                    }

                    jsonOk([
                        'barbero'   => $b['nombre'],
                        'iniciales' => $b['iniciales'],
                        'barbero_id'=> $b['id'],
                        'fecha'     => $fecha,
                        'hora'      => $slot,
                        'etiqueta'  => $etiqueta,
                    ]);
                }
            }
        }
    }

    // Si en 14 días no hay nada libre (muy improbable)
    jsonOk(['barbero' => null, 'etiqueta' => 'Sin disponibilidad']);

} catch (PDOException $e) {
    jsonError('Error de base de datos: ' . $e->getMessage(), 500);
}