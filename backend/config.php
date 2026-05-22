<?php
// ============================================================
//  PRADO BARBER CO. — Configuración de base de datos
// ============================================================

define('DB_HOST',    'sql101.infinityfree.com');
define('DB_NAME',    'if0_41992824_pradobarber');
define('DB_USER',    'if0_41992824');
define('DB_PASS',    'Endika2710SQDEV');
define('DB_CHARSET', 'utf8mb4');

date_default_timezone_set('Europe/Madrid');

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
        $pdo->exec("SET time_zone = 'Europe/Madrid'");
    }
    return $pdo;
}