<?php
// migrations/apply_migrations.php
// Executa arquivos .sql na pasta migrations em ordem alfabética.
require_once __DIR__ . '/../config/functions.php';

function applySqlFile($path) {
    global $pdo;
    $sql = file_get_contents($path);
    if ($sql === false) return false;
    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $pdo->commit();
        echo "Applied: $path\n";
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Failed to apply $path: " . $e->getMessage() . "\n";
        return false;
    }
}

$files = glob(__DIR__ . '/*.sql');
sort($files);
foreach ($files as $f) applySqlFile($f);

echo "Done.\n";
