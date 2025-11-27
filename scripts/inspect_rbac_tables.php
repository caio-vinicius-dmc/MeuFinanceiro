<?php
require __DIR__ . '/../config/db.php';
$tables = ['roles','permissions','role_permissions','user_roles'];
foreach ($tables as $t) {
    echo "--- $t ---\n";
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM ' . $t);
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            echo $c['Field'] . ' ' . $c['Type'] . ' ' . $c['Null'] . ' ' . $c['Key'] . ' ' . ($c['Default'] ?? '') . ' ' . ($c['Extra'] ?? '') . "\n";
        }
    } catch (Exception $e) {
        echo 'Table ' . $t . ' not found\n';
    }
}
