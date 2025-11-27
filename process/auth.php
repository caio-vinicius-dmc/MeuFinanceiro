<?php
// process/auth.php
require_once '../config/functions.php'; // Inclui DB e session_start

if (isset($_POST['action']) && $_POST['action'] == 'login') {
    
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    if (empty($email) || empty($senha)) {
        header("Location: " . base_url('index.php?page=login'));
        exit;
    }

    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // 1. Verifica se o usuário existe
    // 2. Verifica a senha usando password_verify
    if ($user && password_verify($senha, $user['senha'])) {
        
        // Login bem-sucedido
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome'];
        $_SESSION['user_tipo'] = $user['tipo'];
        
        // Se for do tipo 'cliente', armazena o ID do cliente associado e a permissão de lançamentos
        if ($user['tipo'] == 'cliente') {
            $_SESSION['id_cliente_associado'] = $user['id_cliente_associado'];
            $_SESSION['user_acesso_lancamentos'] = $user['acesso_lancamentos'];
        }

        // Carrega roles e permissões do usuário na sessão (se a biblioteca RBAC estiver disponível)
        if (function_exists('rbac_load_user_into_session')) {
            try { rbac_load_user_into_session($user['id']); } catch (Exception $e) { /* ignore */ }
        }

        logAction("Login bem-sucedido");
        header("Location: " . base_url('index.php?page=dashboard'));
        exit;

    } else {
        // Falha no login
        logAction("Falha no login", "usuarios", null, "Email: $email");
        $_SESSION['error_message'] = "Email ou senha inválidos.";
        header("Location: ../index.php?page=login");
        exit;
    }
}
?>