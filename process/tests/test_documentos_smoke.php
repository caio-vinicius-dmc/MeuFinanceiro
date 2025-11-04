<?php
// Smoke test for documentos-related helpers (CLI use)
chdir(__DIR__ . '/../../');
require_once __DIR__ . '/../../config/functions.php';

$checks = [
    'isAdmin', 'base_url', 'getDocumentUploadPolicy', 'logAction'
];

$ok = true;
foreach ($checks as $fn) {
    if (!function_exists($fn)) {
        echo "MISSING: function {$fn} not found\n";
        $ok = false;
    } else {
        echo "OK: function {$fn} exists\n";
    }
}

// Basic DB connection check (will only attempt if $pdo is available)
global $pdo;
if (isset($pdo)) {
    try {
        $stmt = $pdo->query('SELECT 1');
        echo "OK: DB connection active\n";
    } catch (Exception $e) {
        echo "WARN: DB connection failed: " . $e->getMessage() . "\n";
        $ok = false;
    }
} else {
    echo "WARN: PDO not available (skipping DB check)\n";
}

if ($ok) exit(0);
exit(2);
