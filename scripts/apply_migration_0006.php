<?php
// scripts/apply_migration_0006.php
require __DIR__ . '/../config/db.php';

$path = __DIR__ . '/../migrations/0006_ensure_permissions.sql';
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
    echo "MIGRATION_0006_DONE\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

// Quick verify: listar permissões garantidas
$slugs = [
    'visualizar_documentos','gerenciar_papeis','gerenciar_documentos','gerenciar_empresas','acessar_lancamentos','acessar_cobrancas','acessar_configuracoes','acessar_logs','gerenciar_usuarios','acessar_associacoes_contador'
];

foreach ($slugs as $s) {
    $stmt = $pdo->prepare('SELECT id, slug, name FROM permissions WHERE slug = ?');
    $stmt->execute([$s]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "PERM: {$row['slug']} -> {$row['name']}\n";
    } else {
        echo "MISSING: $s\n";
    }
}

?>