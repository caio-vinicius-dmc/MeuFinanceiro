<?php
require_once __DIR__ . '/../config/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
    exit;
}

function is_ajax_request() {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) return true;
    if (isset($_POST['ajax'])) return true;
    return false;
}

function json_response($data, $status = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$action = $_POST['action'] ?? '';
try {
    global $pdo;
    if ($action === 'create') {
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem criar empresas.';
            header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
            exit;
        }
        $nome = trim($_POST['nome'] ?? '');
        $cnpj = trim($_POST['cnpj'] ?? null);
        if ($nome === '') throw new Exception('Nome é obrigatório.');
    $stmt = $pdo->prepare('INSERT INTO empresas (nome, cnpj) VALUES (?, ?)');
    $stmt->execute([$nome, $cnpj]);
    $newId = $pdo->lastInsertId();
        // Associar o criador como admin da nova empresa
        try {
            $pdo->prepare('INSERT INTO usuarios_empresas (usuario_id, empresa_id, role) VALUES (?, ?, ?)')
                ->execute([$_SESSION['user_id'], $newId, 'admin']);
            // Definir empresa corrente na sessão
            $_SESSION['current_company_id'] = intval($newId);
        } catch (Exception $e) {
            // Se a tabela pivot não existir, ignora (compatibilidade)
        }
        // If AJAX, return JSON with created empresa data
        if (is_ajax_request()) {
            $stmtX = $pdo->prepare('SELECT id, nome, cnpj, created_at FROM empresas WHERE id = ? LIMIT 1');
            $stmtX->execute([$newId]);
            $empresa = $stmtX->fetch(PDO::FETCH_ASSOC) ?: ['id' => $newId, 'nome' => $nome, 'cnpj' => $cnpj];
            json_response(['success' => true, 'message' => 'Empresa criada com sucesso.', 'empresa' => $empresa]);
        }
        $_SESSION['success_message'] = 'Empresa criada com sucesso.';
        header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
        exit;
    }

    if ($action === 'update') {
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem editar empresas.';
            header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
            exit;
        }
        $id = intval($_POST['empresa_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $cnpj = trim($_POST['cnpj'] ?? null);
        if ($id <= 0) throw new Exception('Empresa inválida.');
        $stmt = $pdo->prepare('UPDATE empresas SET nome = ?, cnpj = ? WHERE id = ?');
        $stmt->execute([$nome, $cnpj, $id]);
        // If AJAX, return updated row
        if (is_ajax_request()) {
            $stmtX = $pdo->prepare('SELECT id, nome, cnpj, created_at FROM empresas WHERE id = ? LIMIT 1');
            $stmtX->execute([$id]);
            $empresa = $stmtX->fetch(PDO::FETCH_ASSOC) ?: ['id' => $id, 'nome' => $nome, 'cnpj' => $cnpj];
            json_response(['success' => true, 'message' => 'Empresa atualizada.', 'empresa' => $empresa]);
        }
        $_SESSION['success_message'] = 'Empresa atualizada.';
        header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
        exit;
    }

    if ($action === 'delete') {
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem excluir empresas.';
            header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
            exit;
        }
        $id = intval($_POST['empresa_id'] ?? 0);
        if ($id <= 0) throw new Exception('Empresa inválida.');
        // Remover associações primeiro
        $pdo->prepare('DELETE FROM usuarios_empresas WHERE empresa_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM empresas WHERE id = ?')->execute([$id]);
        // AJAX response
        if (is_ajax_request()) {
            json_response(['success' => true, 'message' => 'Empresa excluída.', 'empresa_id' => $id]);
        }
        $_SESSION['success_message'] = 'Empresa excluída.';
        header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
        exit;
    }

    if ($action === 'associate' || $action === 'associate_user') {
        // Pode ser realizado por admin global ou admin da empresa
        $empresa_id = intval($_POST['empresa_id'] ?? 0);
        $usuario_id = intval($_POST['usuario_id'] ?? 0);
        $role = $_POST['role'] ?? 'cliente';
        if ($empresa_id <= 0 || $usuario_id <= 0) throw new Exception('Dados inválidos.');
        // verificar permissão: admin global OU admin da empresa
        $allowed = isAdmin() || user_has_role($_SESSION['user_id'], $empresa_id, 'admin');
        if (!$allowed) {
            $_SESSION['error_message'] = 'Permissão negada para associar usuários.';
            header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
            exit;
        }
        // upsert na pivot
        $stmt = $pdo->prepare('SELECT id FROM usuarios_empresas WHERE usuario_id = ? AND empresa_id = ? LIMIT 1');
        $stmt->execute([$usuario_id, $empresa_id]);
        if ($stmt->fetchColumn()) {
            $pdo->prepare('UPDATE usuarios_empresas SET role = ? WHERE usuario_id = ? AND empresa_id = ?')
                ->execute([$role, $usuario_id, $empresa_id]);
        } else {
            $pdo->prepare('INSERT INTO usuarios_empresas (usuario_id, empresa_id, role) VALUES (?, ?, ?)')
                ->execute([$usuario_id, $empresa_id, $role]);
        }
        if (is_ajax_request()) {
            json_response(['success' => true, 'message' => 'Usuário associado com sucesso.']);
        }
        $_SESSION['success_message'] = 'Usuário associado com sucesso.';
        header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
        exit;
    }

        if ($action === 'remove_association') {
                if (!isAdmin() && !user_has_role($_SESSION['user_id'], intval($_POST['empresa_id'] ?? 0), 'admin')) {
                    if (is_ajax_request()) json_response(['success' => false, 'message' => 'Permissão negada para remover associação.'], 403);
                    $_SESSION['error_message'] = 'Permissão negada para remover associação.';
                    header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
                    exit;
                }
            $empresa_id = intval($_POST['empresa_id'] ?? 0);
            $usuario_id = intval($_POST['usuario_id'] ?? 0);
            if ($empresa_id <= 0 || $usuario_id <= 0) {
                $_SESSION['error_message'] = 'Dados inválidos.';
                header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
                exit;
            }
            $pdo->prepare('DELETE FROM usuarios_empresas WHERE empresa_id = ? AND usuario_id = ?')->execute([$empresa_id, $usuario_id]);
            if (is_ajax_request()) json_response(['success' => true, 'message' => 'Associação removida.']);
            $_SESSION['success_message'] = 'Associação removida.';
            header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
            exit;
        }

    // Se ação não reconhecida
    $_SESSION['error_message'] = 'Ação inválida.';
    header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Erro: ' . $e->getMessage();
    header('Location: ' . base_url('index.php?page=gerenciar_empresas'));
    exit;
}
