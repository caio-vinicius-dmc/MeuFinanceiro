<?php
// process/export_lancamentos.php
require_once '../config/functions.php';
requireLogin(); 
global $pdo;

// --- 1. Capturar e Sanitizar Filtros (Via GET) ---
$filtro_empresa_id = $_GET['id_empresa'] ?? null;
$filtro_status = $_GET['status'] ?? null;
$filtro_venc_inicio = $_GET['venc_inicio'] ?? null;
$filtro_venc_fim = $_GET['venc_fim'] ?? null;
$filtro_valor_min = $_GET['valor_min'] ?? null;
$filtro_valor_max = $_GET['valor_max'] ?? null;

// --- 2. Lógica de Permissão e Construção WHERE/PARAMS (REPLICADA) ---
$where_conditions = [];
$params = [];

// 2a. Permissão de Dados (Clientes e Contadores)
$clientes_permitidos_ids = []; 
if (isContador()) {
    $stmt_clientes_assoc = $pdo->prepare("SELECT id_cliente FROM contador_clientes_assoc WHERE id_usuario_contador = ?");
    $stmt_clientes_assoc->execute([$_SESSION['user_id']]);
    $clientes_permitidos_ids = $stmt_clientes_assoc->fetchAll(PDO::FETCH_COLUMN);

    if (empty($clientes_permitidos_ids)) {
        $where_conditions[] = "e.id_cliente IN (0)"; 
    } else {
        $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
        $where_conditions[] = "e.id_cliente IN ($placeholders)";
        // ATENÇÃO: Adiciona os IDs dos clientes permitidos aos parâmetros
        $params = array_merge($params, $clientes_permitidos_ids);
    }
} 
elseif (isClient()) {
    $where_conditions[] = "e.id_cliente = ?";
    $params[] = $_SESSION['id_cliente_associado'];
}

// 2b. Filtros de Interface
if (!empty($filtro_empresa_id)) {
    $where_conditions[] = "l.id_empresa = ?";
    $params[] = $filtro_empresa_id;
}
if (!empty($filtro_status)) {
    $where_conditions[] = "l.status = ?";
    $params[] = $filtro_status;
}
if (!empty($filtro_venc_inicio)) {
    $where_conditions[] = "l.data_vencimento >= ?";
    $params[] = $filtro_venc_inicio;
}
if (!empty($filtro_venc_fim)) {
    $where_conditions[] = "l.data_vencimento <= ?";
    $params[] = $filtro_venc_fim;
}
if (is_numeric($filtro_valor_min)) {
    $where_conditions[] = "l.valor >= ?";
    $params[] = $filtro_valor_min;
}
if (is_numeric($filtro_valor_max)) {
    $where_conditions[] = "l.valor <= ?";
    $params[] = $filtro_valor_max;
}

$where_sql = "";
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

// --- 3. Consulta de Exportação (Corrigida a sintaxe e espaçamento) ---
$sql_export = "SELECT 
    l.data_vencimento,
    l.data_pagamento,
    c.nome_responsavel AS nome_cliente,
    e.razao_social AS nome_empresa,
    l.descricao,
    l.tipo,
    l.valor,
    l.status,
    l.observacao_contestacao
    FROM lancamentos l
    JOIN empresas e ON l.id_empresa = e.id
    JOIN clientes c ON e.id_cliente = c.id
    $where_sql
    ORDER BY l.data_vencimento ASC";

$stmt_export = $pdo->prepare($sql_export);
// A linha de execução é onde a PDO lança a exceção se a query estiver errada
if (!$stmt_export->execute($params)) {
    // Tratamento de erro adicional, caso o prepare/execute falhe por outros motivos
    error_log("Erro de exportação: " . print_r($stmt_export->errorInfo(), true));
    die("Erro ao executar a consulta de exportação.");
}
$dados = $stmt_export->fetchAll(PDO::FETCH_ASSOC);

// --- 4. Geração do Arquivo CSV ---

// Cabeçalhos HTTP para download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="lancamentos_export_' . date('Ymd_His') . '.csv"');

// Abre o output como um arquivo (write to output stream)
$output = fopen('php://output', 'w');

// Colunas do CSV
$cabecalho = [
    'Data Vencimento',
    'Data Pagamento',
    'Cliente',
    'Empresa',
    'Descricao',
    'Tipo (receita/despesa)',
    'Valor (R$)',
    'Status',
    'Observacao Contestacao'
];

// Escreve o cabeçalho no CSV
fputcsv($output, $cabecalho, ';'); // Usando ponto e vírgula como delimitador padrão BR

// Escreve os dados
foreach ($dados as $linha) {
    // Traduz o tipo
    $linha['tipo'] = ($linha['tipo'] == 'receita' ? 'Receita' : 'Despesa');
    
    // Formata o valor e substitui ponto por vírgula para compatibilidade com Excel BR
    $linha['valor'] = number_format($linha['valor'], 2, ',', '.');
    
    // Converte a linha para CSV e escreve
    fputcsv($output, $linha, ';');
}

fclose($output);
exit;
?>