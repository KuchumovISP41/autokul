<?php
// Manual CLI helper: php scripts/bootstrap_database.php
// The website also runs this bootstrap automatically through getDBConnection().

require_once __DIR__ . '/../includes/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

$pdo = getDBConnection();
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

echo "Database is ready: " . DB_NAME . PHP_EOL;
echo "Tables: " . implode(', ', $tables) . PHP_EOL;
echo "Demo users: admin@autokul.ru / ivan@example.com / mechanic@autokul.ru" . PHP_EOL;
echo "Demo password: password123" . PHP_EOL;
