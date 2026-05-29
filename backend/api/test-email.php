<?php
// ============================================================
//  DIAGNÓSTICO — /backend/api/test-email.php
//  Solo accesible desde localhost
// ============================================================
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso denegado']);
    exit;
}
// ============================================================

require_once __DIR__ . '/../config.php';

$results = [];

// 1. ¿Está cargada la API key?
$apiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : '';
$results['api_key_definida'] = !empty($apiKey) ? '✅ Sí (' . substr($apiKey, 0, 8) . '...)' : '❌ No definida';

// 2. ¿curl disponible?
$results['curl_disponible'] = function_exists('curl_init') ? '✅ Sí' : '❌ No';

// 3. Test de conexión a Resend
if (function_exists('curl_init') && $apiKey) {
    $payload = json_encode([
        'from'    => 'Prado Barber Co. <onboarding@resend.dev>',
        'to'      => ['endikapradodev@gmail.com'],
        'subject' => '🧪 Test email - Prado Barber Co.',
        'html'    => '<h1>¡Funciona!</h1><p>Este es un email de prueba desde el servidor de InfinityFree.</p>',
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $results['resend_http_code'] = $httpCode;
    $results['resend_respuesta'] = $resp;
    $results['curl_error']       = $curlErr ?: 'Ninguno';

    if ($httpCode === 200 || $httpCode === 201) {
        $results['email_enviado'] = '✅ Email enviado correctamente a endikapradodev@gmail.com';
    } else {
        $results['email_enviado'] = '❌ Falló el envío. HTTP ' . $httpCode;
    }
} else {
    $results['email_enviado'] = '⚠ Saltado (curl o API key no disponibles)';
}

// 4. Test BD
try {
    $db = getDB();
    $db->query('SELECT 1');
    $results['bd_conexion'] = '✅ Base de datos conectada';
} catch (Exception $e) {
    $results['bd_conexion'] = '❌ Error BD: ' . $e->getMessage();
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Test Diagnóstico - Prado Barber</title>
  <style>
    body { background: #09080f; color: #f0ece3; font-family: monospace; padding: 2rem; }
    h1   { color: #c9a84c; margin-bottom: 1.5rem; }
    .row { background: #111119; border: 1px solid #252530; border-radius: 8px;
           padding: 1rem 1.5rem; margin-bottom: 0.75rem; }
    .key { color: #7a7880; font-size: 0.85rem; margin-bottom: 0.25rem; }
    .val { font-size: 0.95rem; word-break: break-all; }
    .warn { background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.3);
            color: #f59e0b; padding: 1rem 1.5rem; border-radius: 8px; margin-top: 1.5rem; }
  </style>
</head>
<body>
  <h1>🧪 Diagnóstico Prado Barber Co.</h1>
  <?php foreach ($results as $key => $val): ?>
    <div class="row">
      <div class="key"><?= htmlspecialchars($key) ?></div>
      <div class="val"><?= htmlspecialchars((string)$val) ?></div>
    </div>
  <?php endforeach; ?>
  <div class="warn">
    ⚠ <strong>Recuerda borrar este archivo</strong> después de las pruebas:<br>
    <code>backend/api/test-email.php</code>
  </div>
</body>
</html>