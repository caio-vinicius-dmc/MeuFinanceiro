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


    //--- AÇÕES DE LANÇAMENTO ---
    case 'cadastrar_lancamento':
        if (isAdmin() || isContador() || isClient()) { // All can create
            $id_empresa = $_POST['id_empresa'];
            $descricao = $_POST['descricao'];
            $valor = $_POST['valor'];
            $tipo = $_POST['tipo']; // Assuming 'tipo' is still relevant for categorization
            $data_vencimento = $_POST['data_vencimento'];
            $data_competencia = $_POST['data_competencia'] ?? null;
            $metodo_pagamento = $_POST['metodo_pagamento'] ?? null;

            try {
                // Armazenamos o status inicial como 'pendente' para compatibilidade com a lógica de exibição
                $sql = "INSERT INTO lancamentos (id_empresa, descricao, valor, tipo, data_vencimento, data_competencia, metodo_pagamento, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente')";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute([$id_empresa, $descricao, $valor, $tipo, $data_vencimento, $data_competencia, $metodo_pagamento])) {
                    $id_novo = $pdo->lastInsertId();
                    $_SESSION['success_message'] = "Lançamento cadastrado com sucesso!";
                    logAction("Cadastro Lançamento", "lancamentos", $id_novo, "Valor: R$ $valor, Descrição: $descricao, Status: pendente");
                } else {
                    $_SESSION['error_message'] = "Erro ao cadastrar lançamento.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
            }
            $pagina_redirecionar = base_url('index.php?page=lancamentos') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

        case 'enviar_cobranca_email':
            // Envia por email uma cobrança específica para o contato do cliente/empresa
            $id_cobranca = $_GET['id'] ?? $_POST['id'] ?? null;
            if (!$id_cobranca) {
                $_SESSION['error_message'] = 'ID da cobrança ausente.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            try {
                // Busca dados da cobrança com empresa e cliente
                $sql = "SELECT cob.*, emp.razao_social, emp.id_cliente, cli.nome_responsavel, cli.email_contato
                        FROM cobrancas cob
                        JOIN empresas emp ON cob.id_empresa = emp.id
                        LEFT JOIN clientes cli ON emp.id_cliente = cli.id
                        WHERE cob.id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$id_cobranca]);
                $cob = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$cob) {
                    $_SESSION['error_message'] = 'Cobrança não encontrada.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }

                // Permissões: admin/contador podem enviar qualquer cobrança; cliente só sua
                if (isClient()) {
                    $id_cliente_logado = $_SESSION['id_cliente_associado'] ?? null;
                    if ($id_cliente_logado != $cob['id_cliente']) {
                        $_SESSION['error_message'] = 'Você não tem permissão para enviar esta cobrança.';
                        header('Location: ' . base_url('index.php?page=cobrancas'));
                        exit;
                    }
                }

                // Destinatário: email do cliente vinculado à empresa
                $toEmail = $cob['email_contato'] ?? null;
                $toName = $cob['nome_responsavel'] ?? ($cob['razao_social'] ?? 'Cliente');

                if (empty($toEmail)) {
                    $_SESSION['error_message'] = 'Email do cliente não encontrado. Verifique cadastro do cliente.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }

                // Prepara dados no formato esperado pela função de notificação
                $lancamento_like = [
                    'descricao' => $cob['descricao'] ?? 'Cobrança',
                    'valor' => $cob['valor'],
                    'data_vencimento' => $cob['data_vencimento'],
                    'tipo' => 'receita'
                ];

                $sent = sendNotificationEmail($toEmail, $toName, $lancamento_like);

                if ($sent) {
                    $_SESSION['success_message'] = 'Email enviado com sucesso para ' . htmlspecialchars($toEmail);
                    logAction('Enviou Cobrança por Email', 'cobrancas', $id_cobranca, 'Email para: ' . $toEmail);
                } else {
                    $_SESSION['error_message'] = 'Falha no envio do email. Verifique as configurações SMTP.';
                    logAction('Falha no envio de Cobrança por Email', 'cobrancas', $id_cobranca, 'Tentativa para: ' . $toEmail);
                }

            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Erro ao tentar enviar email: ' . $e->getMessage();
            }

            header('Location: ' . base_url('index.php?page=cobrancas'));
            exit;
            break;

        case 'test_smtp':
            // Testar envio SMTP usando as configurações salvas
            if (!isAdmin()) {
                $_SESSION['error_message'] = 'Apenas administradores podem testar a conexão SMTP.';
                header('Location: ' . base_url('index.php?page=configuracoes_email'));
                exit;
            }

            $settings = getSmtpSettings();
            $to = $settings['smtp_username'] ?? $settings['email_from'];
            $toName = 'Administrador';

            if (empty($to)) {
                $_SESSION['error_message'] = 'Nenhum destinatário válido encontrado (smtp_username ou email_from). Configure antes de testar.';
                header('Location: ' . base_url('index.php?page=configuracoes_email'));
                exit;
            }

            $testLanc = [
                'descricao' => 'Teste de Conexão SMTP',
                'valor' => 0,
                'data_vencimento' => date('Y-m-d'),
                'tipo' => 'receita'
            ];

            $sent = sendNotificationEmail($to, $toName, $testLanc);
            if ($sent) {
                $_SESSION['success_message'] = 'Teste de SMTP enviado com sucesso para ' . htmlspecialchars($to);
            } else {
                $_SESSION['error_message'] = 'Falha ao enviar e-mail de teste. Verifique as configurações SMTP e execute composer install.';
            }
            header('Location: ' . base_url('index.php?page=configuracoes_email'));
            exit;
            break;

    case 'editar_lancamento':
        if (isAdmin() || isContador() || isClient()) { // All can edit
            $id = $_POST['id_lancamento'];
            $id_empresa_novo = $_POST['id_empresa'];
            $descricao_novo = $_POST['descricao'];
            $valor_novo = $_POST['valor'];
            $tipo_novo = $_POST['tipo'];
            $data_vencimento_novo = $_POST['data_vencimento'];
            $data_competencia_novo = $_POST['data_competencia'] ?? null;
            $metodo_pagamento_novo = $_POST['metodo_pagamento'] ?? null;
            $status_novo = $_POST['status']; // New: status can be edited

            // 1. AUDITORIA: Busca dados antigos
            $stmt_old = $pdo->prepare("SELECT id_empresa, descricao, valor, tipo, data_vencimento, data_competencia, metodo_pagamento, status FROM lancamentos WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
            
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
            if (($old_data['data_competencia'] ?? null) !== ($data_competencia_novo ?? null)) {
                $detalhes_log[] = "Competência: " . ($old_data['data_competencia'] ? date('d/m/Y', strtotime($old_data['data_competencia'])) : 'N/D') . " -> " . ($data_competencia_novo ? date('d/m/Y', strtotime($data_competencia_novo)) : 'N/D');
            }
            if (($old_data['metodo_pagamento'] ?? null) !== ($metodo_pagamento_novo ?? null)) {
                $detalhes_log[] = "Forma Pgto: " . ($old_data['metodo_pagamento'] ?? 'N/D') . " -> " . ($metodo_pagamento_novo ?? 'N/D');
            }
            if ($old_data['status'] !== $status_novo) {
                 $detalhes_log[] = "Status: {$old_data['status']} -> {$status_novo}";
            }

            if (empty($detalhes_log)) {
                 $_SESSION['success_message'] = "Lançamento não alterado (nenhuma mudança detectada).";
                 $pagina_redirecionar = base_url('index.php?page=lancamentos') . '&_t=' . time();
                 header("Location: $pagina_redirecionar");
                 exit;
            }
            
            $log_details_string = "Campos alterados: " . implode('; ', $detalhes_log);

            // 3. Atualiza o banco de dados
            $sql = "UPDATE lancamentos SET 
                        id_empresa = ?, descricao = ?, valor = ?, tipo = ?, data_vencimento = ?, data_competencia = ?, metodo_pagamento = ?, status = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id_empresa_novo, $descricao_novo, $valor_novo, $tipo_novo, $data_vencimento_novo, $data_competencia_novo, $metodo_pagamento_novo, $status_novo, $id])) {
                $_SESSION['success_message'] = "Lançamento atualizado com sucesso!";
                logAction("Edição Lançamento", "lancamentos", $id, $log_details_string);
            } else {
                $_SESSION['error_message'] = "Erro ao atualizar lançamento.";
            }
            $pagina_redirecionar = base_url('index.php?page=lancamentos') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

    case 'excluir_lancamento': // Assuming this action already exists or needs to be added
        if (isAdmin() || isContador() || isClient()) { // All can delete
            $id = $_GET['id'] ?? null;
            if ($id) {
                try {
                    $sql = "DELETE FROM lancamentos WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$id])) {
                        $_SESSION['success_message'] = "Lançamento excluído com sucesso!";
                        logAction("Excluiu Lançamento", "lancamentos", $id);
                    } else {
                        $_SESSION['error_message'] = "Erro ao excluir lançamento.";
                    }
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
            $pagina_redirecionar = base_url('index.php?page=lancamentos') . '&_t=' . time();
            header("Location: $pagina_redirecionar");
            exit;
        }
        break;

    case 'atualizar_status_lancamento':
        if (isAdmin() || isContador() || isClient()) {
            $id = $_POST['id_lancamento'];
            $novo_status = $_POST['status']; // Expected to be 'Pago' or 'Em aberto'
            $data_pagamento = $_POST['data_pagamento'] ?? null;
            $metodo_pagamento = $_POST['metodo_pagamento'] ?? null;

            error_log("DEBUG: atualizar_status_lancamento - ID: $id, Novo Status Recebido: $novo_status");

            // Get current lancamento data for comparison and logging
            $stmt_old_data = $pdo->prepare("SELECT status, data_vencimento, data_pagamento, metodo_pagamento FROM lancamentos WHERE id = ?");
            $stmt_old_data->execute([$id]);
            $old_lancamento_data = $stmt_old_data->fetch(PDO::FETCH_ASSOC);

            if (!$old_lancamento_data) {
                $_SESSION['error_message'] = "Lançamento não encontrado.";
                error_log("DEBUG: Lançamento ID $id não encontrado.");
                header("Location: " . base_url('index.php?page=lancamentos') . '&_t=' . time());
                exit;
            }

            $old_status = $old_lancamento_data['status'];
            $old_data_pagamento = $old_lancamento_data['data_pagamento'];
            $old_metodo_pagamento = $old_lancamento_data['metodo_pagamento'];

            error_log("DEBUG: Old Status: $old_status, Old Data Pagamento: $old_data_pagamento, Old Metodo Pagamento: $old_metodo_pagamento");

            $log_details = [];
            $update_fields = [];
            $update_params = [];

            // Handle status change
            if ($novo_status == 'Pago') {
                if ($old_status != 'pago') {
                    $update_fields[] = "status = ?";
                    $update_params[] = 'pago';
                    $log_details[] = "Status: $old_status -> pago";
                }
                // Only update data_pagamento and metodo_pagamento if marking as paid
                if ($data_pagamento && $data_pagamento != $old_data_pagamento) {
                    $update_fields[] = "data_pagamento = ?";
                    $update_params[] = $data_pagamento;
                    $log_details[] = "Data Pagamento: " . ($old_data_pagamento ?? 'N/D') . " -> $data_pagamento";
                }
                if ($metodo_pagamento && $metodo_pagamento != $old_metodo_pagamento) {
                    $update_fields[] = "metodo_pagamento = ?";
                    $update_params[] = $metodo_pagamento;
                    $log_details[] = "Forma Pgto: " . ($old_metodo_pagamento ?? 'N/D') . " -> $metodo_pagamento";
                }
            } elseif ($novo_status == 'Em aberto') {
                
                // Se o status antigo era 'pago', forçamos a mudança para 'pendente' e limpamos os campos de pagamento.
                // Adicionamos os campos de atualização diretamente aqui.
                if (strtolower($old_status) == 'pago') {
                    $update_fields[] = "status = ?";
                    $update_params[] = 'pendente';
                    $log_details[] = "Status: $old_status -> pendente";

                    // Limpa data_pagamento
                    $update_fields[] = "data_pagamento = NULL";
                    $log_details[] = "Data Pagamento: " . ($old_data_pagamento ?? 'N/D') . " -> NULL";
                    
                    // Limpa metodo_pagamento
                    $update_fields[] = "metodo_pagamento = NULL";
                    $log_details[] = "Forma Pgto: " . ($old_metodo_pagamento ?? 'N/D') . " -> NULL";
                }
            }

            if (empty($update_fields)) {
                $_SESSION['success_message'] = "Nenhuma alteração necessária para o lançamento.";
                header("Location: " . base_url('index.php?page=lancamentos') . '&_t=' . time());
                exit;
            }

            try {
                $sql = "UPDATE lancamentos SET " . implode(", ", $update_fields) . " WHERE id = ?";
                $update_params[] = $id;
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute($update_params)) {
                    $_SESSION['success_message'] = "Lançamento atualizado com sucesso!";
                    logAction("Atualizou Lançamento", "lancamentos", $id, implode("; ", $log_details));
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $_SESSION['error_message'] = "Erro ao atualizar lançamento: " . ($errorInfo[2] ?? "Erro desconhecido.");
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
            }
            header("Location: " . base_url('index.php?page=lancamentos') . '&_t=' . time());
            exit;
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
            $acesso_lancamentos = ($tipo == 'cliente' && isset($_POST['acesso_lancamentos'])) ? 1 : 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;
            
            $sql = "INSERT INTO usuarios (nome, email, telefone, senha, tipo, id_cliente_associado, acesso_lancamentos, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$nome, $email, $telefone, $senha, $tipo, $id_cliente_associado, $acesso_lancamentos, $ativo])) {
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
            $acesso_lancamentos = ($tipo == 'cliente' && isset($_POST['acesso_lancamentos'])) ? 1 : 0;
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            $stmt_old = $pdo->prepare("SELECT nome, email, telefone, tipo, id_cliente_associado, acesso_lancamentos FROM usuarios WHERE id = ?");
            $stmt_old->execute([$id_usuario_edit]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);

            // Segurança: não permitir que um usuário desative a si próprio via formulário (evita lockout)
            if ($id_usuario_edit == $user_id && $ativo == 0) {
                $ativo = 1;
                $_SESSION['error_message'] = "Ação não permitida: você não pode desativar seu próprio usuário. Mantendo ativo.";
            }


            $sql_senha_part = "";
            $params_sql = [$nome, $email, $telefone, $tipo, $id_cliente_associado, $acesso_lancamentos, $ativo];
            
            if (!empty($_POST['nova_senha'])) {
                $novo_hash = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
                $sql_senha_part = ", senha = ?";
                $params_sql[] = $novo_hash;
            }
            
            $params_sql[] = $id_usuario_edit; 

            $sql = "UPDATE usuarios SET nome = ?, email = ?, telefone = ?, tipo = ?, id_cliente_associado = ?, acesso_lancamentos = ?, ativo = ? $sql_senha_part WHERE id = ?";
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
            
            // Se o usuário for cliente, atualiza também a permissão de lançamentos na sessão
            if (isClient()) {
                $stmt_acesso = $pdo->prepare("SELECT acesso_lancamentos FROM usuarios WHERE id = ?");
                $stmt_acesso->execute([$user_id]);
                $_SESSION['user_acesso_lancamentos'] = $stmt_acesso->fetchColumn();
            }

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
            $id_tipo_cobranca = $_POST['id_tipo_cobranca'] ?? null;
            $descricao = $_POST['descricao'];
            $contexto_pagamento = $_POST['contexto_pagamento'] ?? null;

            try {
                $sql = "INSERT INTO cobrancas (id_empresa, data_competencia, data_vencimento, valor, id_forma_pagamento, id_tipo_cobranca, descricao, contexto_pagamento, status_pagamento) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendente')";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id_empresa, $data_competencia, $data_vencimento, $valor, $id_forma_pagamento, $id_tipo_cobranca, $descricao, $contexto_pagamento])) {
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
            $id_tipo_cobranca = $_POST['id_tipo_cobranca'] ?? null;
            $descricao = $_POST['descricao'];
            $contexto_pagamento = $_POST['contexto_pagamento'] ?? null;

            try {
                $sql = "UPDATE cobrancas SET 
                            id_empresa = ?, 
                            data_competencia = ?, 
                            data_vencimento = ?, 
                            valor = ?, 
                            id_forma_pagamento = ?, 
                            id_tipo_cobranca = ?, 
                            descricao = ?, 
                            contexto_pagamento = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id_empresa, $data_competencia, $data_vencimento, $valor, $id_forma_pagamento, $id_tipo_cobranca, $descricao, $contexto_pagamento, $id])) {
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

    // --- AÇÕES DE TIPOS DE COBRANÇA (Admin) ---

    case 'criar_tipo_cobranca':
        if (isAdmin()) {
            $nome = $_POST['nome'];
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            try {
                $sql = "INSERT INTO tipos_cobranca (nome, ativo) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$nome, $ativo])) {
                    $_SESSION['success_message'] = "Tipo de cobrança criado com sucesso!";
                    logAction("Criou Tipo de Cobrança", "tipos_cobranca", $pdo->lastInsertId(), "Nome: $nome");
                } else {
                    $_SESSION['error_message'] = "Erro ao criar tipo de cobrança.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error_message'] = "Erro: Já existe um tipo de cobrança com este nome.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    case 'editar_tipo_cobranca':
        if (isAdmin()) {
            $id = $_POST['id'];
            $nome = $_POST['nome'];
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            try {
                $sql = "UPDATE tipos_cobranca SET nome = ?, ativo = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$nome, $ativo, $id])) {
                    $_SESSION['success_message'] = "Tipo de cobrança atualizado com sucesso!";
                    logAction("Editou Tipo de Cobrança", "tipos_cobranca", $id, "Nome: $nome");
                } else {
                    $_SESSION['error_message'] = "Erro ao atualizar tipo de cobrança.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error_message'] = "Erro: Já existe um tipo de cobrança com este nome.";
                } else {
                    $_SESSION['error_message'] = "Erro no banco de dados: " . $e->getMessage();
                }
            }
        }
        break;

    case 'excluir_tipo_cobranca':
        if (isAdmin()) {
            $id = $_GET['id'];
            try {
                $sql = "DELETE FROM tipos_cobranca WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id])) {
                    $_SESSION['success_message'] = "Tipo de cobrança excluído com sucesso!";
                    logAction("Excluiu Tipo de Cobrança", "tipos_cobranca", $id);
                } else {
                    $_SESSION['error_message'] = "Erro ao excluir tipo de cobrança.";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1451) { // Foreign key constraint
                    $_SESSION['error_message'] = "Erro: Este tipo de cobrança não pode ser excluído pois está sendo utilizado em uma ou mais cobranças.";
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