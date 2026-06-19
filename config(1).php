<?php
// config.php — Conexión a MariaDB
session_start();

define('DB_HOST', '10.238.49.21');   // IP de tu VM con MariaDB
define('DB_NAME', 'votos_db');
define('DB_USER', 'votacion_user');
define('DB_PASS', 'VotacionBolivia2025!');

date_default_timezone_set('America/La_Paz');

function getDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die('Error de conexión con la base de datos.');
    }
}
?>
