<?php
// scripts/migrate_legacy_roles.php
// Mapeia usuários existentes (coluna `tipo`) para os papéis criados pelo seed.
require_once __DIR__ . '/../config/functions.php';
global $pdo;

$map = [
    'super admin' => 'super_admin',
    'admin' => 'admin',
    'contador' => 'contador',
    'cliente' => 'cliente'
];

foreach ($map as $tipo => $slug) {
    // encontrar role id
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $roleId = $stmt->fetchColumn();
    if (!$roleId) { echo "Role not found: $slug\n"; continue; }

    $stmtUsers = $pdo->prepare('SELECT id FROM usuarios WHERE tipo = ?');
    $stmtUsers->execute([$tipo]);
    $users = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);
    foreach ($users as $uid) {
        try {
            $ins = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)');
            $ins->execute([$uid, $roleId]);
            echo "Assigned role $slug to user $uid\n";
        } catch (Exception $e) {
            echo "Error assigning role to $uid: " . $e->getMessage() . "\n";
        }
    }
}

echo "Done.\n";
