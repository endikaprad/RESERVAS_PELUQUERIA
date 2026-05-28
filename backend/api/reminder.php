<?php

// backend/api/reminder.php

if (php_sapi_name() !== 'cli') {
    ob_start();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mail.php';

date_default_timezone_set('Europe/Madrid');

function logMsg(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;

    // Guardar en logs del servidor
    error_log('REMINDER: ' . $msg);

    // Mostrar solo si se ejecuta desde terminal
    if (php_sapi_name() === 'cli') {
        echo $line;
    }
}

function outputResult(array $r): void {

    if (php_sapi_name() !== 'cli') {

        if (ob_get_length()) {
            ob_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

try {

    logMsg('Inicio del proceso de recordatorios');

    $mañanaInicio = new DateTime('tomorrow 00:00:00');
    $mañanaFin    = new DateTime('tomorrow 23:59:59');

    $sql = "
        SELECT *
        FROM reservas
        WHERE fecha >= ?
        AND fecha <= ?
        AND (recordatorio_enviado = 0 OR recordatorio_enviado IS NULL)
        AND (estado IS NULL OR estado != 'cancelada')
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Error preparando consulta SQL');
    }

    $inicio = $mañanaInicio->format('Y-m-d H:i:s');
    $fin    = $mañanaFin->format('Y-m-d H:i:s');

    $stmt->bind_param('ss', $inicio, $fin);
    $stmt->execute();

    $result = $stmt->get_result();

    $enviados = 0;
    $errores = 0;

    while ($reserva = $result->fetch_assoc()) {

        try {

            $email = trim($reserva['email'] ?? '');

            if (!$email) {
                continue;
            }

            $nombre = $reserva['nombre'] ?? 'Cliente';
            $fecha  = date('d/m/Y', strtotime($reserva['fecha']));
            $hora   = date('H:i', strtotime($reserva['fecha']));

            $subject = 'Recordatorio de tu cita';

            $html = "
                <div style='font-family:Arial,sans-serif;padding:20px;'>
                    <h2>Hola {$nombre}</h2>

                    <p>Te recordamos que tienes una cita mañana.</p>

                    <div style='background:#f5f5f5;padding:15px;border-radius:10px;margin:20px 0;'>
                        <p><strong>Fecha:</strong> {$fecha}</p>
                        <p><strong>Hora:</strong> {$hora}</p>
                    </div>

                    <p>Te esperamos.</p>
                </div>
            ";

            $mailEnviado = sendMail($email, $subject, $html);

            if ($mailEnviado) {

                $update = $conn->prepare("
                    UPDATE reservas
                    SET
                        recordatorio_enviado = 1,
                        recordatorio_fecha = NOW(),
                        ultimo_recordatorio_error = NULL
                    WHERE id = ?
                ");

                $update->bind_param('i', $reserva['id']);
                $update->execute();

                $enviados++;

                logMsg("Recordatorio enviado a {$email}");

            } else {

                $update = $conn->prepare("
                    UPDATE reservas
                    SET ultimo_recordatorio_error = ?
                    WHERE id = ?
                ");

                $errorText = 'Error enviando email';

                $update->bind_param('si', $errorText, $reserva['id']);
                $update->execute();

                $errores++;

                logMsg("Error enviando a {$email}");
            }

        } catch (Throwable $e) {

            $errores++;

            logMsg('Error individual: ' . $e->getMessage());
        }
    }

    logMsg("Proceso terminado. Enviados: {$enviados} | Errores: {$errores}");

    outputResult([
        'success' => true,
        'message' => 'Proceso completado',
        'enviados' => $enviados,
        'errores' => $errores
    ]);

} catch (Throwable $e) {

    logMsg('ERROR GENERAL: ' . $e->getMessage());

    outputResult([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}