<?php
// migrations/run_migrations.php
// Runner idempotente simples para aplicar migrações .sql na pasta migrations/
// Uso: acessar via navegador ou CLI (php run_migrations.php)

require_once __DIR__ . '/../config/functions.php';

// Allow running from CLI for convenience (dev), otherwise require web login as admin
if (PHP_SAPI === 'cli') {
    // ok
} else {
    requireLogin();
    // only allow global admins to run migrations from web UI
    if (!function_exists('isAdmin') || !isAdmin()) {
        http_response_code(403);
        echo "Acesso negado. Apenas administradores podem executar migrações.";
        exit;
    }
}

$dir = __DIR__;
$files = array_values(array_filter(scandir($dir), function($f){ return preg_match('/^\d+.*\\.sql$/', $f); }));
sort($files, SORT_STRING);

global $pdo;

// ensure migrations_applied table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS migrations_applied (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$appliedStmt = $pdo->prepare('SELECT filename FROM migrations_applied');
$appliedStmt->execute();
$applied = $appliedStmt->fetchAll(PDO::FETCH_COLUMN);

$results = [];

foreach ($files as $file) {
    if (in_array($file, $applied)) {
        $results[$file] = ['status' => 'skipped', 'message' => 'Já aplicado'];
        continue;
    }
    $path = $dir . DIRECTORY_SEPARATOR . $file;
    $sql = file_get_contents($path);
    if ($sql === false) {
        $results[$file] = ['status' => 'error', 'message' => 'Falha ao ler arquivo'];
        continue;
    }

    // Split statements by semicolon; basic approach (won't handle complex procs)
    $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));

    try {
        $pdo->beginTransaction();
        foreach ($stmts as $stmt) {
            $s = trim($stmt);
            // skip explicit transaction control inside SQL files (we manage transactions in the runner)
            if (preg_match('/^(START\s+TRANSACTION|COMMIT|ROLLBACK)\b/i', $s)) {
                continue;
            }
            if ($s === '') continue;

            if (stripos($s, 'ADD COLUMN IF NOT EXISTS') !== false) {
                // tenta extrair table, column e definição
                // exemplo: ALTER TABLE `lancamentos` ADD COLUMN IF NOT EXISTS `empresa_id` INT DEFAULT NULL
                if (preg_match('/ALTER\s+TABLE\s+`?(\w+)`?\s+ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?\s+(.+)/is', $s, $m)) {
                    $table = $m[1];
                    $col = $m[2];
                    $def = rtrim($m[3], "\n\r ;");
                    // if table doesn't exist, skip this statement
                    $tblCheck = $pdo->prepare("SELECT COUNT(1) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
                    $tblCheck->execute([$table]);
                    $tableExists = $tblCheck->fetchColumn() > 0;
                    if (!$tableExists) {
                        continue;
                    }
                    // check if column exists
                    $colCheck = $pdo->prepare("SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                    $colCheck->execute([$table, $col]);
                    $exists = $colCheck->fetchColumn() > 0;
                    if ($exists) {
                        // skip
                        continue;
                    } else {
                        $alt = "ALTER TABLE `" . $table . "` ADD COLUMN `" . $col . "` " . $def;
                        $pdo->exec($alt);
                        continue;
                    }
                }
            }

            // handle CREATE INDEX idempotently (MySQL doesn't support IF NOT EXISTS for indexes)
            if (preg_match('/CREATE\s+INDEX\s+`?(\w+)`?\s+ON\s+`?(\w+)`?/i', $s, $m)) {
                $idxName = $m[1];
                $tblName = $m[2];
                $idxCheck = $pdo->prepare("SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME = ? AND TABLE_NAME = ?");
                $idxCheck->execute([$idxName, $tblName]);
                if ($idxCheck->fetchColumn() > 0) {
                    continue;
                }
            }
            // fallback: execute statement directly
            $pdo->exec($s);
        }
        // mark applied
        $ins = $pdo->prepare('INSERT INTO migrations_applied (filename) VALUES (?)');
        $ins->execute([$file]);
        $pdo->commit();
        $results[$file] = ['status' => 'applied', 'message' => 'Executado com sucesso'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $results[$file] = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

