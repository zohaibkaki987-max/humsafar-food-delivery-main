<?php
/**
 * Humsafar database configuration.
 *
 * Values can be supplied through environment variables on production servers.
 * The local XAMPP defaults are kept as a fallback so the existing local setup
 * continues to work without extra configuration.
 */

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF);

$host = getenv('HUMSAFAR_DB_HOST') ?: 'localhost';
$dbname = getenv('HUMSAFAR_DB_NAME') ?: 'humsafar';
$username = getenv('HUMSAFAR_DB_USER') ?: 'root';
$password = getenv('HUMSAFAR_DB_PASSWORD') ?: '';
$port = (int) (getenv('HUMSAFAR_DB_PORT') ?: 3306);

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_errno) {
    error_log('Humsafar database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    exit('Database connection is temporarily unavailable.');
}

if (!$conn->set_charset('utf8mb4')) {
    error_log('Humsafar database charset setup failed: ' . $conn->error);
}

?>