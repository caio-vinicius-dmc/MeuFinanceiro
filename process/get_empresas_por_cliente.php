<?php
// process/get_empresas_por_cliente.php
require_once '../config/functions.php';
requireLogin();
global $pdo;

$cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : null;
if (empty($cliente_id)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'cliente_id é obrigatório', 'data' => []]);
    exit;
}

// Permissões: cliente só pode pedir suas próprias empresas
if (isClient()) {
    if ($_SESSION['id_cliente_associado'] != $cliente_id) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão', 'data' => []]);
        exit;
    }
}

// Contador: só pode pedir clientes associados, a menos que seja admin ou tenha permissão RBAC 'gerenciar_empresas'
if (isContador() && !(isAdmin() || (function_exists('current_user_has_permission') && current_user_has_permission('gerenciar_empresas')))) {
    $stmt = $pdo->prepare("SELECT COUNT(1) FROM contador_clientes_assoc WHERE id_usuario_contador = ? AND id_cliente = ?");
    $stmt->execute([$_SESSION['user_id'], $cliente_id]);
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Sem permissão', 'data' => []]);
        exit;
    }
}

$stmt = $pdo->prepare("SELECT id, razao_social FROM empresas WHERE id_cliente = ? ORDER BY razao_social");
$stmt->execute([$cliente_id]);
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'data' => $empresas]);
exit;

?>
