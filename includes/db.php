<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $hosts = array_unique([DB_HOST, 'localhost', '127.0.0.1']);
    $lastException = null;

    foreach ($hosts as $host) {
        $dsn = 'mysql:host=' . $host;
        if (defined('DB_PORT') && DB_PORT) {
            $dsn .= ';port=' . (int)DB_PORT;
        }
        $dsn .= ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    http_response_code(500);
    $message = 'Database connection failed. Start MySQL in XAMPP, then reload this page.';
    if ($lastException instanceof PDOException) {
        $message .= ' (' . $lastException->getMessage() . ')';
    }
    exit($message);
}
?>
