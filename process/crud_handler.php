<?php
// process/crud_handler.php
require_once '../config/functions.php';
requireLogin(); 

global $pdo;
// Permite receber 'action' via POST (formulários) ou GET (botão de email)
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$user_id = $_SESSION['user_id'];

// Limpa mensagens antigas
unset($_SESSION['success_message'], $_SESSION['error_message']);

switch ($action) {
    
    // --- NOVO CASE: Salvar Configurações SMTP (Apenas Admin) ---
    case 'salvar_config_smtp':
        if (isAdmin()) {
            $data = $_POST;
            $updated = false;
            
            $settings_to_update = [
                'smtp_host', 'smtp_port', 'smtp_username', 'smtp_secure', 'email_from'
            ];

            try {
                // Prepara a query para INSERT OR UPDATE (UPSERT)
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

                foreach ($settings_to_update as $key) {
                    if (isset($data[$key])) {
                        $stmt->execute([$key, $data[$key]]);
                        $updated = true;
                    }
                }
                
                // Se a senha foi fornecida, atualiza-a separadamente
                if (!empty($data['smtp_password'])) {
                    $stmt->execute(['smtp_password', $data['smtp_password']]);
                    $updated = true;
                }

                if ($updated) {
                    $_SESSION['success_message'] = "Configurações SMTP salvas com sucesso!";
                    logAction("Configurações SMTP salvas", "system_settings");
                } else {
                    $_SESSION['error_message'] = "Nenhuma alteração detectada.";
                }

            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro ao salvar configurações: " . $e->getMessage();
            }
        }
        // Redireciona para a tela de Configuração
        $pagina_redirecionar = base_url('index.php?page=configuracoes_email');
        header("Location: $pagina_redirecionar");
        exit;
    // --- FIM NOVO CASE ---


    // --- NOVO CASE: Disparar E-mail (Apenas Admin/Contador) ---
    case 'disparar_email_lancamento':
        if (isAdmin() || isContador()) {
            $id = $_GET['id_lancamento'] ?? null;
            if (!$id) {
                $_SESSION['error_message'] = "ID do lançamento não fornecido.";
                break;
            }

            // Consulta o lançamento, empresa e cliente associado
            $sql = "SELECT l.*, c.email_contato, c.nome_responsavel 
                    FROM lancamentos l
                    JOIN empresas e ON l.id_empresa = e.id
                    JOIN clientes c ON e.id_cliente = c.id
                    WHERE l.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $lancamento = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($lancamento) {
                if (sendNotificationEmail($lancamento['email_contato'], $lancamento['nome_responsavel'], $lancamento)) {
                    $_SESSION['success_message'] = "E-mail de notificação enviado com sucesso para {$lancamento['email_contato']}!";
                    logAction("Disparou E-mail", "lancamentos", $id, "E-mail enviado manualmente para cliente: {$lancamento['email_contato']}");
                } else {
                    $_SESSION['error_message'] = "Falha ao enviar e-mail. Verifique as Configurações SMTP.";
                }
            } else {
                $_SESSION['error_message'] = "Lançamento não encontrado.";
            }
        }
        break;
    // --- FIM NOVO CASE ---


    //--- AÇÕES DE LANÇAMENTO (RESTANTE DO CÓDIGO) ---
    case 'dar_baixa_lancamento':
        if (isAdmin() || isContador()) {
            $id = $_POST['id_lancamento'];
            $sql = "UPDATE lancamentos SET status = 'pago', data_pagamento = CURDATE() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id])) {
                $_SESSION['success_message'] = "Lançamento baixado com sucesso! (Status: PAGO)";
                logAction("Baixa Lançamento", "lancamentos", $id);
            } else {
                $_SESSION['error_message'] = "Erro ao dar baixa no lançamento.";
            }
        }
        break;

    case 'confirmar_pagamento_cliente':
        if (isClient()) {
            $id = $_POST['id_lancamento'];
            // NOVOS CAMPOS OBRIGATÓRIOS DO MODAL
            $data_pagamento = $_POST['data_pagamento_cliente'] ?? null;
            $metodo = $_POST['metodo_pagamento'] ?? null;
            
            if (empty($data_pagamento) || empty($metodo)) {
                 $_SESSION['error_message'] = "Erro: Data do Pagamento e Método são obrigatórios.";
                 break;
            }

            $sql = "UPDATE lancamentos SET 
                        status = 'confirmado_cliente', 
                        data_pagamento_cliente = ?, 
                        metodo_pagamento = ? 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$data_pagamento, $metodo, $id])) {
                $_SESSION['success_message'] = "Pagamento sinalizado! Data: " . date('d/m/Y', strtotime($data_pagamento)) . ", Método: $metodo. Aguardando baixa do contador.";
                logAction("Cliente Confirmou Pagamento", "lancamentos", $id, "Lançamento ID $id sinalizado como pago pelo cliente. Data: $data_pagamento, Método: $metodo."); 
            } else {
                $_SESSION['error_message'] = "Erro ao confirmar pagamento. Tente novamente.";
            }
        }
        break;

    case 'reverter_pagamento':
         if (isAdmin() || isContador()) {
            $id = $_POST['id_lancamento'];
            // Reverte status PAGO para PENDENTE (limpa data de pagamento e contestação)
            // Também limpa os campos de confirmação do cliente para voltar ao status inicial.
            $sql = "UPDATE lancamentos SET 
                        status = 'pendente', 
                        data_pagamento = NULL, 
                        observacao_contestacao = NULL,
                        data_pagamento_cliente = NULL, 
                        metodo_pagamento = NULL 
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id])) {
                $_SESSION['success_message'] = "Lançamento revertido para Pendente! Status PAGO desfeito.";
                logAction("Reverteu Pagamento", "lancamentos", $id, "Lançamento ID $id revertido de PAGO para PENDENTE.");
            } else {
                $_SESSION['error_message'] = "Erro ao reverter lançamento.";
            }
        }
        break;
        
    case 'reverter_confirmacao_cliente':
         if (isAdmin() || isContador()) {
            $id = $_POST['id_lancamento'];
            // Reverte status CONFIRMADO_CLIENTE para PENDENTE, limpando também os detalhes informados pelo cliente.
            $sql = "UPDATE lancamentos SET status = 'pendente', data_pagamento_cliente = NULL, metodo_pagamento = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id])) {
                $_SESSION['success_message'] = "Confirmação do cliente revertida! Status: Pendente.";
                logAction("Reverteu Confirmação Cliente", "lancamentos", $id, "Lançamento ID $id teve a confirmação do cliente revertida para PENDENTE.");
            } else {
                $_SESSION['error_message'] = "Erro ao reverter confirmação.";
            }
        }
        break;
    
    // NOVO CASE: Reverter Contestação
    case 'reverter_contestacao':
         if (isAdmin() || isContador()) {
            $id = $_POST['id_lancamento'];
            $motivo_reversao = $_POST['motivo_reversao'] ?? 'Revertido pelo Contador/Admin.';

            // Reverte status CONTESTADO para PENDENTE e limpa a observação de contestação
            $sql = "UPDATE lancamentos SET status = 'pendente', observacao_contestacao = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id])) {
                $_SESSION['success_message'] = "Contestação revertida com sucesso! Lançamento voltou ao status Pendente.";
                logAction("Reverteu Contestação", "lancamentos", $id, "Motivo da reversão: $motivo_reversao");
            } else {
                $_SESSION['error_message'] = "Erro ao reverter contestação.";
            }
        }
        break;


    case 'contestar_lancamento':
        if (isClient()) {
            $id = $_POST['id_lancamento'];
            $motivo = $_POST['motivo_contestacao'];
            $sql = "UPDATE lancamentos SET status = 'contestado', observacao_contestacao = ?, data_pagamento_cliente = NULL, metodo_pagamento = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$motivo, $id])) {
                $_SESSION['success_message'] = "Lançamento contestado com sucesso!";
                logAction("Cliente Contestou Lançamento", "lancamentos", $id, "Motivo: $motivo");
            } else {
                 $_SESSION['error_message'] = "Erro ao contestar lançamento.";
            }
        }
        break;
        
    case 'cadastrar_lancamento':
        if (isAdmin() || isContador()) {
            $id_empresa = $_POST['id_empresa'];
            $descricao = $_POST['descricao'];
            $valor = $_POST['valor'];
            $tipo = $_POST['tipo'];
            $data_vencimento = $_POST['data_vencimento'];

            $sql = "INSERT INTO lancamentos (id_empresa, descricao, valor, tipo, data_vencimento, status) VALUES (?, ?, ?, ?, ?, 'pendente')";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$id_empresa, $descricao, $valor, $tipo, $data_vencimento])) {
                $id_novo = $pdo->lastInsertId();
                $_SESSION['success_message'] = "Lançamento cadastrado com sucesso!";
                logAction("Cadastro Lançamento", "lancamentos", $id_novo, "Valor: R$ $valor, Descrição: $descricao");
                
                // Disparo de email (Opcional, pode ser automático aqui ou manual via botão)
                // $stmt_cliente = $pdo->prepare("SELECT l.*, c.email_contato, c.nome_responsavel FROM lancamentos l JOIN empresas e ON l.id_empresa = e.id JOIN clientes c ON e.id_cliente = c.id WHERE l.id = ?");
                // $stmt_cliente->execute([$id_novo]);
                // $lancamento_info = $stmt_cliente->fetch();
                // if ($lancamento_info) {
                //     sendNotificationEmail($lancamento_info['email_contato'], $lancamento_info['nome_responsavel'], $lancamento_info);
                // }
            } else {
                $_SESSION['error_message'] = "Erro ao cadastrar lançamento.";
            }
        }
        break;
    
    case 'editar_lancamento':
        if (isAdmin() || isContador()) {
            $id = $_POST['id_lancamento'];
            $id_empresa_novo = $_POST['id_empresa'];
            $descricao_novo = $_POST['descricao'];
            $valor_novo = $_POST['valor'];
            $tipo_novo = $_POST['tipo'];
            $data_vencimento_novo = $_POST['data_vencimento'];

            // 1. AUDITORIA: Busca dados antigos e status
            $stmt_old = $pdo->prepare("SELECT id_empresa, descricao, valor, tipo, data_vencimento, status FROM lancamentos WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

            // Verificação de segurança: Só edita se o status NÃO for pago
            if ($old_data['status'] === 'pago') {
                 $_SESSION['error_message'] = "Erro: Lançamento pago deve ser revertido antes de ser editado.";
                 break;
            }
            
            // 2. AUDITORIA: Compara e monta a string de detalhes
            $detalhes_log = [];
            
            if ($old_data['id_empresa'] !== $id_empresa_novo) {
                 $stmt_empresa = $pdo->prepare("SELECT razao_social FROM empresas WHERE id = ?");
                 $stmt_empresa->execute([$id_empresa_novo]);
                 $nome_empresa_novo = $stmt_empresa->fetchColumn() ?? 'N/D';
                 $stmt_empresa->execute([$old_data['id_empresa']]);
                 $nome_empresa_antigo = $stmt_empresa->fetchColumn() ?? 'N/D';
                 $detalhes_log[] = "Empresa: {$nome_empresa_antigo} -> {$nome_empresa_novo}";
            }
            if ($old_data['descricao'] !== $descricao_novo) {
                 $detalhes_log[] = "Descrição: {$old_data['descricao']} -> {$descricao_novo}";
            }
            if ($old_data['valor'] != $valor_novo) {
                 $detalhes_log[] = "Valor: R$ " . number_format($old_data['valor'], 2, ',', '.') . " -> R$ " . number_format($valor_novo, 2, ',', '.');
            }
            if ($old_data['tipo'] !== $tipo_novo) {
                 $detalhes_log[] = "Tipo: {$old_data['tipo']} -> {$tipo_novo}";
            }
            if ($old_data['data_vencimento'] !== $data_vencimento_novo) {
                 $detalhes_log[] = "Vencimento: " . date('d/m/Y', strtotime($old_data['data_vencimento'])) . " -> " . date('d/m/Y', strtotime($data_vencimento_novo));
            }

            if (empty($detalhes_log)) {
                 $_SESSION['success_message'] = "Lançamento não alterado (nenhuma mudança detectada).";
                 break;
            }
            
            $log_details_string = "Campos alterados: " . implode('; ', $detalhes_log);

            // 3. Atualiza o banco de dados
            // Resetamos campos de confirmação do cliente ou contestação, se o registro for editado
            $sql = "UPDATE lancamentos SET 
                        id_empresa = ?, descricao = ?, valor = ?, tipo = ?, data_vencimento = ?, 
                        status = 'pendente', observacao_contestacao = NULL, data_pagamento_cliente = NULL, metodo_pagamento = NULL
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id_empresa_novo, $descricao_novo, $valor_novo, $tipo_novo, $data_vencimento_novo, $id])) {
                $_SESSION['success_message'] = "Lançamento atualizado com sucesso! O status foi redefinido para 'Pendente'.";
                logAction("Edição Lançamento", "lancamentos", $id, $log_details_string);
            } else {
                $_SESSION['error_message'] = "Erro ao atualizar lançamento.";
            }
        }
        break;


    // --- AÇÕES DE CADASTRO (EMPRESA) ---

    case 'cadastrar_empresa':
        if (isAdmin() || isContador()) {
            $id_cliente = $_POST['id_cliente'];
            $cnpj = $_POST['cnpj'];
            $razao_social = $_POST['razao_social'];
            $data_abertura = !empty($_POST['data_abertura']) ? $_POST['data_abertura'] : null;

            $sql = "INSERT INTO empresas (id_cliente, cnpj, razao_social, nome_fantasia, data_abertura) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id_cliente, $cnpj, $_POST['razao_social'], $_POST['nome_fantasia'], $data_abertura])) {
                $id_novo = $pdo->lastInsertId();
                $_SESSION['success_message'] = "Empresa cadastrada com sucesso!";
                logAction("Cadastro Empresa", "empresas", $id_novo, "CNPJ: $cnpj"); 
            } else {
                $_SESSION['error_message'] = "Erro ao cadastrar empresa.";
            }
        }
        break;

    case 'editar_empresa':
        if (isAdmin() || isContador()) {
             $id_empresa = $_POST['id_empresa'];
             $data_abertura = !empty($_POST['data_abertura']) ? $_POST['data_abertura'] : null;

             $sql_update = "UPDATE empresas SET id_cliente = ?, cnpj = ?, razao_social = ?, nome_fantasia = ?, data_abertura = ? WHERE id = ?";
             $stmt_update = $pdo->prepare($sql_update);
             
             if ($stmt_update->execute([$_POST['id_cliente'], $_POST['cnpj'], $_POST['razao_social'], $_POST['nome_fantasia'], $data_abertura, $id_empresa])) {
                 $_SESSION['success_message'] = "Empresa atualizada com sucesso!";
                 logAction("Edição Empresa", "empresas", $id_empresa, "CNPJ: " . $_POST['cnpj']);
             } else {
                 $_SESSION['error_message'] = "Erro ao atualizar empresa.";
             }
        }
        break;

    case 'deletar_empresa':
        if (isAdmin() || isContador()) {
             $id_empresa = $_POST['id_empresa'];
             $sql = "DELETE FROM empresas WHERE id = ?";
             $stmt = $pdo->prepare($sql);
             if ($stmt->execute([$id_empresa])) {
                 $_SESSION['success_message'] = "Empresa excluída com sucesso!";
                 logAction("Exclusão Empresa", "empresas", $id_empresa);
             } else {
                 $_SESSION['error_message'] = "Erro ao excluir empresa.";
             }
        }
        break;


    // --- AÇÕES DE CADASTRO (CLIENTE) ---
    
    case 'cadastrar_cliente':
         if (isAdmin() || isContador()) {
            $sql = "INSERT INTO clientes (nome_responsavel, email_contato, telefone) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$_POST['nome_responsavel'], $_POST['email_contato'], $_POST['telefone']])) {
                $id_novo = $pdo->lastInsertId();
                $_SESSION['success_message'] = "Cliente cadastrado com sucesso!";
                logAction("Cadastro Cliente", "clientes", $id_novo, "Nome: " . $_POST['nome_responsavel']);
            } else {
                 $_SESSION['error_message'] = "Erro ao cadastrar cliente.";
            }
         }
        break;

    case 'editar_cliente':
         if (isAdmin() || isContador()) {
            $id = $_POST['id_cliente'];
            $nome_novo = $_POST['nome_responsavel'];
            $email_novo = $_POST['email_contato'];
            $telefone_novo = $_POST['telefone'];

            // 1. AUDITORIA: Busca dados antigos
            $stmt_old = $pdo->prepare("SELECT nome_responsavel, email_contato, telefone FROM clientes WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

            // 2. AUDITORIA: Compara e monta a string de detalhes
            $detalhes_log = [];
            if ($old_data['nome_responsavel'] !== $nome_novo) {
                $detalhes_log[] = "Nome: {$old_data['nome_responsavel']} -> {$nome_novo}";
            }
            if ($old_data['email_contato'] !== $email_novo) {
                $detalhes_log[] = "Email: {$old_data['email_contato']} -> {$email_novo}";
            }
            if (($old_data['telefone'] ?? '') !== ($telefone_novo ?? '')) {
                 $detalhes_log[] = "Telefone: " . ($old_data['telefone'] ?? 'N/D') . " -> " . ($telefone_novo ?? 'N/D');
            }
            
            // Se nada mudou, cancela o log e a atualização
            if (empty($detalhes_log)) {
                 $_SESSION['success_message'] = "Cliente não alterado (nenhuma mudança detectada).";
                 break;
            }
            
            $log_details_string = "Campos alterados: " . implode('; ', $detalhes_log);


            // 3. Atualiza o banco de dados
            $sql = "UPDATE clientes SET nome_responsavel = ?, email_contato = ?, telefone = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nome_novo, $email_novo, $telefone_novo, $id])) {
                $_SESSION['success_message'] = "Cliente atualizado com sucesso!";
                logAction("Edição Cliente", "clientes", $id, $log_details_string);
            } else {
                $_SESSION['error_message'] = "Erro ao atualizar cliente.";
            }
         }
        break;

    case 'deletar_cliente':
        if (isAdmin()) {
            $id = $_POST['id_cliente'];
            $stmt_name = $pdo->prepare("SELECT nome_responsavel FROM clientes WHERE id = ?");
            $stmt_name->execute([$id]);
            $cliente_nome = $stmt_name->fetchColumn() ?? "ID $id";
            
            $sql = "DELETE FROM clientes WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id])) {
                $_SESSION['success_message'] = "Cliente ($cliente_nome) e todos os seus dados (empresas, lançamentos) foram excluídos com sucesso!";
                logAction("Exclusão Cliente", "clientes", $id, "Cliente excluído: $cliente_nome");
            } else {
                 $_SESSION['error_message'] = "Erro ao excluir cliente.";
            }
        }
        break;

    // --- AÇÕES DE CADASTRO (USUÁRIO) ---
    
    case 'cadastrar_usuario':
        if (isAdmin()) {
            $nome = $_POST['nome'];
            $email = $_POST['email'];
            $telefone = $_POST['telefone'] ?? null;
            $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT); 
            $tipo = $_POST['tipo_usuario'];
            $id_cliente_associado = ($tipo == 'cliente' && !empty($_POST['id_cliente_associado'])) ? $_POST['id_cliente_associado'] : null;
            
            $sql = "INSERT INTO usuarios (nome, email, telefone, senha, tipo, id_cliente_associado) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$nome, $email, $telefone, $senha, $tipo, $id_cliente_associado])) {
                $id_novo_usuario = $pdo->lastInsertId();
                $_SESSION['success_message'] = "Usuário cadastrado com sucesso!";
                logAction("Cadastro Usuário", "usuarios", $id_novo_usuario, "Email: $email, Tipo: $tipo");
                
                if ($tipo == 'contador' && !empty($_POST['id_clientes_associados'])) {
                    $ids_clientes_assoc = $_POST['id_clientes_associados'];
                    $sql_assoc = "INSERT INTO contador_clientes_assoc (id_usuario_contador, id_cliente) VALUES (?, ?)";
                    $stmt_assoc = $pdo->prepare($sql_assoc);
                    foreach ($ids_clientes_assoc as $id_cliente) {
                        $stmt_assoc->execute([$id_novo_usuario, $id_cliente]);
                    }
                }
            } else {
                 $_SESSION['error_message'] = "Erro ao cadastrar usuário.";
            }
        }
        break;
    
    case 'editar_usuario':
        if (isAdmin()) {
            $id_usuario_edit = $_POST['id_usuario'];
            $nome = $_POST['nome'];
            $email = $_POST['email'];
            $telefone = $_POST['telefone'] ?? null;
            $tipo = $_POST['tipo_usuario'];
            $id_cliente_associado = ($tipo == 'cliente' && !empty($_POST['id_cliente_associado'])) ? $_POST['id_cliente_associado'] : null;

            $stmt_old = $pdo->prepare("SELECT nome, email, telefone, tipo, id_cliente_associado FROM usuarios WHERE id = ?");
            $stmt_old->execute([$id_usuario_edit]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);


            $sql_senha_part = "";
            $params_sql = [$nome, $email, $telefone, $tipo, $id_cliente_associado];
            
            if (!empty($_POST['nova_senha'])) {
                $novo_hash = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
                $sql_senha_part = ", senha = ?";
                $params_sql[] = $novo_hash;
            }
            
            $params_sql[] = $id_usuario_edit; 

            $sql = "UPDATE usuarios SET nome = ?, email = ?, telefone = ?, tipo = ?, id_cliente_associado = ? $sql_senha_part WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($params_sql)) {
                 $_SESSION['success_message'] = "Usuário atualizado com sucesso!";
                 logAction("Edição Usuário", "usuarios", $id_usuario_edit, "Email: $email, Tipo: $tipo"); 

                $stmt_delete_assoc = $pdo->prepare("DELETE FROM contador_clientes_assoc WHERE id_usuario_contador = ?");
                $stmt_delete_assoc->execute([$id_usuario_edit]);

                if ($tipo == 'contador' && !empty($_POST['id_clientes_associados'])) {
                    $ids_clientes_assoc = $_POST['id_clientes_associados'];
                    $sql_assoc = "INSERT INTO contador_clientes_assoc (id_usuario_contador, id_cliente) VALUES (?, ?)";
                    $stmt_assoc = $pdo->prepare($sql_assoc);
                    foreach ($ids_clientes_assoc as $id_cliente) {
                        $stmt_assoc->execute([$id_usuario_edit, $id_cliente]);
                    }
                }
            } else {
                $_SESSION['error_message'] = "Erro ao atualizar usuário.";
            }
        }
        break;

    case 'deletar_usuario':
        if (isAdmin()) {
            $id = $_POST['id_usuario'];
            if ($id == $user_id) {
                $_SESSION['error_message'] = "Você não pode excluir seu próprio usuário.";
                break; 
            }
            
            $sql = "DELETE FROM usuarios WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id])) {
                 $_SESSION['success_message'] = "Usuário excluído com sucesso!";
                 logAction("Exclusão Usuário", "usuarios", $id);
            } else {
                 $_SESSION['error_message'] = "Erro ao excluir usuário.";
            }
        }
        break;

    // --- AÇÕES DE PERFIL (MEU PERFIL) ---
    
    case 'atualizar_perfil':
        $nome = $_POST['nome'];
        $email = $_POST['email'];
        $telefone = $_POST['telefone'];
        
        $sql = "UPDATE usuarios SET nome = ?, email = ?, telefone = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$nome, $email, $telefone, $user_id])) {
            logAction("Atualização de Perfil", "usuarios", $user_id);
            $_SESSION['user_nome'] = $nome; 
            $_SESSION['success_message'] = "Perfil atualizado com sucesso!";
        } else {
             $_SESSION['error_message'] = "Erro ao atualizar perfil.";
        }
        break;

    case 'alterar_senha':
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];

        if ($nova_senha !== $confirmar_senha) {
            $_SESSION['error_message'] = "A nova senha e a confirmação não coincidem.";
            break;
        }

        $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $hash_atual = $stmt->fetchColumn();

        if (password_verify($senha_atual, $hash_atual)) {
            $novo_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $sql_update = "UPDATE usuarios SET senha = ? WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([$novo_hash, $user_id]);
            
            logAction("Alteração de Senha", "usuarios", $user_id);
            $_SESSION['success_message'] = "Senha alterada com sucesso!";
        } else {
            $_SESSION['error_message'] = "A senha atual está incorreta.";
        }
        break;
        

    case 'criar_cobranca':
        if (isAdmin() || isContador()) {
            $id_empresa = $_POST['id_empresa'];
            $data_competencia = $_POST['data_competencia'];
            $data_vencimento = $_POST['data_vencimento'];
            $valor = $_POST['valor'];
            $id_forma_pagamento = $_POST['id_forma_pagamento'];
            $descricao = $_POST['descricao'];
            $contexto_pagamento = $_POST['contexto_pagamento'] ?? null;

            try {
                $sql = "INSERT INTO cobrancas (id_empresa, data_competencia, data_vencimento, valor, id_forma_pagamento, descricao, contexto_pagamento, status_pagamento) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendente')";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id_empresa, $data_competencia, $data_vencimento, $valor, $id_forma_pagamento, $descricao, $contexto_pagamento])) {
                    $_SESSION['success_message'] = "Cobrança gerada com sucesso!";
                    logAction("Gerou Cobrança", "cobrancas", $pdo->lastInsertId(), "Valor: R$ $valor para empresa ID: $id_empresa");
                } else {
                    $_SESSION['error_message'] = "Erro ao gerar cobrança.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
            }
        }
        break;

    case 'excluir_cobranca':
        if (isAdmin() || isContador()) {
            $id = $_GET['id'] ?? null;
            if ($id) {
                try {
                    $sql = "DELETE FROM cobrancas WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$id])) {
                        $_SESSION['success_message'] = "Cobrança excluída com sucesso!";
                        logAction("Excluiu Cobrança", "cobrancas", $id);
                    } else {
                        $_SESSION['error_message'] = "Erro ao excluir cobrança.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    case 'editar_cobranca':
        if (isAdmin() || isContador()) {
            $id = $_POST['id_cobranca'];
            $id_empresa = $_POST['id_empresa'];
            $data_competencia = $_POST['data_competencia'];
            $data_vencimento = $_POST['data_vencimento'];
            $valor = $_POST['valor'];
            $id_forma_pagamento = $_POST['id_forma_pagamento'];
            $descricao = $_POST['descricao'];
            $contexto_pagamento = $_POST['contexto_pagamento'] ?? null;

            try {
                $sql = "UPDATE cobrancas SET 
                            id_empresa = ?, 
                            data_competencia = ?, 
                            data_vencimento = ?, 
                            valor = ?, 
                            id_forma_pagamento = ?, 
                            descricao = ?, 
                            contexto_pagamento = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id_empresa, $data_competencia, $data_vencimento, $valor, $id_forma_pagamento, $descricao, $contexto_pagamento, $id])) {
                    $_SESSION['success_message'] = "Cobrança atualizada com sucesso!";
                    logAction("Editou Cobrança", "cobrancas", $id, "Valor: R$ $valor, Descrição: $descricao");
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $_SESSION['error_message'] = "Erro ao atualizar cobrança: " . ($errorInfo[2] ?? "Erro desconhecido.");
                    error_log("Erro ao atualizar cobrança (ID: $id): " . ($errorInfo[2] ?? "Erro desconhecido."));
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
            }
            $pagina_redirecionar = base_url('index.php?page=cobrancas') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

    case 'marcar_pago_cobranca':
        if (isAdmin() || isContador()) {
            $id = $_POST['id_cobranca'] ?? null;
            $data_pagamento = $_POST['data_pagamento'] ?? null;

            if (!$id || !$data_pagamento) {
                $_SESSION['error_message'] = "Erro: ID da cobrança ou data de pagamento não fornecidos.";
                break;
            }

            try {
                // 1. Obter a data de vencimento da cobrança
                $stmt_vencimento = $pdo->prepare("SELECT data_vencimento FROM cobrancas WHERE id = ?");
                $stmt_vencimento->execute([$id]);
                $cobranca = $stmt_vencimento->fetch(PDO::FETCH_ASSOC);

                if (!$cobranca) {
                    $_SESSION['error_message'] = "Erro: Cobrança não encontrada.";
                    break;
                }

                $data_vencimento = new DateTime($cobranca['data_vencimento']);
                $data_pagamento_obj = new DateTime($data_pagamento);

                $status_pagamento = 'Pago';
                if ($data_pagamento_obj > $data_vencimento) {
                    $status_pagamento = 'Pago em atraso';
                }

                // 2. Atualizar a cobrança com a data de pagamento e o status determinado
                $sql = "UPDATE cobrancas SET status_pagamento = ?, data_pagamento = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$status_pagamento, $data_pagamento, $id])) {
                    $_SESSION['success_message'] = "Cobrança marcada como $status_pagamento em " . date('d/m/Y', strtotime($data_pagamento)) . "!";
                    logAction("Baixa Cobrança", "cobrancas", $id, "Status: $status_pagamento, Data Pagamento: $data_pagamento");
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $_SESSION['error_message'] = "Erro ao dar baixa na cobrança: " . ($errorInfo[2] ?? "Erro desconhecido.");
                    error_log("Erro ao dar baixa na cobrança (ID: $id): " . ($errorInfo[2] ?? "Erro desconhecido."));
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
            }
            $pagina_redirecionar = base_url('index.php?page=cobrancas') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

    case 'reverter_pago_cobranca':
        if (isAdmin() || isContador()) {
            $id = $_POST['id_cobranca'] ?? null;
            if ($id) {
                $sql = "UPDATE cobrancas SET status_pagamento = 'Pendente', data_pagamento = NULL WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id])) {
                    $_SESSION['success_message'] = "Pagamento da cobrança revertido para Pendente!";
                    logAction("Reverteu Baixa Cobrança", "cobrancas", $id);
                } else {
                    $_SESSION['error_message'] = "Erro ao reverter a baixa.";
                }
            }
            $pagina_redirecionar = base_url('index.php?page=cobrancas') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

    // --- AÇÕES DE FORMAS DE PAGAMENTO (Admin) ---

    case 'criar_forma_pagamento':
        if (isAdmin()) {
            $nome = $_POST['nome'];
            $icone = $_POST['icone_bootstrap'] ?? null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            try {
                $sql = "INSERT INTO formas_pagamento (nome, icone_bootstrap, ativo) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$nome, $icone, $ativo])) {
                    $_SESSION['success_message'] = "Forma de pagamento criada com sucesso!";
                    logAction("Criou Forma de Pagamento", "formas_pagamento", $pdo->lastInsertId(), "Nome: $nome");
                } else {
                    $_SESSION['error_message'] = "Erro ao criar forma de pagamento.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error_message'] = "Erro: Já existe uma forma de pagamento com este nome.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    case 'editar_forma_pagamento':
        if (isAdmin()) {
            $id = $_POST['id'];
            $nome = $_POST['nome'];
            $icone = $_POST['icone_bootstrap'] ?? null;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            try {
                $sql = "UPDATE formas_pagamento SET nome = ?, icone_bootstrap = ?, ativo = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$nome, $icone, $ativo, $id])) {
                    $_SESSION['success_message'] = "Forma de pagamento atualizada com sucesso!";
                    logAction("Editou Forma de Pagamento", "formas_pagamento", $id, "Nome: $nome");
                } else {
                    $_SESSION['error_message'] = "Erro ao atualizar forma de pagamento.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error_message'] = "Erro: Já existe uma forma de pagamento com este nome.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    case 'excluir_forma_pagamento':
        if (isAdmin()) {
            $id = $_GET['id'];
            try {
                $sql = "DELETE FROM formas_pagamento WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id])) {
                    $_SESSION['success_message'] = "Forma de pagamento excluída com sucesso!";
                    logAction("Excluiu Forma de Pagamento", "formas_pagamento", $id);
                } else {
                    $_SESSION['error_message'] = "Erro ao excluir forma de pagamento.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1451) {
                    $_SESSION['error_message'] = "Erro: Esta forma de pagamento não pode ser excluída pois está sendo utilizada em uma ou mais cobranças.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    default:
        // Caso a action não seja reconhecida
        if ($action) {
             $_SESSION['error_message'] = "Ação '$action' desconhecida.";
        }
        break;
}

// Redireciona de volta para a página anterior
$pagina_anterior = $_SERVER['HTTP_REFERER'] ?? base_url('index.php?page=dashboard');
header("Location: $pagina_anterior");
exit;