<?php
// ============================================================
//  PRADO BARBER CO. — Configuración de base de datos
//  Edita los valores según tu entorno local/servidor
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'pradobarber');
define('DB_USER', 'root');       // ← cambia a tu usuario
define('DB_PASS', '');           // ← cambia a tu contraseña
define('DB_CHARSET', 'utf8mb4');

/**
 * Devuelve una conexión PDO singleton.
 * Lanza una excepción si no puede conectar.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}