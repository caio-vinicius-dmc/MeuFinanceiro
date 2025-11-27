<?php
// migrations/apply_migrations.php
// Executa arquivos .sql na pasta migrations em ordem alfabética.
require_once __DIR__ . '/../config/functions.php';

function applySqlFile($path) {
    global $pdo;
    $sql = file_get_contents($path);
    if ($sql === false) return false;
    try {
        // Divide em statements por ';' e executa um a um para evitar problemas com múltiplos statements
        $stmts = array_filter(array_map('trim', explode(';', $sql)));
        try {
            $pdo->beginTransaction();
        } catch (Exception $e) {
            // se não for possível iniciar transação, prossegue sem transação
        }
        foreach ($stmts as $stmt) {
            if ($stmt === '') continue;
            $pdo->exec($stmt);
        }
        if ($pdo->inTransaction()) $pdo->commit();
        echo "Applied: $path\n";
        return true;
    } catch (Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            try { $pdo->rollBack(); } catch (Exception $e2) {}
        }
        echo "Failed to apply $path: " . $e->getMessage() . "\n";
        return false;
    }
}

$files = glob(__DIR__ . '/*.sql');
sort($files);
foreach ($files as $f) applySqlFile($f);

echo "Done.\n";
