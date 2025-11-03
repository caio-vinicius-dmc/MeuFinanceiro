<?php
// process/cli_export_lancamentos.php
// Script CLI para gerar um CSV de teste dos lançamentos (sem autenticação)
require_once __DIR__ . '/../config/db.php'; // fornece $pdo

// filtros de exemplo (vazio = sem filtro)
$filtro_empresa_id = null;
$filtro_status = null;

$where_conditions = [];
$params = [];

if (!empty($filtro_empresa_id)) {
    $where_conditions[] = "l.id_empresa = ?";
    $params[] = $filtro_empresa_id;
}
if (!empty($filtro_status)) {
    $where_conditions[] = "l.status = ?";
    $params[] = $filtro_status;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Verifica se existe a coluna id_forma_pagamento na tabela lancamentos
$has_forma_pag = false;
$colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_forma_pagamento'");
$colStmt->execute();
if ($colStmt->fetchColumn() > 0) {
    $has_forma_pag = true;
}

if ($has_forma_pag) {
    $sql = "SELECT 
    l.data_vencimento,
    l.data_pagamento,
    c.nome_responsavel AS nome_cliente,
    e.razao_social AS nome_empresa,
    l.descricao,
    l.tipo,
    l.valor,
    fp.nome as forma_pagamento_nome,
    l.status
    FROM lancamentos l
    JOIN empresas e ON l.id_empresa = e.id
    JOIN clientes c ON e.id_cliente = c.id
    LEFT JOIN formas_pagamento fp ON l.id_forma_pagamento = fp.id
    $where_sql
    ORDER BY l.data_vencimento ASC";
} else {
    // Versão sem forma de pagamento
    $sql = "SELECT 
    l.data_vencimento,
    l.data_pagamento,
    c.nome_responsavel AS nome_cliente,
    e.razao_social AS nome_empresa,
    l.descricao,
    l.tipo,
    l.valor,
    l.status
    FROM lancamentos l
    JOIN empresas e ON l.id_empresa = e.id
    JOIN clientes c ON e.id_cliente = c.id
    $where_sql
    ORDER BY l.data_vencimento ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$outFile = __DIR__ . '/../backups/lancamentos_test_export.csv';
if (!is_dir(dirname($outFile))) mkdir(dirname($outFile), 0755, true);
$out = fopen($outFile, 'w');

$header = [
    'Data Vencimento',
    'Data Pagamento',
    'Cliente',
    'Empresa',
    'Descricao',
    'Tipo (receita/despesa)',
    'Forma Pagamento',
    'Valor (R$)',
    'Status',
    'Observacao Contestacao'
];
fputcsv($out, $header, ';');

foreach ($dados as $linha) {
    $linha['tipo'] = ($linha['tipo'] === 'receita' ? 'Receita' : 'Despesa');
    $linha['valor'] = number_format($linha['valor'], 2, ',', '.');
    $csvLine = [
        $linha['data_vencimento'],
        $linha['data_pagamento'],
        $linha['nome_cliente'],
        $linha['nome_empresa'],
        $linha['descricao'],
        $linha['tipo'],
        $linha['forma_pagamento_nome'] ?? '',
        $linha['valor'],
        $linha['status'],
        ''
    ];
    fputcsv($out, $csvLine, ';');
}

fclose($out);

echo "CSV gerado em: $outFile\n";
exit(0);
