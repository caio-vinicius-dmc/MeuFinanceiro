<?php
// scripts/rollback_rbac.php
// Executa o rollback contido em migrations/0005_rollback_rbac.sql
require __DIR__ . '/../config/db.php';

$path = __DIR__ . '/../migrations/0005_rollback_rbac.sql';
if (!file_exists($path)) {
    echo "Rollback file not found: $path\n";
    exit(1);
}

$sql = file_get_contents($path);
$stmts = array_filter(array_map('trim', explode(';', $sql)));
try {
    foreach ($stmts as $s) {
        if (strlen($s) > 0) {
            $pdo->exec($s);
        }
    }
    echo "ROLLBACK_DONE\n";
} catch (Exception $e) {
    echo "Rollback error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
