<?php
// scripts/apply_03_add_fk.php
// Executa a criação da FK fk_lancamentos_forma_pagamento de forma segura
require_once __DIR__ . '/../config/db.php';

try {
    // Confere se a coluna existe
    $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_forma_pagamento'");
    $colStmt->execute();
    $has_col = $colStmt->fetchColumn() > 0;

    if (!$has_col) {
        echo "Coluna id_forma_pagamento não existe em lancamentos. Não será criada a FK.\n";
        exit(0);
    }

    // Confere se a constraint já existe
    $ckStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND CONSTRAINT_NAME = 'fk_lancamentos_forma_pagamento'");
    $ckStmt->execute();
    $exists = $ckStmt->fetchColumn() > 0;

    if ($exists) {
        echo "Constraint fk_lancamentos_forma_pagamento já existe. Nada a fazer.\n";
        exit(0);
    }

    // Aplica a FK
    $sql = "ALTER TABLE lancamentos ADD CONSTRAINT fk_lancamentos_forma_pagamento FOREIGN KEY (id_forma_pagamento) REFERENCES formas_pagamento(id) ON DELETE SET NULL ON UPDATE CASCADE";
    $pdo->exec($sql);
    echo "FK fk_lancamentos_forma_pagamento criada com sucesso.\n";
} catch (PDOException $e) {
    echo "Erro ao criar FK: " . $e->getMessage() . "\n";
    exit(1);
}
