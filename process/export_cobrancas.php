<?php
// process/export_cobrancas.php
require_once '../config/functions.php';
requireLogin();
global $pdo;

// Captura filtros via GET (mesma semântica de pages/cobrancas.php)
$filtro_data_inicio = $_GET['data_inicio'] ?? null;
$filtro_data_fim = $_GET['data_fim'] ?? null;
$filtro_cliente_id = $_GET['cliente_id'] ?? null;
$filtro_id_empresa = $_GET['id_empresa'] ?? null; // usado na visão cliente

$where_conditions = [];
$params = [];
// novos filtros para export: pagamento, competencia, forma de pagamento
$filtro_pag_inicio = $_GET['pag_inicio'] ?? null;
$filtro_pag_fim = $_GET['pag_fim'] ?? null;
$filtro_comp_inicio = $_GET['comp_inicio'] ?? null;
$filtro_comp_fim = $_GET['comp_fim'] ?? null;
$filtro_forma_pag = $_GET['forma_pagamento'] ?? null;

// Permissões: clientes só exportam suas empresas; contadores limitados aos clientes associados
if (isClient()) {
    $id_cliente_logado = $_SESSION['id_cliente_associado'];
    $where_conditions[] = 'emp.id_cliente = ?';
    $params[] = $id_cliente_logado;
    if (!empty($filtro_id_empresa)) {
        $where_conditions[] = 'cob.id_empresa = ?';
        $params[] = $filtro_id_empresa;
    }
} elseif (isContador()) {
    // limitar aos clientes associados do contador
    $stmt_clientes_assoc = $pdo->prepare("SELECT id_cliente FROM contador_clientes_assoc WHERE id_usuario_contador = ?");
    $stmt_clientes_assoc->execute([$_SESSION['user_id']]);
    $clientes_permitidos_ids = $stmt_clientes_assoc->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($clientes_permitidos_ids)) {
        $placeholders = implode(',', array_fill(0, count($clientes_permitidos_ids), '?'));
        $where_conditions[] = "emp.id_cliente IN ($placeholders)";
        $params = array_merge($params, $clientes_permitidos_ids);
    } else {
        // sem clientes permitidos -> nada a exportar
        $where_conditions[] = '1=0';
    }
} // isAdmin não tem restrição adicional

// Aplicar filtro por empresa também para admin/contador quando fornecido
if (!empty($filtro_id_empresa) && !isClient()) {
    $where_conditions[] = 'cob.id_empresa = ?';
    $params[] = $filtro_id_empresa;
}

// Filtros de data (aplica somente se houver ambos)
if ($filtro_data_inicio && $filtro_data_fim) {
    $where_conditions[] = 'cob.data_vencimento BETWEEN ? AND ?';
    $params[] = $filtro_data_inicio;
    $params[] = $filtro_data_fim;
}

if ($filtro_cliente_id && isAdmin()) {
    // filtro por cliente usado apenas em admin
    $where_conditions[] = 'emp.id_cliente = ?';
    $params[] = $filtro_cliente_id;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

$sql = "SELECT cob.id, cob.descricao, cob.valor, cob.data_competencia, cob.data_vencimento, cob.data_pagamento, cob.status_pagamento, cob.contexto_pagamento, cob.id_forma_pagamento, cob.id_tipo_cobranca,
               emp.razao_social AS empresa, emp.cnpj AS empresa_cnpj, c.nome_responsavel AS cliente, fp.nome as forma_pagamento_nome, tc.nome as tipo_cobranca_nome
        FROM cobrancas cob
        JOIN empresas emp ON cob.id_empresa = emp.id
        JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id
        LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id
        JOIN clientes c ON emp.id_cliente = c.id
        $where_sql
        ORDER BY cob.data_vencimento DESC";

$stmt = $pdo->prepare($sql);
if (!$stmt->execute($params)) {
    error_log('Erro export_cobrancas: ' . print_r($stmt->errorInfo(), true));
    die('Erro ao executar a consulta de exportação de cobranças.');
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gera CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="cobrancas_export_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');

$header = [
    'ID',
    'Empresa',
    'CNPJ Empresa',
    'Cliente',
    'Descricao',
    'Tipo Cobranca',
    'Forma Pagamento',
    'Mês de competência',
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
        (!empty($r['data_competencia']) ? date('m/Y', strtotime($r['data_competencia'])) : ''),
        $r['data_vencimento'],
        $r['data_pagamento'],
        $r['valor'],
        $r['status_pagamento'],
        $r['contexto_pagamento'] ?? ''
    ];
    fputcsv($out, $line, ';');
}

fclose($out);
exit;
?>
