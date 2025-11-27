<?php
// scripts/apply_migration_0003.php
require __DIR__ . '/../config/db.php';

$path = __DIR__ . '/../migrations/0003_add_associacoes_permission.sql';
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
    echo "MIGRATION_DONE\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

// Quick verify
$stmt = $pdo->prepare('SELECT id, slug, name FROM permissions WHERE slug = ?');
$stmt->execute(['acessar_associacoes_contador']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "FOUND:" . PHP_EOL;
    echo "id=" . $row['id'] . " slug=" . $row['slug'] . " name=" . $row['name'] . PHP_EOL;
} else {
    echo "PERMISSION_NOT_FOUND\n";
}

?>
