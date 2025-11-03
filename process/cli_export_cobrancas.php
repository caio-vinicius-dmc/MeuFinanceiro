<?php
// process/cli_export_cobrancas.php
require_once __DIR__ . '/../config/db.php'; // fornece $pdo

// filtros de exemplo (vazio = sem filtro)
$filtro_empresa_id = null;
$filtro_cliente_id = null;

$where_conditions = [];
$params = [];

if (!empty($filtro_empresa_id)) {
    $where_conditions[] = "cob.id_empresa = ?";
    $params[] = $filtro_empresa_id;
}
if (!empty($filtro_cliente_id)) {
    $where_conditions[] = "emp.id_cliente = ?";
    $params[] = $filtro_cliente_id;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Verifica existência de colunas antes de usar joins específicos
$has_forma = false;
$has_tipo = false;
$colStmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cobrancas'");
$colStmt->execute();
$cols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
if (in_array('id_forma_pagamento', $cols)) $has_forma = true;
if (in_array('id_tipo_cobranca', $cols)) $has_tipo = true;

$select_extra = '';
$joins = '';
if ($has_forma) {
    $select_extra .= ', fp.nome as forma_pagamento_nome';
    $joins .= ' LEFT JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id';
}
if ($has_tipo) {
    $select_extra .= ', tc.nome as tipo_cobranca_nome';
    $joins .= ' LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id';
}

$sql = "SELECT cob.id, cob.descricao, cob.valor, cob.data_competencia, cob.data_vencimento, cob.data_pagamento, cob.status_pagamento, cob.contexto_pagamento, cob.id_forma_pagamento, cob.id_tipo_cobranca,
               emp.razao_social AS empresa, emp.cnpj AS empresa_cnpj, c.nome_responsavel AS cliente $select_extra
        FROM cobrancas cob
        JOIN empresas emp ON cob.id_empresa = emp.id
        JOIN clientes c ON emp.id_cliente = c.id
        $joins
        $where_sql
        ORDER BY cob.data_vencimento DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$outFile = __DIR__ . '/../backups/cobrancas_test_export.csv';
if (!is_dir(dirname($outFile))) mkdir(dirname($outFile), 0755, true);
$out = fopen($outFile, 'w');

$header = [
    'ID',
    'Empresa',
    'CNPJ Empresa',
    'Cliente',
    'Descricao',
    'Tipo Cobranca',
    'Forma Pagamento',
    'Data Competencia',
    'Data Vencimento',
    'Data Pagamento',
    'Valor (R$)',
    'Status',
    'Contexto Pagamento'
];

fputcsv($out, $header, ';');

foreach ($rows as $r) {
    $r['valor'] = number_format($r['valor'], 2, ',', '.');
    $line = [
        $r['id'],
        $r['empresa'],
        $r['empresa_cnpj'],
        $r['cliente'],
        $r['descricao'],
        $r['tipo_cobranca_nome'] ?? '',
        $r['forma_pagamento_nome'] ?? '',
        $r['data_competencia'],
        $r['data_vencimento'],
        $r['data_pagamento'],
        $r['valor'],
        $r['status_pagamento'],
        $r['contexto_pagamento'] ?? ''
    ];
    fputcsv($out, $line, ';');
}

fclose($out);

echo "CSV gerado em: $outFile\n";
exit(0);
