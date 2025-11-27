<?php
// scripts/apply_migration_0004.php
require __DIR__ . '/../config/db.php';

$path = __DIR__ . '/../migrations/0004_add_more_rbac_permissions.sql';
if (!file_exists($path)) {
    echo "Migration file not found: $path\n";
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
    echo "MIGRATION_0004_DONE\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

// Quick list of newly added permissions to verify
$stmt = $pdo->query("SELECT slug, name FROM permissions WHERE slug IN ('acessar_logs','acessar_lancamentos','gerenciar_empresas','gerenciar_documentos') ORDER BY slug");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "PERM: " . $r['slug'] . " -> " . $r['name'] . PHP_EOL;
}

?>
