<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/helpers.php';

try {
    $db = getDB();
    echo json_encode(['ok' => true, 'msg' => 'Conexion BD correcta']);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}