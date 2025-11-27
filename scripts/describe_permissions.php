<?php
require __DIR__ . '/../config/db.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM permissions");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo $c['Field'] . "\t" . $c['Type'] . "\t" . $c['Null'] . "\t" . $c['Key'] . "\t" . ($c['Default'] ?? '') . "\t" . ($c['Extra'] ?? '') . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
