<?php
// scripts/apply_migration_04.php
// Run from command line: php apply_migration_04.php

require_once __DIR__ . '/../config/functions.php';

$sqlFile = __DIR__ . '/../migrations/04_create_documentos_tables.sql';
if (!file_exists($sqlFile)) {
    echo "Migration file not found: $sqlFile\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
if (empty(trim($sql))) {
    echo "Migration file is empty.\n";
    exit(1);
}

try {
    // Split statements by semicolon that's at end of line (simple splitter)
    $statements = preg_split('/;\s*\n/', $sql);
    foreach ($statements as $stmt) {
        $s = trim($stmt);
        if ($s === '') continue;
        // Ignorar comandos de controle de transação presentes no arquivo SQL
        if (preg_match('/^\s*(commit|start transaction|begin)\b/i', $s)) continue;
        // Execute each statement
        $pdo->exec($s);
    }
    echo "Migration 04 applied successfully.\n";
} catch (Exception $e) {
    echo "Failed to apply migration: " . $e->getMessage() . "\n";
    exit(1);
}

return 0;
