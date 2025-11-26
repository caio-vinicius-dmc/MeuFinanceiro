<?php
// process/crud_handler.php
require_once '../config/functions.php';
requireLogin(); 

global $pdo;
// detect company column name at runtime (empresa_id or id_empresa)
$company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
// Permite receber 'action' via POST (formulários) ou GET (botão de email)
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$user_id = $_SESSION['user_id'];

// Limpa mensagens antigas
unset($_SESSION['success_message'], $_SESSION['error_message']);

switch ($action) {

        case 'enviar_cobranca_email':
            // Envia por email uma cobrança específica para o contato do cliente/empresa
            $id_cobranca = $_GET['id'] ?? $_POST['id'] ?? null;
            if (!$id_cobranca) {
                $_SESSION['error_message'] = 'ID da cobrança ausente.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            try {
        // Busca dados da cobrança com empresa e cliente (usa coluna dinâmica de empresa)
        $sql = "SELECT cob.*, emp.razao_social, emp.id_cliente, cli.nome_responsavel, cli.email_contato
            FROM cobrancas cob
            JOIN empresas emp ON cob.`" . $company_col . "` = emp.id
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
                // Resolve nome da forma de pagamento (id_forma_pagamento) e o tipo da cobrança (id_tipo_cobranca).
                $forma_nome = '';
                try {
                    if (!empty($cob['id_forma_pagamento'])) {
                        $stmtFp = $pdo->prepare('SELECT nome FROM formas_pagamento WHERE id = ? LIMIT 1');
                        $stmtFp->execute([$cob['id_forma_pagamento']]);
                        $forma_nome = $stmtFp->fetchColumn() ?: '';
                    }
                } catch (Exception $e) {
                    $forma_nome = '';
                }

                $tipo_nome = 'receita'; // fallback
                try {
                    if (!empty($cob['id_tipo_cobranca'])) {
                        $stmtTipo = $pdo->prepare('SELECT nome FROM tipos_cobranca WHERE id = ? LIMIT 1');
                        $stmtTipo->execute([$cob['id_tipo_cobranca']]);
                        $tipo_nome = $stmtTipo->fetchColumn() ?: $tipo_nome;
                    }
                } catch (Exception $e) {
                    // mantém fallback
                }

                $lancamento_like = [
                    'id' => $cob['id'],
                    'descricao' => $cob['descricao'] ?? 'Cobrança',
                    'valor' => $cob['valor'],
                    'data_vencimento' => $cob['data_vencimento'],
                    'tipo' => $tipo_nome,
                    'forma_pagamento' => $forma_nome,
                    'contexto_pagamento' => $cob['contexto_pagamento'] ?? ''
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

    // 'recibo_pagamento' implementation moved later in the file (single implementation using templates)

    // 'termo_quitacao' implementation moved later in the file (single implementation)

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

    case 'salvar_preferencias_cliente':
        // Permite que o cliente ajuste suas preferências de envio automático de emails
        if (!isClient()) {
            $_SESSION['error_message'] = 'Ação não permitida.';
            header('Location: ' . base_url('index.php?page=meu_perfil'));
            exit;
        }
        $id_cliente = $_SESSION['id_cliente_associado'] ?? null;
        if (empty($id_cliente)) {
            $_SESSION['error_message'] = 'Cliente associado não encontrado.';
            header('Location: ' . base_url('index.php?page=meu_perfil'));
            exit;
        }

        $receber_cobrancas = isset($_POST['receber_novas_cobrancas']) ? 1 : 0;
        $receber_recibos = isset($_POST['receber_recibos']) ? 1 : 0;

        // Validação adicional: garantir que o cliente tenha email de contato antes de permitir ativar preferências
        try {
            $stmtCliEmail = $pdo->prepare('SELECT email_contato FROM clientes WHERE id = ? LIMIT 1');
            $stmtCliEmail->execute([$id_cliente]);
            $cliRow = $stmtCliEmail->fetch(PDO::FETCH_ASSOC);
            $cliente_email = trim($cliRow['email_contato'] ?? '');
            if (empty($cliente_email) && ($receber_cobrancas || $receber_recibos)) {
                $_SESSION['error_message'] = 'Não é possível ativar notificações: o cliente não possui email de contato cadastrado.';
                header('Location: ' . base_url('index.php?page=meu_perfil'));
                exit;
            }
        } catch (Exception $e) {
            // se falhar a leitura do email, bloqueia alterações por segurança
            if ($receber_cobrancas || $receber_recibos) {
                $_SESSION['error_message'] = 'Erro ao verificar email do cliente. Tente novamente mais tarde.';
                header('Location: ' . base_url('index.php?page=meu_perfil'));
                exit;
            }
        }

        try {
            $pdo->beginTransaction();

            // cobranças
            if ($receber_cobrancas) {
                $chk = $pdo->prepare("SELECT 1 FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ? LIMIT 1");
                $chk->execute([$id_cliente, 'receber_novas_cobrancas']);
                if (!$chk->fetchColumn()) {
                    $ins = $pdo->prepare("INSERT INTO tb_confg_emailCliente (id_client, permissao, descricao) VALUES (?, ?, ?)");
                    $ins->execute([$id_cliente, 'receber_novas_cobrancas', 'Envia cobrança via email do cliente de forma automática']);
                }
            } else {
                $del = $pdo->prepare("DELETE FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ?");
                $del->execute([$id_cliente, 'receber_novas_cobrancas']);
            }

            // recibos
            if ($receber_recibos) {
                $chk2 = $pdo->prepare("SELECT 1 FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ? LIMIT 1");
                $chk2->execute([$id_cliente, 'receber_recibos']);
                if (!$chk2->fetchColumn()) {
                    $ins2 = $pdo->prepare("INSERT INTO tb_confg_emailCliente (id_client, permissao, descricao) VALUES (?, ?, ?)");
                    $ins2->execute([$id_cliente, 'receber_recibos', 'Envia recibo de pagamento via email do cliente de forma automática']);
                }
            } else {
                $del2 = $pdo->prepare("DELETE FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ?");
                $del2->execute([$id_cliente, 'receber_recibos']);
            }

            $pdo->commit();
            $_SESSION['success_message'] = 'Preferências salvas com sucesso.';
            logAction('Atualizou Preferências Email', 'clientes', $id_cliente, 'Preferências de envio de emails atualizadas pelo cliente.');
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Erro ao salvar preferências: ' . $e->getMessage();
            error_log('Erro ao salvar preferencias cliente: ' . $e->getMessage());
        }
        header('Location: ' . base_url('index.php?page=meu_perfil'));
        exit;
        break;

    case 'associar_me_cliente':
        // Permite que um contador se associe imediatamente a um cliente (auto-associação)
        if (isContador()) {
            $id_cliente = intval($_POST['id_cliente'] ?? 0);
            if ($id_cliente <= 0) {
                $_SESSION['error_message'] = 'Cliente inválido.';
                break;
            }
            try {
                $chk = $pdo->prepare("SELECT 1 FROM contador_clientes_assoc WHERE id_usuario_contador = ? AND id_cliente = ? LIMIT 1");
                $chk->execute([$_SESSION['user_id'], $id_cliente]);
                if ($chk->fetchColumn()) {
                    $_SESSION['info_message'] = 'Você já está associado a este cliente.';
                } else {
                    $ins = $pdo->prepare("INSERT INTO contador_clientes_assoc (id_usuario_contador, id_cliente) VALUES (?, ?)");
                    if ($ins->execute([$_SESSION['user_id'], $id_cliente])) {
                        $_SESSION['success_message'] = 'Associação realizada com sucesso.';
                        logAction('Associação Contador-Cliente', 'contador_clientes_assoc', $id_cliente, 'Contador: ' . $_SESSION['user_id']);
                    } else {
                        $_SESSION['error_message'] = 'Erro ao associar.';
                    }
                }
            } catch (Exception $e) {
                error_log('Erro ao associar contador: ' . $e->getMessage());
                $_SESSION['error_message'] = 'Erro ao processar associação.';
            }
        }
        header('Location: ' . base_url('index.php?page=cadastro_clientes'));
        exit;

    case 'solicitar_assoc_cliente':
        // Fluxo de solicitação de associação (cria um pedido para revisão)
        if (isContador()) {
            try {
                // Buscar cliente por id (se fornecido) ou por email
                if (!empty($_POST['id_cliente'])) {
                    $cliente_id = intval($_POST['id_cliente']);
                    $stmtC = $pdo->prepare('SELECT id FROM clientes WHERE id = ? LIMIT 1');
                    $stmtC->execute([$cliente_id]);
                    $cliente_id = $stmtC->fetchColumn();
                    if (!$cliente_id) {
                        $_SESSION['error_message'] = 'Cliente não encontrado.';
                        break;
                    }
                } else {
                    $email = trim($_POST['request_email_cliente'] ?? '');
                    if (empty($email)) {
                        $_SESSION['error_message'] = 'Informe o email do cliente.';
                        break;
                    }
                    // Buscar pelo email
                    $stmtC = $pdo->prepare('SELECT id FROM clientes WHERE email_contato = ? LIMIT 1');
                    $stmtC->execute([$email]);
                    $cliente_id = $stmtC->fetchColumn();
                    if (!$cliente_id) {
                        $_SESSION['error_message'] = 'Cliente não encontrado com esse email.';
                        break;
                    }
                }

                // Criar tabela de requests simples
                $pdo->exec("CREATE TABLE IF NOT EXISTS contador_assoc_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_usuario_contador INT NOT NULL,
                    id_cliente INT NOT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Inserir pedido
                $ins = $pdo->prepare('INSERT INTO contador_assoc_requests (id_usuario_contador, id_cliente, status) VALUES (?, ?, ?)');
                if ($ins->execute([$_SESSION['user_id'], $cliente_id, 'pending'])) {
                    $_SESSION['success_message'] = 'Solicitação enviada com sucesso. Aguarde aprovação do administrador.';
                    logAction('Solicitação Associação', 'contador_assoc_requests', $cliente_id, 'Contador: ' . $_SESSION['user_id']);
                } else {
                    $_SESSION['error_message'] = 'Erro ao enviar solicitação.';
                }
            } catch (Exception $e) {
                error_log('Erro ao solicitar associação: ' . $e->getMessage());
                $_SESSION['error_message'] = 'Falha ao processar solicitação.';
            }
        }
        header('Location: ' . base_url('index.php?page=cadastro_clientes'));
        exit;

    case 'aprovar_assoc_request':
        // Aprovar pedido de associação (apenas Admin/SuperAdmin)
        if (isAdmin() || isSuperAdmin()) {
            $request_id = intval($_POST['request_id'] ?? 0);
            if ($request_id <= 0) {
                $_SESSION['error_message'] = 'Solicitação inválida.';
                break;
            }
            try {
                $stmt = $pdo->prepare('SELECT * FROM contador_assoc_requests WHERE id = ? LIMIT 1');
                $stmt->execute([$request_id]);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$r) {
                    $_SESSION['error_message'] = 'Solicitação não encontrada.';
                    break;
                }
                if ($r['status'] !== 'pending') {
                    $_SESSION['info_message'] = 'Solicitação já processada.';
                    break;
                }

                // Verifica se já existe associação (evita duplicatas)
                $chkAssoc = $pdo->prepare('SELECT 1 FROM contador_clientes_assoc WHERE id_usuario_contador = ? AND id_cliente = ? LIMIT 1');
                $chkAssoc->execute([$r['id_usuario_contador'], $r['id_cliente']]);
                $assoc_exists = (bool) $chkAssoc->fetchColumn();

                if ($assoc_exists) {
                    // Marca como aprovado mesmo que já existisse, registra processed_* e notifica
                    try {
                        $updStmt = $pdo->prepare('UPDATE contador_assoc_requests SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ?');
                        $updStmt->execute(['approved', $_SESSION['user_id'], $request_id]);
                    } catch (Exception $e) {
                        // fallback se as colunas processed_* não existirem
                        $updStmt = $pdo->prepare('UPDATE contador_assoc_requests SET status = ? WHERE id = ?');
                        $updStmt->execute(['approved', $request_id]);
                    }

                    $_SESSION['info_message'] = 'Associação já existe. Solicitação marcada como aprovada.';
                    logAction('Aprovação Associação (duplicada)', 'contador_assoc_requests', $request_id, 'Aprovado por: ' . $_SESSION['user_id'] . ' (associação já existente)');
                } else {
                    // Inserir associação
                    $ins = $pdo->prepare('INSERT INTO contador_clientes_assoc (id_usuario_contador, id_cliente) VALUES (?, ?)');
                    $ins_ok = $ins->execute([$r['id_usuario_contador'], $r['id_cliente']]);

                    // Atualizar status da solicitação com processed_by/at quando possível
                    try {
                        $upd = $pdo->prepare('UPDATE contador_assoc_requests SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ?');
                        $upd->execute(['approved', $_SESSION['user_id'], $request_id]);
                    } catch (Exception $e) {
                        $upd = $pdo->prepare('UPDATE contador_assoc_requests SET status = ? WHERE id = ?');
                        $upd->execute(['approved', $request_id]);
                    }

                    if ($ins_ok) {
                        $_SESSION['success_message'] = 'Solicitação aprovada e associação realizada.';
                        logAction('Aprovação Associação', 'contador_clientes_assoc', $r['id_cliente'], 'Aprovado por: ' . $_SESSION['user_id'] . ' - Contador: ' . $r['id_usuario_contador']);
                    } else {
                        $_SESSION['error_message'] = 'Erro ao criar associação.';
                        logAction('Erro ao inserir associação', 'contador_clientes_assoc', $r['id_cliente'], 'Tentativa por: ' . $_SESSION['user_id']);
                    }
                }

                // Notificar contador por email sobre o resultado (tentativa silenciosa se falhar)
                try {
                    $stmtU = $pdo->prepare('SELECT nome, email FROM usuarios WHERE id = ? LIMIT 1');
                    $stmtU->execute([$r['id_usuario_contador']]);
                    $urow = $stmtU->fetch(PDO::FETCH_ASSOC);
                    if ($urow && !empty($urow['email'])) {
                        $toEmail = $urow['email'];
                        $toName = $urow['nome'] ?: 'Contador';

                        $settings_email = getSmtpSettings();
                        $subject_tpl = $settings_email['assoc_approved_subject'] ?? 'Solicitação de Associação Aprovada';
                        $body_tpl = $settings_email['assoc_approved_body'] ?? '<p>Olá {toName},</p><p>Sua solicitação de associação ao cliente (ID: {id_cliente}) foi aprovada pelo administrador.</p><p>Atenciosamente,</p>';

                        // Enriquecer placeholders com informações do cliente/empresa quando possível
                        $cliente_nome = '';
                        $cliente_email = '';
                        $empresa_nome = '';
                        try {
                            $stmtCli = $pdo->prepare('SELECT nome_responsavel, email_contato FROM clientes WHERE id = ? LIMIT 1');
                            $stmtCli->execute([$r['id_cliente']]);
                            $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);
                            if ($cli) {
                                $cliente_nome = $cli['nome_responsavel'] ?? '';
                                $cliente_email = $cli['email_contato'] ?? '';
                            }
                        } catch (Exception $e) {
                            // ignore
                        }
                        try {
                            $stmtEmp = $pdo->prepare('SELECT razao_social FROM empresas WHERE id_cliente = ? LIMIT 1');
                            $stmtEmp->execute([$r['id_cliente']]);
                            $empresa_nome = $stmtEmp->fetchColumn() ?: '';
                        } catch (Exception $e) {
                            // ignore
                        }

                        $placeholders = [
                            '{toName}' => htmlspecialchars($toName),
                            '{id_cliente}' => intval($r['id_cliente']),
                            '{cliente_nome}' => htmlspecialchars($cliente_nome),
                            '{cliente_email}' => htmlspecialchars($cliente_email),
                            '{empresa}' => htmlspecialchars($empresa_nome),
                            '{date}' => date('d/m/Y H:i')
                        ];

                        $subject_filled = str_replace(array_keys($placeholders), array_values($placeholders), $subject_tpl);
                        $body_filled = str_replace(array_keys($placeholders), array_values($placeholders), $body_tpl);

                        $sentNotify = sendDocumentNotification($toEmail, $toName, $subject_filled, $body_filled);
                        logAction('Notificação Associação Enviada', 'usuarios', $r['id_usuario_contador'], 'Enviado para: ' . $toEmail . ' - Sucesso: ' . ($sentNotify ? '1' : '0'));
                    }
                } catch (Exception $e) {
                    error_log('Falha ao enviar notificação de aprovação: ' . $e->getMessage());
                }
            } catch (Exception $e) {
                error_log('Erro ao aprovar solicitação: ' . $e->getMessage());
                $_SESSION['error_message'] = 'Erro ao aprovar solicitação.';
            }
        }
        header('Location: ' . base_url('index.php?page=associacoes_contador'));
        exit;

    case 'recusar_assoc_request':
        // Recusar pedido de associação (apenas Admin/SuperAdmin)
        if (isAdmin() || isSuperAdmin()) {
            $request_id = intval($_POST['request_id'] ?? 0);
            if ($request_id <= 0) {
                $_SESSION['error_message'] = 'Solicitação inválida.';
                break;
            }
            try {
                $stmt = $pdo->prepare('SELECT * FROM contador_assoc_requests WHERE id = ? LIMIT 1');
                $stmt->execute([$request_id]);
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$r) {
                    $_SESSION['error_message'] = 'Solicitação não encontrada.';
                    break;
                }
                if ($r['status'] !== 'pending') {
                    $_SESSION['info_message'] = 'Solicitação já processada.';
                    break;
                }

                // Atualizar status e registrar processed_by/processed_at quando possível
                try {
                    $upd = $pdo->prepare('UPDATE contador_assoc_requests SET status = ?, processed_by = ?, processed_at = NOW() WHERE id = ?');
                    $upd->execute(['rejected', $_SESSION['user_id'], $request_id]);
                } catch (Exception $e) {
                    $upd = $pdo->prepare('UPDATE contador_assoc_requests SET status = ? WHERE id = ?');
                    $upd->execute(['rejected', $request_id]);
                }

                $_SESSION['success_message'] = 'Solicitação recusada.';
                logAction('Recusa Associação', 'contador_assoc_requests', $request_id, 'Recusado por: ' . $_SESSION['user_id']);

                // Notificar contador por email sobre a recusa
                try {
                    $stmtU = $pdo->prepare('SELECT nome, email FROM usuarios WHERE id = ? LIMIT 1');
                    $stmtU->execute([$r['id_usuario_contador']]);
                    $urow = $stmtU->fetch(PDO::FETCH_ASSOC);
                    if ($urow && !empty($urow['email'])) {
                        $toEmail = $urow['email'];
                        $toName = $urow['nome'] ?: 'Contador';

                        $settings_email = getSmtpSettings();
                        $subject_tpl = $settings_email['assoc_rejected_subject'] ?? 'Solicitação de Associação Recusada';
                        $body_tpl = $settings_email['assoc_rejected_body'] ?? '<p>Olá {toName},</p><p>Sua solicitação de associação ao cliente (ID: {id_cliente}) foi recusada pelo administrador.</p><p>Atenciosamente,</p>';

                        // Enriquecer placeholders com informações do cliente/empresa quando possível
                        $cliente_nome = '';
                        $cliente_email = '';
                        $empresa_nome = '';
                        try {
                            $stmtCli = $pdo->prepare('SELECT nome_responsavel, email_contato FROM clientes WHERE id = ? LIMIT 1');
                            $stmtCli->execute([$r['id_cliente']]);
                            $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);
                            if ($cli) {
                                $cliente_nome = $cli['nome_responsavel'] ?? '';
                                $cliente_email = $cli['email_contato'] ?? '';
                            }
                        } catch (Exception $e) {
                            // ignore
                        }
                        try {
                            $stmtEmp = $pdo->prepare('SELECT razao_social FROM empresas WHERE id_cliente = ? LIMIT 1');
                            $stmtEmp->execute([$r['id_cliente']]);
                            $empresa_nome = $stmtEmp->fetchColumn() ?: '';
                        } catch (Exception $e) {
                            // ignore
                        }

                        $placeholders = [
                            '{toName}' => htmlspecialchars($toName),
                            '{id_cliente}' => intval($r['id_cliente']),
                            '{cliente_nome}' => htmlspecialchars($cliente_nome),
                            '{cliente_email}' => htmlspecialchars($cliente_email),
                            '{empresa}' => htmlspecialchars($empresa_nome),
                            '{date}' => date('d/m/Y H:i')
                        ];

                        $subject_filled = str_replace(array_keys($placeholders), array_values($placeholders), $subject_tpl);
                        $body_filled = str_replace(array_keys($placeholders), array_values($placeholders), $body_tpl);

                        $sentNotify = sendDocumentNotification($toEmail, $toName, $subject_filled, $body_filled);
                        logAction('Notificação Recusa Associação Enviada', 'usuarios', $r['id_usuario_contador'], 'Enviado para: ' . $toEmail . ' - Sucesso: ' . ($sentNotify ? '1' : '0'));
                    }
                } catch (Exception $e) {
                    error_log('Falha ao enviar notificação de recusa: ' . $e->getMessage());
                }
            } catch (Exception $e) {
                error_log('Erro ao recusar solicitação: ' . $e->getMessage());
                $_SESSION['error_message'] = 'Erro ao recusar solicitação.';
            }
        }
        header('Location: ' . base_url('index.php?page=associacoes_contador'));
        exit;

    case 'salvar_config_smtp':
        // Salva as configurações SMTP no banco (tabela system_settings)
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem alterar as configurações de e-mail.';
            header('Location: ' . base_url('index.php?page=configuracoes_email'));
            exit;
        }

        // Campos esperados
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_port = trim($_POST['smtp_port'] ?? '');
        $smtp_secure = trim($_POST['smtp_secure'] ?? '');
        $email_from = trim($_POST['email_from'] ?? '');
        $email_from_name = trim($_POST['email_from_name'] ?? '');
        $smtp_username = trim($_POST['smtp_username'] ?? '');
        $smtp_password = $_POST['smtp_password'] ?? ''; // senha pode ser vazia para manter atual

        // Validações básicas
        if ($smtp_host === '' || $smtp_port === '' || $smtp_username === '' || $email_from === '' || $email_from_name === '') {
            $_SESSION['error_message'] = 'Host, porta, usuário e e-mail de remetente são obrigatórios.';
            header('Location: ' . base_url('index.php?page=configuracoes_email'));
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Helper para upsert seguro usando ON DUPLICATE KEY UPDATE
            $upsert = function($key, $value) use ($pdo) {
                $sql = 'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$key, $value]);
            };

            $upsert('smtp_host', $smtp_host);
            $upsert('smtp_port', $smtp_port);
            $upsert('smtp_secure', $smtp_secure);
            $upsert('email_from', $email_from);
            $upsert('smtp_username', $smtp_username);
            // Campos de template e remetente: só atualiza se o campo foi enviado no formulário
            if (array_key_exists('email_subject_template', $_POST)) $upsert('email_subject_template', trim($_POST['email_subject_template'] ?? ''));
            if (array_key_exists('email_from_name', $_POST)) $upsert('email_from_name', trim($_POST['email_from_name'] ?? ''));
            if (array_key_exists('email_salutation', $_POST)) $upsert('email_salutation', trim($_POST['email_salutation'] ?? ''));
            if (array_key_exists('email_intro', $_POST)) $upsert('email_intro', trim($_POST['email_intro'] ?? ''));
            if (array_key_exists('email_closing', $_POST)) $upsert('email_closing', trim($_POST['email_closing'] ?? ''));

            // Corpo customizado para emails de lançamento (sanitizar HTML)
            if (array_key_exists('lancamento_email_body', $_POST)) $upsert('lancamento_email_body', sanitize_html($_POST['lancamento_email_body'] ?? ''));
            // Recibo de Pagamento - personalização de email (opcional neste formulário)
            if (array_key_exists('recibo_email_subject', $_POST)) $upsert('recibo_email_subject', trim($_POST['recibo_email_subject'] ?? ''));
            if (array_key_exists('recibo_email_title', $_POST)) $upsert('recibo_email_title', trim($_POST['recibo_email_title'] ?? ''));
            if (array_key_exists('recibo_email_body', $_POST)) $upsert('recibo_email_body', sanitize_html($_POST['recibo_email_body'] ?? ''));

            // Senha: só atualiza se foi enviada
            if (trim($smtp_password) !== '') {
                $upsert('smtp_password', $smtp_password);
            }

            $pdo->commit();
            $_SESSION['success_message'] = 'Configurações SMTP salvas com sucesso.';
            logAction('Atualizou Configurações SMTP', 'system_settings', null, 'Atualizou parâmetros SMTP');
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Erro ao salvar configurações: ' . $e->getMessage();
        }

        header('Location: ' . base_url('index.php?page=configuracoes_email'));
        exit;
        break;

    case 'salvar_templates_email':
        // Salva apenas os modelos/templates de e-mail (sem exigir campos SMTP)
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem alterar os modelos de e-mail.';
            header('Location: ' . base_url('index.php?page=configuracoes_email'));
            exit;
        }

        try {
            $pdo->beginTransaction();

            $upsert = function($key, $value) use ($pdo) {
                $sql = 'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$key, $value]);
            };

            // Lista de keys de templates a serem sempre upsertadas quando o formulário de modelos for enviado.
            $template_keys = [
                'email_subject_template', 'email_salutation', 'email_intro', 'email_closing',
                // Recibo
                'recibo_email_subject', 'recibo_email_title', 'recibo_email_body',
                // Corpo customizável do lançamento
                'lancamento_email_body', 'lancamento_email_title',
                // Associações: templates de notificação para aprovacao/recusa
                'assoc_approved_subject', 'assoc_approved_body', 'assoc_rejected_subject', 'assoc_rejected_body'
            ];

            foreach ($template_keys as $k) {
                // Somente atualiza as chaves que foram realmente enviadas pelo formulário
                if (array_key_exists($k, $_POST)) {
                    $val = $_POST[$k];
                    // Se for um campo HTML (body), sanitiza antes de salvar
                    if (is_string($val) && preg_match('/_body$/', $k)) {
                        $clean = sanitize_html($val);
                        $upsert($k, $clean);
                    } else {
                        $upsert($k, is_string($val) ? trim($val) : $val);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['success_message'] = 'Modelos de e-mail salvos com sucesso.';
            logAction('Atualizou Modelos de Email', 'system_settings', null, 'Atualizou templates de email');
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Erro ao salvar modelos: ' . $e->getMessage();
        }

        header('Location: ' . base_url('index.php?page=configuracoes_email'));
        exit;
        break;

    case 'salvar_config_documentos':
        // Salva templates para Termo e Recibo
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem alterar templates de documentos.';
            header('Location: ' . base_url('index.php?page=configuracoes_documentos'));
            exit;
        }

        try {
            $pdo->beginTransaction();
            $upsert = function($key, $value) use ($pdo) {
                $sql = 'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$key, $value]);
            };

            // Only update keys that were present in the POST (so forms can update subsets safely)
            $allowed_keys = [
                'termo_header','termo_body','termo_footer',
                'recibo_header','recibo_body','recibo_footer',
                'recibo_email_subject','recibo_email_title','recibo_email_body'
            ];
            foreach ($allowed_keys as $k) {
                if (array_key_exists($k, $_POST)) {
                    $val = $_POST[$k] ?? '';
                    if (is_string($val) && preg_match('/_body$/', $k)) {
                        $upsert($k, sanitize_html($val));
                    } else {
                        $upsert($k, trim($val));
                    }
                }
            }

            $pdo->commit();
            $_SESSION['success_message'] = 'Templates de documentos salvos com sucesso.';
            logAction('Atualizou Templates de Documentos', 'system_settings', null, 'Templates termo/recibo atualizados');
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Erro ao salvar templates: ' . $e->getMessage();
        }

        header('Location: ' . base_url('index.php?page=configuracoes_documentos'));
        exit;
        break;

    case 'criar_categoria_lancamento':
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem criar categorias.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if ($nome === '') {
            $_SESSION['error_message'] = 'Nome da categoria obrigatório.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO categorias_lancamento (nome, descricao, ativo) VALUES (?, ?, ?)');
            $stmt->execute([$nome, $descricao, $ativo]);
            $_SESSION['success_message'] = 'Categoria criada com sucesso.';
            logAction('Criou Categoria Lancamento', 'categorias_lancamento', $pdo->lastInsertId(), $nome);
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao criar categoria: ' . $e->getMessage();
        }
        header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
        exit;
        break;

    case 'editar_categoria_lancamento':
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem editar categorias.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        $id = intval($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        if ($id <= 0 || $nome === '') {
            $_SESSION['error_message'] = 'Dados inválidos para atualização.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        try {
            $stmt = $pdo->prepare('UPDATE categorias_lancamento SET nome = ?, descricao = ?, ativo = ? WHERE id = ?');
            $stmt->execute([$nome, $descricao, $ativo, $id]);
            $_SESSION['success_message'] = 'Categoria atualizada com sucesso.';
            logAction('Editou Categoria Lancamento', 'categorias_lancamento', $id, $nome);
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao atualizar categoria: ' . $e->getMessage();
        }
        header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
        exit;
        break;

    case 'excluir_categoria_lancamento':
        if (!isAdmin()) {
            $_SESSION['error_message'] = 'Apenas administradores podem excluir categorias.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['error_message'] = 'ID inválido.';
            header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
            exit;
        }
        try {
            // Antes de excluir, removemos referência em lançamentos (set null)
            $pdo->beginTransaction();
            $stmtNull = $pdo->prepare('UPDATE lancamentos SET id_categoria = NULL WHERE id_categoria = ?');
            $stmtNull->execute([$id]);
            $stmt = $pdo->prepare('DELETE FROM categorias_lancamento WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();
            $_SESSION['success_message'] = 'Categoria excluída com sucesso.';
            logAction('Excluiu Categoria Lancamento', 'categorias_lancamento', $id);
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = 'Erro ao excluir categoria: ' . $e->getMessage();
        }
        header('Location: ' . base_url('index.php?page=gerenciar_categorias'));
        exit;
        break;

    case 'import_lancamentos':
        // Importa um CSV de lançamentos para a empresa selecionada.
        if (!(isAdmin() || isContador() || isClient())) {
            $_SESSION['error_message'] = 'Você não tem permissão para importar lançamentos.';
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }

        // Checa arquivo
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error_message'] = 'Arquivo CSV não enviado ou inválido.';
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }

        // Determine target company: prefer posted id_empresa, fallback to current selected company
        $id_empresa = null;
        if (!empty($_POST['id_empresa'])) $id_empresa = intval($_POST['id_empresa']);
        if (empty($id_empresa)) {
            $id_empresa = current_company_id();
        }
        // additional enforcement: ensure user may act on this company
        if (!empty($id_empresa)) ensure_user_can_access_company($id_empresa);
        if (empty($id_empresa)) {
            $_SESSION['error_message'] = 'Selecione a empresa destino para a importação.';
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }

        // Verifica permissão para a empresa selecionada: allow global admin or user associated with company
        try {
            $stmtCheck = $pdo->prepare('SELECT id_cliente FROM empresas WHERE id = ?');
            $stmtCheck->execute([$id_empresa]);
            $empresa_cliente = $stmtCheck->fetchColumn();
            if ($empresa_cliente === false) {
                $_SESSION['error_message'] = 'Empresa selecionada não encontrada.';
                header('Location: ' . base_url('index.php?page=lancamentos'));
                exit;
            }

            if (!isAdmin()) {
                // if client role exists in legacy app, keep client rules
                if (isClient()) {
                    $id_cliente_logado = $_SESSION['id_cliente_associado'] ?? null;
                    if ($id_cliente_logado != $empresa_cliente) {
                        $_SESSION['error_message'] = 'Você não tem permissão para importar para esta empresa.';
                        header('Location: ' . base_url('index.php?page=lancamentos'));
                        exit;
                    }
                }
                // For contador or other roles, require the user to be associated to the company
                if (!user_has_any_role($_SESSION['user_id'], $id_empresa)) {
                    // contador legacy rule: check contador_clientes_assoc if that table is used
                    if (isContador()) {
                        $stmtAssoc = $pdo->prepare('SELECT id_cliente FROM contador_clientes_assoc WHERE id_usuario_contador = ?');
                        $stmtAssoc->execute([$_SESSION['user_id']]);
                        $assoc = $stmtAssoc->fetchAll(PDO::FETCH_COLUMN);
                        if (!in_array($empresa_cliente, $assoc)) {
                            $_SESSION['error_message'] = 'Você não está associado ao cliente desta empresa.';
                            header('Location: ' . base_url('index.php?page=lancamentos'));
                            exit;
                        }
                    } else {
                        $_SESSION['error_message'] = 'Você não tem permissão para importar para esta empresa.';
                        header('Location: ' . base_url('index.php?page=lancamentos'));
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao validar permissão: ' . $e->getMessage();
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }

        // Helpers locais
        function normalize_header($s) {
            $s = mb_strtolower(trim($s));
            $trans = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ç'=>'c'];
            $s = strtr($s, $trans);
            $s = preg_replace('/[^a-z0-9]/', '', $s);
            return $s;
        }

        function parse_decimal($raw) {
            $r = trim((string)$raw);
            if ($r === '') return null;
            // Remove currency symbols and spaces
            $r = preg_replace('/[R$\s]/u','',$r);
            // If contains comma and dot, assume dot thousand and comma decimal -> remove dots, replace comma
            if (strpos($r, ',') !== false && strpos($r, '.') !== false) {
                $r = str_replace('.', '', $r);
                $r = str_replace(',', '.', $r);
            } else {
                // replace comma with dot
                $r = str_replace(',', '.', $r);
            }
            // remove any non-numeric except dot and minus
            $r = preg_replace('/[^0-9.\-]/', '', $r);
            return is_numeric($r) ? (float)$r : null;
        }

        function parse_date_flex($s) {
            $s = trim((string)$s);
            if ($s === '') return null;
            // YYYY-MM-DD
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;
            // DD/MM/YYYY
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $s)) {
                $parts = explode('/',$s);
                return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
            // Try strtotime
            $t = strtotime($s);
            if ($t !== false) return date('Y-m-d', $t);
            return null;
        }

        // Mapeia formas de pagamento e categorias do sistema (nome => id) para validação rápida
        $formas_map = [];
        $stmt_fp = $pdo->prepare('SELECT id, nome FROM formas_pagamento');
        $stmt_fp->execute();
        $all_formas = $stmt_fp->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_formas as $fp) {
            $formas_map[mb_strtolower(trim($fp['nome']))] = $fp['id'];
        }

        // Categorias (pode não existir ainda se migration não aplicada)
        $categorias_map = [];
        try {
            $stmt_cat = $pdo->prepare('SELECT id, nome FROM categorias_lancamento WHERE ativo = 1');
            $stmt_cat->execute();
            $all_cats = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
            foreach ($all_cats as $c) {
                $categorias_map[mb_strtolower(trim($c['nome']))] = $c['id'];
            }
        } catch (Exception $e) {
            // Se a tabela categorias_lancamento não existir ainda, apenas ignoramos (import/cadastro seguirá sem validação por nome)
        }

        $tmp = $_FILES['csv_file']['tmp_name'];
        $inserted = 0;
        $errors = [];

        try {
            if (!is_uploaded_file($tmp)) {
                throw new Exception('Arquivo inválido ou não enviado corretamente.');
            }
            // Verifica extensão do arquivo (ajuda a pegar uploads incorretos)
            $originalName = $_FILES['csv_file']['name'] ?? '';
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'])) {
                throw new Exception('Tipo de arquivo inválido. Envie um arquivo com extensão .csv');
            }
            $filesize = @filesize($tmp);
            if ($filesize === 0 || $filesize === false) {
                throw new Exception('Arquivo vazio. Verifique o conteúdo do CSV.');
            }

            if (($handle = fopen($tmp, 'r')) === false) {
                throw new Exception('Não foi possível abrir o arquivo CSV para leitura.');
            }

            // Read header (CSV com separador ';')
            $header = fgetcsv($handle, 0, ';', '"');
            if ($header === false) {
                throw new Exception('Arquivo CSV vazio ou inválido.');
            }
            // Detect common mistake: file uses comma as delimiter
            if (count($header) === 1 && strpos($header[0], ',') !== false) {
                throw new Exception('Parece que seu arquivo usa vírgula (,) como separador. O importador aceita apenas ponto-e-vírgula (;). Por favor, converta o arquivo para usar ponto-e-vírgula.');
            }
            $map = [];
            foreach ($header as $i => $h) {
                $norm = normalize_header($h);
                // Map expected names
                if (in_array($norm, ['descricao','desc','descr'])) $map[$i] = 'descricao';
                else if (in_array($norm, ['valor','val','vlor'])) $map[$i] = 'valor';
                else if (in_array($norm, ['tipo','tip'])) $map[$i] = 'tipo';
                else if (in_array($norm, ['datavencimento','datavenc','vencimento','datavto','datavenc'])) $map[$i] = 'data_vencimento';
                else if (in_array($norm, ['datacompetencia','datacompet','competencia','compet'])) $map[$i] = 'data_competencia';
                else if (in_array($norm, ['datapagamento','datapag','data_pagamento','pagamento'])) $map[$i] = 'data_pagamento';
                else if (in_array($norm, ['formadepagamento','formapagamento','formapagto','forma_pagamento','metodopagamento','metodo_pagamento'])) $map[$i] = 'metodo_pagamento';
                else if (in_array($norm, ['status','situacao','situacao_pagamento','estado'])) $map[$i] = 'status';
                else if (in_array($norm, ['categoria','categoria_lancamento','categoria_lanc','categoria_lancamentos'])) $map[$i] = 'categoria';
                else $map[$i] = null; // ignora colunas não reconhecidas
            }

            // Verifica se o cabeçalho possui as colunas obrigatórias
            $mappedFields = array_values(array_filter($map));
            $hasDescricao = in_array('descricao', $mappedFields);
            $hasValor = in_array('valor', $mappedFields);
            $hasTipo = in_array('tipo', $mappedFields);
            $hasVenc = in_array('data_vencimento', $mappedFields);
            if (!($hasDescricao && $hasValor && $hasTipo && $hasVenc)) {
                throw new Exception('Cabeçalho inválido. Colunas obrigatórias: Descrição, Valor, Tipo e Data vencimento. Verifique o arquivo e tente novamente.');
            }

            // Prepara statements de insert (detecta coluna id_forma_pagamento e id_categoria)
            $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_forma_pagamento'");
            $colStmt->execute();
            $has_forma_col = $colStmt->fetchColumn() > 0;

            $colStmt2 = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_categoria'");
            $colStmt2->execute();
            $has_categoria_col = $colStmt2->fetchColumn() > 0;

            // Monta SQL dinâmico conforme colunas disponíveis
        // Use the configured company column name (empresa_id or id_empresa)
        $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
        $insertCols = array_merge([$company_col], ['descricao','valor','tipo','data_vencimento','data_competencia','data_pagamento','metodo_pagamento']);
            if ($has_forma_col) $insertCols[] = 'id_forma_pagamento';
            if ($has_categoria_col) $insertCols[] = 'id_categoria';
            $insertCols[] = 'status';
            $placeholders = implode(',', array_fill(0, count($insertCols), '?'));
            $insertSql = "INSERT INTO lancamentos (" . implode(',', $insertCols) . ") VALUES ($placeholders)";
            $stmtInsert = $pdo->prepare($insertSql);

            // Leitura e validação de todas as linhas antes de inserir (tudo ou nada)
            $allRows = [];
            $line = 1;
            while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
                $line++;
                $allRows[] = ['line' => $line, 'row' => $row];
            }

            $prepared = [];
            foreach ($allRows as $rinfo) {
                $line = $rinfo['line'];
                $row = $rinfo['row'];
                $data = ['descricao'=>null,'valor'=>null,'tipo'=>null,'data_vencimento'=>null,'data_competencia'=>null,'data_pagamento'=>null,'metodo_pagamento'=>null,'status'=>null];
                foreach ($row as $i => $cell) {
                    if (!isset($map[$i]) || $map[$i] === null) continue;
                    $data[$map[$i]] = $cell;
                }

                $rowErrors = [];
                if (empty(trim((string)$data['descricao']))) $rowErrors[] = 'Descrição ausente';
                $valor = parse_decimal($data['valor']);
                if ($valor === null) $rowErrors[] = 'Valor inválido';
                $tipo = mb_strtolower(trim((string)$data['tipo']));
                if (!in_array($tipo, ['receita','despesa'])) $rowErrors[] = 'Tipo inválido (use receita/despesa)';
                $dv = parse_date_flex($data['data_vencimento']);
                if ($dv === null) $rowErrors[] = 'Data vencimento inválida ou ausente';
                $dc = parse_date_flex($data['data_competencia']);
                $dp = parse_date_flex($data['data_pagamento']);

                $metodo = trim((string)$data['metodo_pagamento']);
                $id_forma = null;
                if ($metodo !== '') {
                    $key = mb_strtolower($metodo);
                    if (isset($formas_map[$key])) {
                        $id_forma = $formas_map[$key];
                    } else {
                        $rowErrors[] = 'Forma de pagamento não encontrada: ' . $metodo;
                    }
                }

                // Categoria (opcional) - mapear nome -> id se a coluna existir
                $id_categoria = null;
                if (isset($data['categoria']) && trim((string)$data['categoria']) !== '') {
                    $cat_key = mb_strtolower(trim((string)$data['categoria']));
                    if (isset($categorias_map[$cat_key])) {
                        $id_categoria = $categorias_map[$cat_key];
                    } else {
                        $rowErrors[] = 'Categoria não encontrada: ' . $data['categoria'];
                    }
                }

                $status_raw = trim((string)$data['status']);
                $status_norm = null;
                if ($status_raw !== '') {
                    $skey = mb_strtolower($status_raw);
                    if (strpos($skey, 'pago') !== false) $status_norm = 'pago';
                    elseif (strpos($skey, 'aberto') !== false || strpos($skey, 'pendente') !== false) $status_norm = 'pendente';
                    else $rowErrors[] = 'Status inválido (use Em aberto ou Pago)';
                } else {
                    $status_norm = 'pendente';
                }

                if (!empty($rowErrors)) {
                    $errors[] = "Linha $line: " . implode('; ', $rowErrors);
                    continue;
                }

                $valor_grav = $valor;
                $metodo_txt = $metodo !== '' ? $metodo : null;

                // Monta params seguindo a ordem de $insertCols
                $params = [];
                $params[] = $id_empresa;
                $params[] = $data['descricao'];
                $params[] = $valor_grav;
                $params[] = $tipo;
                $params[] = $dv;
                $params[] = $dc ?: null;
                $params[] = $dp ?: null;
                $params[] = $metodo_txt;
                if ($has_forma_col) $params[] = $id_forma;
                if ($has_categoria_col) $params[] = $id_categoria;
                $params[] = $status_norm;
                $prepared[] = $params;
            }

            if (!empty($errors)) {
                fclose($handle);
                $_SESSION['error_message'] = 'Arquivo possui linhas inválidas. Nenhum lançamento foi importado.';
                $_SESSION['import_errors'] = $errors;
                header('Location: ' . base_url('index.php?page=lancamentos'));
                exit;
            }

            // Insere todos os registros validados dentro de uma transação
            $pdo->beginTransaction();
            try {
                foreach ($prepared as $params) {
                    $stmtInsert->execute($params);
                    $inserted++;
                }
                $pdo->commit();
                fclose($handle);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if (isset($handle) && is_resource($handle)) fclose($handle);
                $_SESSION['error_message'] = 'Erro ao inserir registros: ' . $e->getMessage();
                header('Location: ' . base_url('index.php?page=lancamentos'));
                exit;
            }
        } catch (Exception $e) {
            // Em caso de erro, desfaz qualquer inserção parcial e informa o usuário
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }
            $_SESSION['error_message'] = 'Importação inválida: ' . $e->getMessage();
            // Anexa detalhes das linhas que falharam, se houver
            if (!empty($errors)) {
                $_SESSION['import_errors'] = $errors;
            }
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }

        // Prepara mensagem de retorno
        $msgs = [];
        if ($inserted > 0) $msgs[] = "{$inserted} lançamentos importados com sucesso.";
        if (!empty($errors)) {
            // grava erros no session como lista (limitado)
            $_SESSION['error_message'] = 'Algumas linhas não foram importadas. Veja detalhes abaixo.';
            $_SESSION['import_errors'] = $errors;
        } else {
            $_SESSION['success_message'] = implode(' ', $msgs);
        }
        // Se houver mensagens de sucesso e erro simultâneas, prioriza exibir erro + sucesso
        if (!empty($errors) && $inserted > 0) {
            $_SESSION['success_message'] = implode(' ', $msgs);
        }

        header('Location: ' . base_url('index.php?page=lancamentos'));
        exit;
        break;

    case 'cadastrar_lancamento':
        if (isAdmin() || isContador() || isClient()) {
            $id_empresa = $_POST['id_empresa'] ?? null;
            // se não enviado, tenta usar a empresa atual da sessão
            if (empty($id_empresa)) {
                $id_empresa = current_company_id();
            }
            if (empty($id_empresa)) {
                $_SESSION['error_message'] = 'Selecione a empresa para o lançamento.';
                header('Location: ' . base_url('index.php?page=lancamentos'));
                exit;
            }
            // Verifica se o usuário tem associação com a empresa (a menos que seja admin global)
            if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $id_empresa)) {
                $_SESSION['error_message'] = 'Você não tem permissão para criar lançamentos nessa empresa.';
                header('Location: ' . base_url('index.php?page=lancamentos'));
                exit;
            }
            $descricao = $_POST['descricao'] ?? null;
            $valor = $_POST['valor'] ?? null;
            $tipo = $_POST['tipo'] ?? 'receita';
            $data_vencimento = $_POST['data_vencimento'] ?? null;
            $data_competencia = $_POST['data_competencia'] ?? null;
            // Normaliza valor vindo de input type="month" (YYYY-MM) para data completa YYYY-MM-01
            if (!empty($data_competencia)) {
                if (preg_match('/^\d{4}-\d{2}$/', $data_competencia)) {
                    $data_competencia = $data_competencia . '-01';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_competencia)) {
                    // já está em formato completo
                } else {
                    $ts = strtotime($data_competencia);
                    $data_competencia = $ts ? date('Y-m-d', $ts) : null;
                }
            } else {
                $data_competencia = null;
            }
            $data_pagamento = $_POST['data_pagamento'] ?? null;
            $metodo_pagamento = $_POST['metodo_pagamento'] ?? null;
            $id_forma = isset($_POST['id_forma_pagamento']) ? ($_POST['id_forma_pagamento'] !== '' ? $_POST['id_forma_pagamento'] : null) : null;
            $id_categoria = isset($_POST['id_categoria']) ? ($_POST['id_categoria'] !== '' ? $_POST['id_categoria'] : null) : null;
            $status = $_POST['status'] ?? 'pendente';

            // Detecta colunas opcionais
            $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_forma_pagamento'");
            $colStmt->execute();
            $has_forma_col = $colStmt->fetchColumn() > 0;
            $colStmt2 = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_categoria'");
            $colStmt2->execute();
            $has_categoria_col = $colStmt2->fetchColumn() > 0;

            // Monta colunas e params dinamicamente (usa coluna de empresa detectada dinamicamente)
            $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
            $cols = array_merge([$company_col], ['descricao','valor','tipo','data_vencimento','data_competencia','data_pagamento','metodo_pagamento']);
            $params = [$id_empresa,$descricao,$valor,$tipo,$data_vencimento,$data_competencia ?: null,$data_pagamento ?: null,$metodo_pagamento];
            if ($has_forma_col) { $cols[] = 'id_forma_pagamento'; $params[] = $id_forma; }
            if ($has_categoria_col) { $cols[] = 'id_categoria'; $params[] = $id_categoria; }
            $cols[] = 'status'; $params[] = $status;

            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO lancamentos (" . implode(',', $cols) . ") VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            try {
                if ($stmt->execute($params)) {
                    $novo_id = $pdo->lastInsertId();
                    $_SESSION['success_message'] = 'Lançamento criado com sucesso.';
                    logAction('Criou Lançamento', 'lancamentos', $novo_id, $descricao);
                } else {
                    $_SESSION['error_message'] = 'Erro ao criar lançamento.';
                }
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Erro ao criar lançamento: ' . $e->getMessage();
            }
            header('Location: ' . base_url('index.php?page=lancamentos'));
            exit;
        }
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
            // Normaliza valor vindo de input type="month" (YYYY-MM) para data completa YYYY-MM-01
            if (!empty($data_competencia_novo)) {
                if (preg_match('/^\d{4}-\d{2}$/', $data_competencia_novo)) {
                    $data_competencia_novo = $data_competencia_novo . '-01';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_competencia_novo)) {
                    // já está em formato completo
                } else {
                    $ts = strtotime($data_competencia_novo);
                    $data_competencia_novo = $ts ? date('Y-m-d', $ts) : null;
                }
            } else {
                $data_competencia_novo = null;
            }
            $metodo_pagamento_novo = $_POST['metodo_pagamento'] ?? null;
            $id_forma_pagamento_novo = isset($_POST['id_forma_pagamento']) ? ($_POST['id_forma_pagamento'] !== '' ? $_POST['id_forma_pagamento'] : null) : null;
            $id_categoria_novo = isset($_POST['id_categoria']) ? ($_POST['id_categoria'] !== '' ? $_POST['id_categoria'] : null) : null;
            // Detecta se a coluna id_forma_pagamento existe
            $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_forma_pagamento'");
            $colStmt->execute();
            $has_forma_col = $colStmt->fetchColumn() > 0;
            // Detecta se a coluna id_categoria existe
            $colStmtCat = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lancamentos' AND COLUMN_NAME = 'id_categoria'");
            $colStmtCat->execute();
            $has_categoria_col = $colStmtCat->fetchColumn() > 0;
            $status_novo = $_POST['status']; // New: status can be edited

            // 1. AUDITORIA: Busca dados antigos
            // Seleciona colunas antigas para auditoria (inclui id_forma_pagamento e id_categoria quando existirem)
            $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
            // alias company column to empresa_ref for consistent access
            $selectCols = ["{$company_col} AS empresa_ref",'descricao','valor','tipo','data_vencimento','data_competencia','metodo_pagamento','status'];
            if ($has_forma_col) $selectCols[] = 'id_forma_pagamento';
            if ($has_categoria_col) $selectCols[] = 'id_categoria';
            $stmt_old = $pdo->prepare("SELECT " . implode(',', $selectCols) . " FROM lancamentos WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
            // Security: ensure non-admin users can only edit lancamentos for companies they belong to
            if (!isAdmin()) {
                $empresa_antiga = $old_data['empresa_ref'] ?? null;
                if ($empresa_antiga === null) {
                    $_SESSION['error_message'] = 'Empresa vinculada ao lançamento não encontrada.';
                    header('Location: ' . base_url('index.php?page=lancamentos'));
                    exit;
                }
                if (!user_has_any_role($_SESSION['user_id'], $empresa_antiga)) {
                    $_SESSION['error_message'] = 'Você não tem permissão para editar este lançamento.';
                    header('Location: ' . base_url('index.php?page=lancamentos'));
                    exit;
                }
            }
            
            // 2. AUDITORIA: Compara e monta a string de detalhes
            $detalhes_log = [];
            
          if (($old_data['empresa_ref'] ?? null) !== $id_empresa_novo) {
              $stmt_empresa = $pdo->prepare("SELECT razao_social FROM empresas WHERE id = ?");
              $stmt_empresa->execute([$id_empresa_novo]);
              $nome_empresa_novo = $stmt_empresa->fetchColumn() ?? 'N/D';
              $stmt_empresa->execute([$old_data['empresa_ref'] ?? 0]);
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
                $detalhes_log[] = "Competência: " . ($old_data['data_competencia'] ? date('m/Y', strtotime($old_data['data_competencia'])) : 'N/D') . " -> " . ($data_competencia_novo ? date('m/Y', strtotime($data_competencia_novo)) : 'N/D');
            }
            if (($old_data['metodo_pagamento'] ?? null) !== ($metodo_pagamento_novo ?? null) || ($has_forma_col && (($old_data['id_forma_pagamento'] ?? null) !== ($id_forma_pagamento_novo ?? null)))) {
                $det_old = ($old_data['metodo_pagamento'] ?? 'N/D') . ($has_forma_col ? (' (id: ' . ($old_data['id_forma_pagamento'] ?? 'N/D') . ')') : '');
                $det_new = ($metodo_pagamento_novo ?? 'N/D') . ($has_forma_col ? (' (id: ' . ($id_forma_pagamento_novo ?? 'N/D') . ')') : '');
                $detalhes_log[] = "Forma Pgto: " . $det_old . " -> " . $det_new;
            }
            // Categoria
            if ($has_categoria_col) {
                if (($old_data['id_categoria'] ?? null) !== ($id_categoria_novo ?? null)) {
                    // busca nomes para log amigável
                    $old_cat_name = 'N/D';
                    $new_cat_name = 'N/D';
                    if (!empty($old_data['id_categoria'])) {
                        $s = $pdo->prepare("SELECT nome FROM categorias_lancamento WHERE id = ?");
                        $s->execute([$old_data['id_categoria']]);
                        $old_cat_name = $s->fetchColumn() ?? 'N/D';
                    }
                    if (!empty($id_categoria_novo)) {
                        $s2 = $pdo->prepare("SELECT nome FROM categorias_lancamento WHERE id = ?");
                        $s2->execute([$id_categoria_novo]);
                        $new_cat_name = $s2->fetchColumn() ?? 'N/D';
                    }
                    $detalhes_log[] = "Categoria: {$old_cat_name} -> {$new_cat_name}";
                }
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
            // Monta UPDATE dinâmico conforme colunas presentes (usa nome de coluna de empresa dinamicamente)
            $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
            $update_fields = [
                "{$company_col} = ?", 'descricao = ?', 'valor = ?', 'tipo = ?', 'data_vencimento = ?', 'data_competencia = ?', 'metodo_pagamento = ?'
            ];
            $update_params = [$id_empresa_novo, $descricao_novo, $valor_novo, $tipo_novo, $data_vencimento_novo, $data_competencia_novo, $metodo_pagamento_novo];
            if ($has_forma_col) {
                $update_fields[] = 'id_forma_pagamento = ?';
                $update_params[] = $id_forma_pagamento_novo;
            }
            if ($has_categoria_col) {
                $update_fields[] = 'id_categoria = ?';
                $update_params[] = $id_categoria_novo;
            }
            $update_fields[] = 'status = ?';
            $update_params[] = $status_novo;

            $sql = "UPDATE lancamentos SET " . implode(', ', $update_fields) . " WHERE id = ?";
            $update_params[] = $id;
            $params = $update_params;
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
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

    case 'excluir_lancamento': // Ensure company scoping before delete
        if (isAdmin() || isContador() || isClient()) {
            $id = intval($_GET['id'] ?? 0);
            if ($id) {
                try {
                    $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                    $stmtC = $pdo->prepare("SELECT `" . $company_col . "` FROM lancamentos WHERE id = ?");
                    $stmtC->execute([$id]);
                    $empresa_ref = $stmtC->fetchColumn();
                    if ($empresa_ref === false) {
                        $_SESSION['error_message'] = 'Lançamento não encontrado.';
                        header('Location: ' . base_url('index.php?page=lancamentos'));
                        exit;
                    }
                    if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $empresa_ref)) {
                        $_SESSION['error_message'] = 'Você não tem permissão para excluir este lançamento.';
                        header('Location: ' . base_url('index.php?page=lancamentos'));
                        exit;
                    }
                    $sql = "DELETE FROM lancamentos WHERE id = ? AND `" . $company_col . "` = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$id, $empresa_ref])) {
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
            // Fetch old lancamento data including company for permission checks
            $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
            $stmt_old_data = $pdo->prepare("SELECT status, data_vencimento, data_pagamento, metodo_pagamento, `" . $company_col . "` AS empresa_ref FROM lancamentos WHERE id = ?");
            $stmt_old_data->execute([$id]);
            $old_lancamento_data = $stmt_old_data->fetch(PDO::FETCH_ASSOC);

            if (!$old_lancamento_data) {
                $_SESSION['error_message'] = "Lançamento não encontrado.";
                break;
            }

            // Permission: non-admins must be associated to the company
            if (!isAdmin()) {
                $empresa_ref = $old_lancamento_data['empresa_ref'] ?? null;
                if ($empresa_ref === null) {
                    $_SESSION['error_message'] = 'Empresa vinculada ao lançamento não encontrada.';
                    break;
                }
                if (!user_has_any_role($_SESSION['user_id'], $empresa_ref)) {
                    $_SESSION['error_message'] = 'Você não tem permissão para alterar o status deste lançamento.';
                    break;
                }
            }

            if (!$old_lancamento_data) {
                $_SESSION['error_message'] = "Lançamento não encontrado.";
                error_log("DEBUG: Lançamento ID $id não encontrado.");
                header("Location: " . base_url('index.php?page=lancamentos') . '&_t=' . time());
                exit;
            }

            $old_status = $old_lancamento_data['status'];
            $old_data_pagamento = $old_lancamento_data['data_pagamento'];
                $data_pagamento = $_POST['data_pagamento'] ?? null;
                $metodo_pagamento = $_POST['metodo_pagamento'] ?? null;
                $id_forma_pagamento = isset($_POST['id_forma_pagamento']) ? ($_POST['id_forma_pagamento'] !== '' ? $_POST['id_forma_pagamento'] : null) : null;
            error_log("DEBUG: Old Status: $old_status, Old Data Pagamento: $old_data_pagamento, Old Metodo Pagamento: $old_metodo_pagamento");

            $log_details = [];
            $update_fields = [];
            $update_params = [];

            // Handle status change
                if (!$old_lancamento_data) {
                    $_SESSION['error_message'] = "Lançamento não encontrado.";
                    error_log("DEBUG: Lançamento ID $id não encontrado.");
                    header("Location: " . base_url('index.php?page=lancamentos') . '&_t=' . time());
                    exit;
                }
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
                    if ($id_forma_pagamento !== null && $id_forma_pagamento != ($old_lancamento_data['id_forma_pagamento'] ?? null)) {
                        $update_fields[] = "id_forma_pagamento = ?";
                        $update_params[] = $id_forma_pagamento;
                        $log_details[] = "Forma Pgto (id): " . (($old_lancamento_data['id_forma_pagamento'] ?? 'N/D')) . " -> " . $id_forma_pagamento;
                    }
                    
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
                        // Limpa id_forma_pagamento também se existir
                        if ($has_forma_col) {
                            $update_fields[] = "id_forma_pagamento = NULL";
                            $log_details[] = "Forma Pgto (id): " . (($old_lancamento_data['id_forma_pagamento'] ?? 'N/D')) . " -> NULL";
                        }
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
            $data_contratacao = !empty($_POST['data_contratacao']) ? $_POST['data_contratacao'] : null;

            $sql = "INSERT INTO empresas (id_cliente, cnpj, razao_social, nome_fantasia, data_abertura) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $sql = "INSERT INTO empresas (id_cliente, cnpj, razao_social, nome_fantasia, data_abertura, data_contratacao) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$id_cliente, $cnpj, $_POST['razao_social'], $_POST['nome_fantasia'], $data_abertura, $data_contratacao])) {
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
             if (!empty($id_empresa)) ensure_user_can_access_company($id_empresa);
             $data_abertura = !empty($_POST['data_abertura']) ? $_POST['data_abertura'] : null;
             $data_contratacao = !empty($_POST['data_contratacao']) ? $_POST['data_contratacao'] : null;

             $sql_update = "UPDATE empresas SET id_cliente = ?, cnpj = ?, razao_social = ?, nome_fantasia = ?, data_abertura = ?, data_contratacao = ? WHERE id = ?";
             $stmt_update = $pdo->prepare($sql_update);
             
             if ($stmt_update->execute([$_POST['id_cliente'], $_POST['cnpj'], $_POST['razao_social'], $_POST['nome_fantasia'], $data_abertura, $data_contratacao, $id_empresa])) {
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
             if (!empty($id_empresa)) ensure_user_can_access_company($id_empresa);
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
            // Flags de preferência de envio de e-mail
            $receber_cobrancas = isset($_POST['receber_novas_cobrancas_email']) ? 1 : 0;
            $receber_recibos = isset($_POST['receber_recibos_email']) ? 1 : 0;

            // 1) Insere cliente
            $sql = "INSERT INTO clientes (nome_responsavel, email_contato, telefone) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$_POST['nome_responsavel'], $_POST['email_contato'], $_POST['telefone']])) {
                $id_novo = $pdo->lastInsertId();

                // Se o usuário atual for um contador, auto-associa o novo cliente a ele
                try {
                    if (isContador()) {
                        $stmt_assoc_ins = $pdo->prepare("INSERT INTO contador_clientes_assoc (id_usuario_contador, id_cliente) VALUES (?, ?)");
                        $stmt_assoc_ins->execute([$_SESSION['user_id'], $id_novo]);
                    }
                } catch (Exception $e) {
                    error_log('Falha ao associar contador ao novo cliente: ' . $e->getMessage());
                }

                // 2) Garante existência da tabela simples de configuração e insere as flags
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS tb_confg_emailCliente (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        id_client INT NOT NULL,
                        permissao VARCHAR(100) NULL,
                        descricao TEXT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_id_client (id_client),
                        INDEX idx_permissao (permissao)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                    // Insert mapping rows according to checked flags (avoid duplicates by checking existence)
                    if ($receber_cobrancas) {
                        $chk = $pdo->prepare("SELECT 1 FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ? LIMIT 1");
                        $chk->execute([$id_novo, 'receber_novas_cobrancas']);
                        if (!$chk->fetchColumn()) {
                            $ins = $pdo->prepare("INSERT INTO tb_confg_emailCliente (id_client, permissao, descricao) VALUES (?, ?, ?)");
                            $ins->execute([$id_novo, 'receber_novas_cobrancas', 'Envia cobrança via email do cliente de forma automática']);
                        }
                    }
                    if ($receber_recibos) {
                        $chk = $pdo->prepare("SELECT 1 FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ? LIMIT 1");
                        $chk->execute([$id_novo, 'receber_recibos']);
                        if (!$chk->fetchColumn()) {
                            $ins = $pdo->prepare("INSERT INTO tb_confg_emailCliente (id_client, permissao, descricao) VALUES (?, ?, ?)");
                            $ins->execute([$id_novo, 'receber_recibos', 'Envia recibo de pagamento via email do cliente de forma automática']);
                        }
                    }
                } catch (Exception $e) {
                    // Se falhar (permissões), apenas logamos e continuamos sem bloquear a criação do cliente
                    error_log('Falha ao criar/atualizar tb_confg_emailCliente: ' . $e->getMessage());
                }

                $_SESSION['success_message'] = "Cliente cadastrado com sucesso!";
                logAction("Cadastro Cliente", "clientes", $id_novo, "Nome: " . $_POST['nome_responsavel']);
            } else {
                 $_SESSION['error_message'] = "Erro ao cadastrar cliente.";
            }
         }
        break;

    case 'editar_cliente':
         if (isAdmin() || isContador()) {
                // Se for contador, verificar associação com o cliente alvo
                if (isContador()) {
                    $chkAssoc = $pdo->prepare("SELECT 1 FROM contador_clientes_assoc WHERE id_usuario_contador = ? AND id_cliente = ? LIMIT 1");
                    $chkAssoc->execute([$_SESSION['user_id'], $_POST['id_cliente']]);
                    if (!$chkAssoc->fetchColumn()) {
                        $_SESSION['error_message'] = 'Você não tem permissão para editar este cliente.';
                        break;
                    }
                }
            $id = $_POST['id_cliente'];
            $nome_novo = $_POST['nome_responsavel'];
            $email_novo = $_POST['email_contato'];
            $telefone_novo = $_POST['telefone'];
            $receber_cobrancas_novo = isset($_POST['receber_novas_cobrancas_email']) ? 1 : 0;
            $receber_recibos_novo = isset($_POST['receber_recibos_email']) ? 1 : 0;

            // 1. AUDITORIA: Busca dados antigos do cliente e das preferências na tabela separada
            $stmt_old = $pdo->prepare("SELECT c.nome_responsavel, c.email_contato, c.telefone,
                (SELECT 1 FROM tb_confg_emailCliente ec WHERE ec.id_client = c.id AND ec.permissao = 'receber_novas_cobrancas' LIMIT 1) AS receber_novas_cobrancas_email,
                (SELECT 1 FROM tb_confg_emailCliente ec2 WHERE ec2.id_client = c.id AND ec2.permissao = 'receber_recibos' LIMIT 1) AS receber_recibos_email
                FROM clientes c WHERE c.id = ?");
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
            if (intval($old_data['receber_novas_cobrancas_email'] ?? 0) !== $receber_cobrancas_novo) {
                $detalhes_log[] = "Receber novas cobranças por email: " . intval($old_data['receber_novas_cobrancas_email'] ?? 0) . " -> " . $receber_cobrancas_novo;
            }
            if (intval($old_data['receber_recibos_email'] ?? 0) !== $receber_recibos_novo) {
                $detalhes_log[] = "Receber recibos por email: " . intval($old_data['receber_recibos_email'] ?? 0) . " -> " . $receber_recibos_novo;
            }
            
            // Se nada mudou, cancela o log e a atualização
            if (empty($detalhes_log)) {
                 $_SESSION['success_message'] = "Cliente não alterado (nenhuma mudança detectada).";
                 break;
            }
            
            $log_details_string = "Campos alterados: " . implode('; ', $detalhes_log);


            // 3. Atualiza o banco de dados
            // Atualiza dados do cliente
            $sql = "UPDATE clientes SET nome_responsavel = ?, email_contato = ?, telefone = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$nome_novo, $email_novo, $telefone_novo, $id])) {
                // Upsert das preferências na tabela separada (simple mapping)
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS tb_confg_emailCliente (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        id_client INT NOT NULL,
                        permissao VARCHAR(100) NULL,
                        descricao TEXT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_id_client (id_client),
                        INDEX idx_permissao (permissao)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                    // receber_novas_cobrancas: insert/delete accordingly
                    $chk = $pdo->prepare("SELECT 1 FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ? LIMIT 1");
                    $chk->execute([$id, 'receber_novas_cobrancas']);
                    $exists = (bool) $chk->fetchColumn();
                    if ($receber_cobrancas_novo && !$exists) {
                        $ins = $pdo->prepare("INSERT INTO tb_confg_emailCliente (id_client, permissao, descricao) VALUES (?, ?, ?)");
                        $ins->execute([$id, 'receber_novas_cobrancas', 'Envia cobrança via email do cliente de forma automática']);
                    } elseif (!$receber_cobrancas_novo && $exists) {
                        $del = $pdo->prepare("DELETE FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ?");
                        $del->execute([$id, 'receber_novas_cobrancas']);
                    }

                    // receber_recibos: insert/delete accordingly
                    $chk2 = $pdo->prepare("SELECT 1 FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ? LIMIT 1");
                    $chk2->execute([$id, 'receber_recibos']);
                    $exists2 = (bool) $chk2->fetchColumn();
                    if ($receber_recibos_novo && !$exists2) {
                        $ins2 = $pdo->prepare("INSERT INTO tb_confg_emailCliente (id_client, permissao, descricao) VALUES (?, ?, ?)");
                        $ins2->execute([$id, 'receber_recibos', 'Envia recibo de pagamento via email do cliente de forma automática']);
                    } elseif (!$receber_recibos_novo && $exists2) {
                        $del2 = $pdo->prepare("DELETE FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ?");
                        $del2->execute([$id, 'receber_recibos']);
                    }
                } catch (Exception $e) {
                    error_log('Falha ao criar/atualizar tb_confg_emailCliente (editar): ' . $e->getMessage());
                }

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
            // Impedir edição da conta Super Admin por segurança
            $chkSuper = $pdo->prepare('SELECT is_super_admin FROM usuarios WHERE id = ? LIMIT 1');
            $chkSuper->execute([$id_usuario_edit]);
            $isSuperUser = intval($chkSuper->fetchColumn() ?? 0);
            if ($isSuperUser) {
                $_SESSION['error_message'] = 'Ação não permitida: esta conta é Super Admin e não pode ser editada via interface.';
                break;
            }
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
            // Impedir exclusão da conta Super Admin
            $chkSuperDel = $pdo->prepare('SELECT is_super_admin FROM usuarios WHERE id = ? LIMIT 1');
            $chkSuperDel->execute([$id]);
            if (intval($chkSuperDel->fetchColumn() ?? 0)) {
                $_SESSION['error_message'] = 'Ação não permitida: esta conta é Super Admin e não pode ser excluída.';
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
            if (!empty($id_empresa)) ensure_user_can_access_company($id_empresa);
            $data_competencia = $_POST['data_competencia'] ?? null;
            // Normaliza valor vindo de input type="month" (YYYY-MM) para data completa YYYY-MM-01
            if (!empty($data_competencia)) {
                if (preg_match('/^\d{4}-\d{2}$/', $data_competencia)) {
                    $data_competencia = $data_competencia . '-01';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_competencia)) {
                    // já está em formato completo
                } else {
                    // tenta normalizar usando strtotime; se falhar, zera para null
                    $ts = strtotime($data_competencia);
                    $data_competencia = $ts ? date('Y-m-d', $ts) : null;
                }
            } else {
                $data_competencia = null;
            }
            $data_vencimento = $_POST['data_vencimento'];
            $valor = $_POST['valor'];
            $id_forma_pagamento = $_POST['id_forma_pagamento'];
                $id_tipo_cobranca = $_POST['id_tipo_cobranca'] ?? null;
                $id_empresa = $_POST['id_empresa'] ?? null;
                if (empty($id_empresa)) {
                    $id_empresa = current_company_id();
                }
                if (empty($id_empresa)) {
                    $_SESSION['error_message'] = 'Selecione a empresa para criar a cobrança.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }
                if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $id_empresa)) {
                    $_SESSION['error_message'] = 'Você não tem permissão para criar cobranças nessa empresa.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }
            $descricao = $_POST['descricao'];
            $contexto_pagamento = $_POST['contexto_pagamento'] ?? null;

            try {
        // use dynamic company column name
        $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
        $sql = "INSERT INTO cobrancas ({$company_col}, data_competencia, data_vencimento, valor, id_forma_pagamento, id_tipo_cobranca, descricao, contexto_pagamento, status_pagamento) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendente')";
        $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$id_empresa, $data_competencia, $data_vencimento, $valor, $id_forma_pagamento, $id_tipo_cobranca, $descricao, $contexto_pagamento])) {
                    $_SESSION['success_message'] = "Cobrança gerada com sucesso!";
                    $newId = $pdo->lastInsertId();
                    logAction("Gerou Cobrança", "cobrancas", $newId, "Valor: R$ $valor para empresa ID: $id_empresa");

                    // Após criar a cobrança, tenta envio automático se o cliente da empresa autorizou
                    try {
                        // Busca empresa + cliente
                        $stmtEmp = $pdo->prepare("SELECT emp.id_cliente, emp.razao_social, cli.nome_responsavel, cli.email_contato
                            FROM empresas emp
                            LEFT JOIN clientes cli ON emp.id_cliente = cli.id
                            WHERE emp.id = ? LIMIT 1");
                        $stmtEmp->execute([$id_empresa]);
                        $empresa_info = $stmtEmp->fetch(PDO::FETCH_ASSOC);

                        if ($empresa_info && !empty($empresa_info['id_cliente'])) {
                            $id_cliente = $empresa_info['id_cliente'];

                            // Verifica permissao na tabela de mapeamento
                            $chk = $pdo->prepare("SELECT 1 FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ? LIMIT 1");
                            $chk->execute([$id_cliente, 'receber_novas_cobrancas']);

                            if ($chk->fetchColumn()) {
                                // Só tenta enviar se houver email de contato
                                $toEmail = $empresa_info['email_contato'] ?? null;
                                $toName = $empresa_info['nome_responsavel'] ?? $empresa_info['razao_social'] ?? 'Cliente';

                                if (!empty($toEmail)) {
                                    // Resolve nomes de forma e tipo (como no envio manual)
                                    $forma_nome = '';
                                    if (!empty($id_forma_pagamento)) {
                                        try {
                                            $stmtFp = $pdo->prepare('SELECT nome FROM formas_pagamento WHERE id = ? LIMIT 1');
                                            $stmtFp->execute([$id_forma_pagamento]);
                                            $forma_nome = $stmtFp->fetchColumn() ?: '';
                                        } catch (Exception $e) {
                                            $forma_nome = '';
                                        }
                                    }
                                    $tipo_nome = 'receita';
                                    if (!empty($id_tipo_cobranca)) {
                                        try {
                                            $stmtTipo = $pdo->prepare('SELECT nome FROM tipos_cobranca WHERE id = ? LIMIT 1');
                                            $stmtTipo->execute([$id_tipo_cobranca]);
                                            $tipo_nome = $stmtTipo->fetchColumn() ?: $tipo_nome;
                                        } catch (Exception $e) {
                                            // mantém fallback
                                        }
                                    }

                                    $lancamento_like = [
                                        'id' => $newId,
                                        'descricao' => $descricao ?? 'Cobrança',
                                        'valor' => $valor,
                                        'data_vencimento' => $data_vencimento,
                                        'tipo' => $tipo_nome,
                                        'forma_pagamento' => $forma_nome,
                                        'contexto_pagamento' => $contexto_pagamento ?? ''
                                    ];

                                    $sentAuto = sendNotificationEmail($toEmail, $toName, $lancamento_like);
                                    if ($sentAuto) {
                                        logAction('Envio Automático Cobrança', 'cobrancas', $newId, 'Enviado automaticamente ao cliente id ' . $id_cliente);
                                    } else {
                                        logAction('Falha Envio Automático Cobrança', 'cobrancas', $newId, 'Tentativa automática ao cliente id ' . $id_cliente);
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Erro ao tentar envio automático de cobrança: ' . $e->getMessage());
                    }

                    // Removida: geração automática de PNG de boleto ao criar cobrança (implementação anterior revertida)
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
            $id = intval($_GET['id'] ?? 0);
            if ($id) {
                try {
                    $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                    $stmtC = $pdo->prepare("SELECT `" . $company_col . "` FROM cobrancas WHERE id = ?");
                    $stmtC->execute([$id]);
                    $empresa_ref = $stmtC->fetchColumn();
                    if ($empresa_ref === false) {
                        $_SESSION['error_message'] = 'Cobrança não encontrada.';
                        header('Location: ' . base_url('index.php?page=cobrancas'));
                        exit;
                    }
                    if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $empresa_ref)) {
                        $_SESSION['error_message'] = 'Você não tem permissão para excluir esta cobrança.';
                        header('Location: ' . base_url('index.php?page=cobrancas'));
                        exit;
                    }
                    $sql = "DELETE FROM cobrancas WHERE id = ? AND `" . $company_col . "` = ?";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute([$id, $empresa_ref])) {
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
            if (!empty($id_empresa)) ensure_user_can_access_company($id_empresa);
            $data_competencia = $_POST['data_competencia'] ?? null;
            // Normaliza valor vindo de input type="month" (YYYY-MM) para data completa YYYY-MM-01
            if (!empty($data_competencia)) {
                if (preg_match('/^\d{4}-\d{2}$/', $data_competencia)) {
                    $data_competencia = $data_competencia . '-01';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_competencia)) {
                    // já está em formato completo
                } else {
                    $ts = strtotime($data_competencia);
                    $data_competencia = $ts ? date('Y-m-d', $ts) : null;
                }
            } else {
                $data_competencia = null;
            }
            $data_vencimento = $_POST['data_vencimento'];
            $valor = $_POST['valor'];
            $id_forma_pagamento = $_POST['id_forma_pagamento'];
            $id_tipo_cobranca = $_POST['id_tipo_cobranca'] ?? null;
            $descricao = $_POST['descricao'];
            $contexto_pagamento = $_POST['contexto_pagamento'] ?? null;

            // If id_empresa not provided, fallback to current company
            if (empty($id_empresa)) $id_empresa = current_company_id();
            if (empty($id_empresa)) {
                $_SESSION['error_message'] = 'Selecione a empresa para a cobrança.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }
            // Permission: non-admin must be associated to the selected company
            if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $id_empresa)) {
                $_SESSION['error_message'] = 'Você não tem permissão para editar cobranças nesta empresa.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            try {
                $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                $sql = "UPDATE cobrancas SET 
                            {$company_col} = ?, 
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
            // Novos campos permitidos na confirmação: forma de pagamento e contexto
            $id_forma_pagamento = isset($_POST['id_forma_pagamento']) ? ($_POST['id_forma_pagamento'] === '' ? null : $_POST['id_forma_pagamento']) : null;
            $contexto_pagamento = isset($_POST['contexto_pagamento']) ? trim($_POST['contexto_pagamento']) : null;

            if (!$id || !$data_pagamento) {
                $_SESSION['error_message'] = "Erro: ID da cobrança ou data de pagamento não fornecidos.";
                break;
            }

            try {
                // 1. Obter a data de vencimento da cobrança e a empresa associada
                $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                $stmt_vencimento = $pdo->prepare("SELECT data_vencimento, `" . $company_col . "` AS empresa_ref FROM cobrancas WHERE id = ?");
                $stmt_vencimento->execute([$id]);
                $cobranca = $stmt_vencimento->fetch(PDO::FETCH_ASSOC);

                if (!$cobranca) {
                    $_SESSION['error_message'] = "Erro: Cobrança não encontrada.";
                    break;
                }

                // Permission check
                if (!isAdmin()) {
                    $empresa_ref = $cobranca['empresa_ref'] ?? null;
                    if ($empresa_ref === null) {
                        $_SESSION['error_message'] = 'Empresa vinculada à cobrança não encontrada.';
                        break;
                    }
                    if (!user_has_any_role($_SESSION['user_id'], $empresa_ref)) {
                        $_SESSION['error_message'] = 'Você não tem permissão para dar baixa nesta cobrança.';
                        break;
                    }
                }

                $data_vencimento = new DateTime($cobranca['data_vencimento']);
                $data_pagamento_obj = new DateTime($data_pagamento);

                $status_pagamento = 'Pago';

                // 2. Atualizar a cobrança com a data de pagamento, status e opcionalmente forma/contexto
                $sql = "UPDATE cobrancas SET status_pagamento = ?, data_pagamento = ?, id_forma_pagamento = ?, contexto_pagamento = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$status_pagamento, $data_pagamento, $id_forma_pagamento, $contexto_pagamento, $id])) {
                    $_SESSION['success_message'] = "Cobrança marcada como $status_pagamento em " . date('d/m/Y', strtotime($data_pagamento)) . "!";
                    $log_details = "Status: $status_pagamento, Data Pagamento: $data_pagamento";
                    if ($id_forma_pagamento !== null) $log_details .= ", Forma Pagamento ID: $id_forma_pagamento";
                    if (!empty($contexto_pagamento)) $log_details .= ", Contexto: " . substr($contexto_pagamento, 0, 200);
                    logAction("Baixa Cobrança", "cobrancas", $id, $log_details);
                    // Tenta envio automático de recibo se o cliente estiver configurado para receber recibos
                    try {
                        $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                        $stmtRec = $pdo->prepare("SELECT cob.*, emp.razao_social, emp.cnpj, emp.id_cliente AS empresa_cliente, cli.nome_responsavel, cli.email_contato, fp.nome as forma_nome, tc.nome as tipo_nome
                            FROM cobrancas cob
                            JOIN empresas emp ON cob.`" . $company_col . "` = emp.id
                            LEFT JOIN clientes cli ON emp.id_cliente = cli.id
                            LEFT JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id
                            LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id
                            WHERE cob.id = ? LIMIT 1");
                        $stmtRec->execute([$id]);
                        $cobRec = $stmtRec->fetch(PDO::FETCH_ASSOC);
                        if ($cobRec) {
                            $id_cliente_rec = $cobRec['empresa_cliente'] ?? null;
                            if (!empty($id_cliente_rec)) {
                                $chkRec = $pdo->prepare("SELECT 1 FROM tb_confg_emailCliente WHERE id_client = ? AND permissao = ? LIMIT 1");
                                $chkRec->execute([$id_cliente_rec, 'receber_recibos']);
                                if ($chkRec->fetchColumn()) {
                                    $toEmail = $cobRec['email_contato'] ?? null;
                                    $toName = $cobRec['nome_responsavel'] ?? ($cobRec['razao_social'] ?? 'Cliente');
                                    if (!empty($toEmail)) {
                                        // Monta recibo em HTML reutilizando templates
                                        $logo_path = __DIR__ . '/../assets/img/logo.png';
                                        $logo_img = '';
                                        if (file_exists($logo_path)) {
                                            $data = base64_encode(file_get_contents($logo_path));
                                            $logo_img = '<img src="data:image/png;base64,' . $data . '" style="max-height:80px;margin-bottom:10px;">';
                                        }
                                        $logo_url = file_exists($logo_path) ? 'cid:logo_cid' : base_url('assets/img/logo.png');

                                        $templates = getDocumentTemplates();
                                        $recibo_header = $templates['recibo_header'] ?? '';
                                        $recibo_body = $templates['recibo_body'] ?? '';
                                        $recibo_footer = $templates['recibo_footer'] ?? '';

                                        $replacements = [
                                            '{logo}' => $logo_img,
                                            '{empresa}' => htmlspecialchars($cobRec['razao_social'] ?? ''),
                                            '{cnpj}' => htmlspecialchars($cobRec['cnpj'] ?? ''),
                                            '{cliente}' => htmlspecialchars($cobRec['nome_responsavel'] ?? ''),
                                            '{cliente_email}' => htmlspecialchars($cobRec['email_contato'] ?? ''),
                                            '{descricao}' => nl2br(htmlspecialchars($cobRec['descricao'] ?? '')),
                                            '{logo_url}' => htmlspecialchars($logo_url ?? ''),
                                            '{valor}' => number_format($cobRec['valor'], 2, ',', '.'),
                                            '{data_pagamento}' => htmlspecialchars(date('d/m/Y', strtotime($cobRec['data_pagamento'] ?? date('Y-m-d')))),
                                            '{data_vencimento}' => htmlspecialchars(date('d/m/Y', strtotime($cobRec['data_vencimento'] ?? ''))),
                                            '{data_competencia}' => htmlspecialchars(date('d/m/Y', strtotime($cobRec['data_competencia'] ?? ''))),
                                            '{date}' => date('d/m/Y H:i'),
                                            '{tipo}' => htmlspecialchars($cobRec['tipo_nome'] ?? ''),
                                            '{forma}' => htmlspecialchars($cobRec['forma_nome'] ?? ''),
                                            '{contexto}' => nl2br(htmlspecialchars($cobRec['contexto_pagamento'] ?? ''))
                                        ];

                                        $html = '<html><head><meta charset="utf-8"><style>body{font-family: Arial, Helvetica, sans-serif; color:#222} .assinatura{margin-top:40px;display:flex;justify-content:space-between}.assinatura .box{width:45%;text-align:center;padding-top:60px;border-top:1px solid #000}</style></head><body>';
                                        $html .= strtr($recibo_header, $replacements);
                                        $html .= strtr($recibo_body, $replacements);
                                        $html .= strtr($recibo_footer, $replacements);
                                        $html .= '</body></html>';

                                        // Renderiza PDF
                                        $dompdf = new \Dompdf\Dompdf();
                                        $dompdf->loadHtml($html);
                                        $dompdf->setPaper('A4', 'portrait');
                                        $dompdf->render();
                                        $pdfString = $dompdf->output();

                                        // Envio via PHPMailer
                                        $settings = getSmtpSettings();
                                        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                                            try {
                                                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                                                $mail->CharSet = 'UTF-8';
                                                $mail->Encoding = 'base64';
                                                $mail->isSMTP();
                                                $mail->Host = $settings['smtp_host'];
                                                $mail->Port = intval($settings['smtp_port']);
                                                $mail->SMTPAuth = true;
                                                $mail->Username = $settings['smtp_username'];
                                                $mail->Password = $settings['smtp_password'];

                                                $secure = strtolower(trim($settings['smtp_secure'] ?? ''));
                                                if ($secure === 'starttls' || $secure === 'tls') {
                                                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                                                } elseif ($secure === 'ssl') {
                                                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                                                }

                                                $mail->setFrom($settings['email_from'], $settings['email_from_name'] ?? 'Sistema Financeiro');
                                                $mail->addAddress($toEmail, $toName);

                                                if (file_exists($logo_path)) {
                                                    try {
                                                        $mail->addEmbeddedImage($logo_path, 'logo_cid', 'logo.png');
                                                    } catch (Exception $e) {
                                                        error_log('Falha ao embutir logo no envio automático de recibo: ' . $e->getMessage());
                                                    }
                                                }

                                                $templatesMail = getDocumentTemplates();
                                                $email_subject_tpl = $templatesMail['recibo_email_subject'] ?? '';
                                                $email_body_tpl = $templatesMail['recibo_email_body'] ?? '';

                                                $emailRepl = [
                                                    '{id}' => $cobRec['id'],
                                                    '{empresa}' => htmlspecialchars($cobRec['razao_social'] ?? ''),
                                                    '{cnpj}' => htmlspecialchars($cobRec['cnpj'] ?? ''),
                                                    '{cliente}' => htmlspecialchars($cobRec['nome_responsavel'] ?? ''),
                                                    '{cliente_email}' => htmlspecialchars($cobRec['email_contato'] ?? ''),
                                                    '{descricao}' => htmlspecialchars($cobRec['descricao'] ?? ''),
                                                    '{logo}' => $logo_img,
                                                    '{logo_url}' => htmlspecialchars($logo_url ?? ''),
                                                    '{valor}' => number_format($cobRec['valor'], 2, ',', '.'),
                                                    '{data_pagamento}' => htmlspecialchars(date('d/m/Y', strtotime($cobRec['data_pagamento'] ?? date('Y-m-d')))),
                                                    '{data_vencimento}' => htmlspecialchars(date('d/m/Y', strtotime($cobRec['data_vencimento'] ?? ''))),
                                                    '{date}' => date('d/m/Y H:i'),
                                                    '{tipo}' => htmlspecialchars($cobRec['tipo_nome'] ?? ''),
                                                    '{forma}' => htmlspecialchars($cobRec['forma_nome'] ?? ''),
                                                    '{contexto}' => nl2br(htmlspecialchars($cobRec['contexto_pagamento'] ?? ''))
                                                ];

                                                $subject = !empty($email_subject_tpl) ? strtr($email_subject_tpl, $emailRepl) : 'Recibo de Pagamento - Cobrança #' . $cobRec['id'];
                                                $bodyHtml = !empty($email_body_tpl) ? strtr($email_body_tpl, $emailRepl) : '<p>Prezados,</p><p>Em anexo segue o recibo de pagamento referente à cobrança #' . htmlspecialchars($cobRec['id']) . '.</p><p>Atenciosamente,</p>';

                                                $mail->Subject = $subject;
                                                $mail->isHTML(true);
                                                $mail->Body = $bodyHtml;
                                                $mail->addStringAttachment($pdfString, 'recibo_cobranca_' . $cobRec['id'] . '.pdf', 'base64', 'application/pdf');

                                                $sent = $mail->send();
                                                if ($sent) {
                                                    logAction('Envio Automático Recibo', 'cobrancas', $id, 'Enviado automaticamente ao cliente id ' . $id_cliente_rec);
                                                } else {
                                                    logAction('Falha Envio Automático Recibo', 'cobrancas', $id, 'Tentativa automática ao cliente id ' . $id_cliente_rec);
                                                }
                                            } catch (Exception $e) {
                                                error_log('Erro no PHPMailer (auto recibo): ' . $e->getMessage());
                                            }
                                        } else {
                                            error_log('PHPMailer não disponível para envio automático de recibo.');
                                        }
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Erro ao tentar envio automático de recibo: ' . $e->getMessage());
                    }
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
                // Check company permission before reverting
                $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
                $stmtC = $pdo->prepare("SELECT `" . $company_col . "` FROM cobrancas WHERE id = ?");
                $stmtC->execute([$id]);
                $empresa_ref = $stmtC->fetchColumn();
                if ($empresa_ref === false) {
                    $_SESSION['error_message'] = 'Cobrança não encontrada.';
                    break;
                }
                if (!isAdmin() && !user_has_any_role($_SESSION['user_id'], $empresa_ref)) {
                    $_SESSION['error_message'] = 'Você não tem permissão para reverter a baixa desta cobrança.';
                    break;
                }
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

    // --- Geração de PDFs (Recibo e Termo de Quitação) ---
    case 'recibo_pagamento':
        // Gera PDF com recibo da cobrança específica (apenas se pago)
        $id_cobranca = $_GET['id'] ?? $_POST['id'] ?? null;
        if (!$id_cobranca) {
            $_SESSION['error_message'] = 'ID da cobrança ausente.';
            header('Location: ' . base_url('index.php?page=cobrancas'));
            exit;
        }
        try {
            $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
            $sql = "SELECT cob.*, emp.razao_social, emp.cnpj, emp.id_cliente AS empresa_cliente, cli.nome_responsavel, cli.email_contato, fp.nome as forma_nome, tc.nome as tipo_nome
                    FROM cobrancas cob
                    JOIN empresas emp ON cob.`" . $company_col . "` = emp.id
                    LEFT JOIN clientes cli ON emp.id_cliente = cli.id
                    LEFT JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id
                    LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id
                    WHERE cob.id = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_cobranca]);
            $cob = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cob) {
                $_SESSION['error_message'] = 'Cobrança não encontrada.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            // Somente pode gerar recibo se estiver marcada como Pago
            if (($cob['status_pagamento'] ?? '') !== 'Pago') {
                $_SESSION['error_message'] = 'Recibo disponível apenas para cobranças marcadas como Pagas.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            // Permissões: admin/contador podem; clientes apenas do seu cliente associado
            if (isClient()) {
                $id_cliente_logado = $_SESSION['id_cliente_associado'] ?? null;
                $empresa_cliente = $cob['empresa_cliente'] ?? null;
                if ($id_cliente_logado == null || $empresa_cliente == null || intval($id_cliente_logado) !== intval($empresa_cliente)) {
                    $_SESSION['error_message'] = 'Você não tem permissão para acessar este recibo.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }
            }

            // Monta HTML do recibo com espaço para assinatura
            $logo_path = __DIR__ . '/../assets/img/logo.png';
            $logo_img = '';
            if (file_exists($logo_path)) {
                $data = base64_encode(file_get_contents($logo_path));
                $logo_img = '<img src="data:image/png;base64,' . $data . '" style="max-height:80px;margin-bottom:10px;">';
            }
            // For use inside <img src="..."> attributes in email templates
            $logo_url = file_exists($logo_path) ? 'cid:logo_cid' : base_url('assets/img/logo.png');

            // Usa templates configuráveis para recibo (header, body, footer)
            $templates = getDocumentTemplates();
            $recibo_header = $templates['recibo_header'] ?? '';
            $recibo_body = $templates['recibo_body'] ?? '';
            $recibo_footer = $templates['recibo_footer'] ?? '';

            // Prepara valores para substituição
            $replacements = [
                '{logo}' => $logo_img,
                '{empresa}' => htmlspecialchars($cob['razao_social'] ?? ''),
                '{cnpj}' => htmlspecialchars($cob['cnpj'] ?? ''),
                '{cliente}' => htmlspecialchars($cob['nome_responsavel'] ?? ''),
                '{cliente_email}' => htmlspecialchars($cob['email_contato'] ?? ''),
                    '{descricao}' => nl2br(htmlspecialchars($cob['descricao'] ?? '')),
                    '{logo_url}' => htmlspecialchars($logo_url ?? ''),
                    '{valor}' => number_format($cob['valor'], 2, ',', '.'),
                '{data_pagamento}' => htmlspecialchars(date('d/m/Y', strtotime($cob['data_pagamento'] ?? ''))),
                '{data_vencimento}' => htmlspecialchars(date('d/m/Y', strtotime($cob['data_vencimento'] ?? ''))),
                '{data_competencia}' => htmlspecialchars(date('d/m/Y', strtotime($cob['data_competencia'] ?? ''))),
                '{date}' => date('d/m/Y H:i'),
                '{tipo}' => htmlspecialchars($cob['tipo_nome'] ?? ''),
                '{forma}' => htmlspecialchars($cob['forma_nome'] ?? ''),
                '{contexto}' => nl2br(htmlspecialchars($cob['contexto_pagamento'] ?? ''))
            ];

            $html = '<html><head><meta charset="utf-8"><style>body{font-family: Arial, Helvetica, sans-serif; color:#222} .assinatura{margin-top:40px;display:flex;justify-content:space-between}.assinatura .box{width:45%;text-align:center;padding-top:60px;border-top:1px solid #000}</style></head><body>';
            $html .= strtr($recibo_header, $replacements);
            $html .= strtr($recibo_body, $replacements);
       
            $html .= strtr($recibo_footer, $replacements);
            $html .= '</body></html>';

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream('recibo_cobranca_' . $cob['id'] . '.pdf', ['Attachment' => false]);
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao gerar recibo: ' . $e->getMessage();
            header('Location: ' . base_url('index.php?page=cobrancas'));
            exit;
        }
        break;

    case 'enviar_recibo_pagamento':
        // Gera o recibo em PDF e envia por e-mail como anexo (Admin/Contador podem enviar)
        $id_cobranca = $_GET['id'] ?? $_POST['id'] ?? null;
        if (!$id_cobranca) {
            $_SESSION['error_message'] = 'ID da cobrança ausente.';
            header('Location: ' . base_url('index.php?page=cobrancas'));
            exit;
        }
        try {
            $company_col = function_exists('get_company_column_name') ? get_company_column_name() : 'id_empresa';
            $sql = "SELECT cob.*, emp.razao_social, emp.cnpj, emp.id_cliente AS empresa_cliente, cli.nome_responsavel, cli.email_contato, fp.nome as forma_nome, tc.nome as tipo_nome
                    FROM cobrancas cob
                    JOIN empresas emp ON cob.`" . $company_col . "` = emp.id
                    LEFT JOIN clientes cli ON emp.id_cliente = cli.id
                    LEFT JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id
                    LEFT JOIN tipos_cobranca tc ON cob.id_tipo_cobranca = tc.id
                    WHERE cob.id = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_cobranca]);
            $cob = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cob) {
                $_SESSION['error_message'] = 'Cobrança não encontrada.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            // Permissões: admin/contador podem; clientes apenas do seu cliente associado
            if (isClient()) {
                $id_cliente_logado = $_SESSION['id_cliente_associado'] ?? null;
                $empresa_cliente = $cob['empresa_cliente'] ?? null;
                if ($id_cliente_logado == null || $empresa_cliente == null || intval($id_cliente_logado) !== intval($empresa_cliente)) {
                    $_SESSION['error_message'] = 'Você não tem permissão para enviar este recibo.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }
            }

            // Aceita cobranças marcadas como Pago ou Confirmado (flexível)
            $st = strtolower(trim((string)$cob['status_pagamento'] ?? ''));
            if (!in_array($st, ['pago', 'confirmado_cliente', 'confirmado'])) {
                $_SESSION['error_message'] = 'Recibo disponível apenas para cobranças marcadas como Pagas ou Confirmadas.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            // Monta HTML do recibo (reaproveita templates configuráveis)
            $logo_path = __DIR__ . '/../assets/img/logo.png';
            $logo_img = '';
            if (file_exists($logo_path)) {
                $data = base64_encode(file_get_contents($logo_path));
                $logo_img = '<img src="data:image/png;base64,' . $data . '" style="max-height:80px;margin-bottom:10px;">';
            }
            // For use inside <img src="..."> attributes in email templates
            $logo_url = file_exists($logo_path) ? 'cid:logo_cid' : base_url('assets/img/logo.png');

            $templates = getDocumentTemplates();
            $recibo_header = $templates['recibo_header'] ?? '';
            $recibo_body = $templates['recibo_body'] ?? '';
            $recibo_footer = $templates['recibo_footer'] ?? '';

            $replacements = [
                '{id}' => $cob['id'],
                '{logo}' => $logo_img,
                '{logo_url}' => htmlspecialchars($logo_url ?? ''),
                '{empresa}' => htmlspecialchars($cob['razao_social'] ?? ''),
                '{cnpj}' => htmlspecialchars($cob['cnpj'] ?? ''),
                '{cliente}' => htmlspecialchars($cob['nome_responsavel'] ?? ''),
                '{cliente_email}' => htmlspecialchars($cob['email_contato'] ?? ''),
                '{descricao}' => nl2br(htmlspecialchars($cob['descricao'] ?? '')),
                '{valor}' => number_format($cob['valor'], 2, ',', '.'),
                '{data_pagamento}' => htmlspecialchars(date('d/m/Y', strtotime($cob['data_pagamento'] ?? date('Y-m-d')))),
                '{data_vencimento}' => htmlspecialchars(date('d/m/Y', strtotime($cob['data_vencimento'] ?? ''))),
                '{data_competencia}' => htmlspecialchars(date('d/m/Y', strtotime($cob['data_competencia'] ?? ''))),
                '{date}' => date('d/m/Y H:i'),
                '{tipo}' => htmlspecialchars($cob['tipo_nome'] ?? ''),
                '{forma}' => htmlspecialchars($cob['forma_nome'] ?? ''),
                '{contexto}' => nl2br(htmlspecialchars($cob['contexto_pagamento'] ?? ''))
            ];

            $html = '<html><head><meta charset="utf-8"><style>body{font-family: Arial, Helvetica, sans-serif; color:#222} .assinatura{margin-top:40px;display:flex;justify-content:space-between}.assinatura .box{width:45%;text-align:center;padding-top:60px;border-top:1px solid #000}</style></head><body>';
            $html .= strtr($recibo_header, $replacements);
            $html .= strtr($recibo_body, $replacements);
            $html .= strtr($recibo_footer, $replacements);
            $html .= '</body></html>';

            // Renderiza PDF em memória
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfString = $dompdf->output();

            // Envia por e-mail usando PHPMailer (adiciona anexo)
            $settings = getSmtpSettings();
            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $_SESSION['error_message'] = 'PHPMailer não disponível. Execute composer install para habilitar envio de emails.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            $toEmail = $cob['email_contato'] ?? null;
            $toName = $cob['nome_responsavel'] ?? ($cob['razao_social'] ?? 'Cliente');
            if (empty($toEmail)) {
                $_SESSION['error_message'] = 'Email do cliente não encontrado. Verifique cadastro do cliente.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                $mail->isSMTP();
                $mail->Host = $settings['smtp_host'];
                $mail->Port = intval($settings['smtp_port']);
                $mail->SMTPAuth = true;
                $mail->Username = $settings['smtp_username'];
                $mail->Password = $settings['smtp_password'];

                $secure = strtolower(trim($settings['smtp_secure'] ?? ''));
                if ($secure === 'starttls' || $secure === 'tls') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } elseif ($secure === 'ssl') {
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                }

                $mail->setFrom($settings['email_from'], $settings['email_from_name'] ?? 'Sistema Financeiro');
                $mail->addAddress($toEmail, $toName);

                // Embutir logo como CID quando disponível para permitir uso de src="cid:logo_cid" ou {logo_url}
                if (file_exists($logo_path)) {
                    try {
                        $mail->addEmbeddedImage($logo_path, 'logo_cid', 'logo.png');
                    } catch (Exception $e) {
                        error_log('Falha ao embutir logo no envio de recibo: ' . $e->getMessage());
                    }
                }

                // Usa templates configuráveis para assunto/título/corpo do email de recibo
                $templates = getDocumentTemplates();
                $email_subject_tpl = $templates['recibo_email_subject'] ?? '';
                $email_title_tpl = $templates['recibo_email_title'] ?? '';
                $email_body_tpl = $templates['recibo_email_body'] ?? '';

                // Substituições disponíveis no template de email
                $emailRepl = [
                    '{id}' => $cob['id'],
                    '{empresa}' => htmlspecialchars($cob['razao_social'] ?? ''),
                    '{cnpj}' => htmlspecialchars($cob['cnpj'] ?? ''),
                    '{cliente}' => htmlspecialchars($cob['nome_responsavel'] ?? ''),
                    '{cliente_email}' => htmlspecialchars($cob['email_contato'] ?? ''),
                    '{descricao}' => htmlspecialchars($cob['descricao'] ?? ''),
                    '{logo}' => $logo_img,
                    '{logo_url}' => htmlspecialchars($logo_url ?? ''),
                    '{valor}' => number_format($cob['valor'], 2, ',', '.'),
                    '{data_pagamento}' => htmlspecialchars(date('d/m/Y', strtotime($cob['data_pagamento'] ?? date('Y-m-d')))),
                    '{data_vencimento}' => htmlspecialchars(date('d/m/Y', strtotime($cob['data_vencimento'] ?? ''))),
                    '{date}' => date('d/m/Y H:i'),
                    '{tipo}' => htmlspecialchars($cob['tipo_nome'] ?? ''),
                    '{forma}' => htmlspecialchars($cob['forma_nome'] ?? ''),
                    '{contexto}' => nl2br(htmlspecialchars($cob['contexto_pagamento'] ?? ''))
                ];

                // Assunto
                if (!empty($email_subject_tpl)) {
                    $subject = strtr($email_subject_tpl, $emailRepl);
                } else {
                    $subject = 'Recibo de Pagamento - Cobrança #' . $cob['id'];
                }

                // Corpo do email (HTML)
                if (!empty($email_body_tpl)) {
                    $bodyHtml = strtr($email_body_tpl, $emailRepl);
                } else {
                    $bodyHtml = '<p>Prezados,</p><p>Em anexo segue o recibo de pagamento referente à cobrança #' . htmlspecialchars($cob['id']) . '.</p><p>Atenciosamente,</p>';
                }

                // Anexa PDF gerado em memória
                $filename = 'recibo_cobranca_' . $cob['id'] . '.pdf';
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = $bodyHtml;
                $mail->addStringAttachment($pdfString, $filename, 'base64', 'application/pdf');

                $sent = $mail->send();
                if ($sent) {
                    $_SESSION['success_message'] = 'Recibo enviado com sucesso para ' . htmlspecialchars($toEmail);
                    logAction('Enviou Recibo por Email', 'cobrancas', $id_cobranca, 'Email para: ' . $toEmail);
                } else {
                    $_SESSION['error_message'] = 'Falha no envio do recibo por e-mail.';
                    logAction('Falha ao enviar Recibo por Email', 'cobrancas', $id_cobranca, 'Tentativa para: ' . $toEmail);
                }

            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Erro ao enviar email: ' . $e->getMessage();
            }

        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao processar envio do recibo: ' . $e->getMessage();
        }

        header('Location: ' . base_url('index.php?page=cobrancas'));
        exit;
        break;

    case 'termo_quitacao':
        // Gera PDF com termo de quitação — somente para Admin/Contador mediante seleção de cliente ou empresa
        if (!isAdmin() && !isContador()) {
            $_SESSION['error_message'] = 'Acesso negado. Apenas administradores ou contadores podem gerar este termo.';
            header('Location: ' . base_url('index.php?page=cobrancas'));
            exit;
        }
        try {
            $cliente_id = $_GET['cliente_id'] ?? $_POST['cliente_id'] ?? null;
            $empresa_id = $_GET['empresa_id'] ?? $_POST['empresa_id'] ?? null;
            if (empty($cliente_id) && empty($empresa_id)) {
                $_SESSION['error_message'] = 'Selecione um cliente ou uma empresa para gerar o termo de quitação.';
                header('Location: ' . base_url('index.php?page=cobrancas'));
                exit;
            }

            // Se contador, validar associação ao cliente (quando fornecido)
            if (isContador() && $cliente_id) {
                $stmt_assoc = $pdo->prepare('SELECT 1 FROM contador_clientes_assoc WHERE id_usuario_contador = ? AND id_cliente = ? LIMIT 1');
                $stmt_assoc->execute([$_SESSION['user_id'], $cliente_id]);
                if (!$stmt_assoc->fetchColumn()) {
                    $_SESSION['error_message'] = 'Você não tem permissão para gerar termo para este cliente.';
                    header('Location: ' . base_url('index.php?page=cobrancas'));
                    exit;
                }
            }
            // Monta query de acordo com seleção
            if (!empty($empresa_id)) {
                $sql = "SELECT cob.*, emp.razao_social, emp.cnpj, fp.nome as forma_nome
                        FROM cobrancas cob
                        JOIN empresas emp ON cob.`" . $company_col . "` = emp.id
                        LEFT JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id
                        WHERE emp.id = ? AND cob.status_pagamento = 'Pago'
                        ORDER BY cob.data_pagamento ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$empresa_id]);
            } else {
                $sql = "SELECT cob.*, emp.razao_social, emp.cnpj, fp.nome as forma_nome
                        FROM cobrancas cob
                        JOIN empresas emp ON cob.`" . $company_col . "` = emp.id
                        LEFT JOIN formas_pagamento fp ON cob.id_forma_pagamento = fp.id
                        WHERE emp.id_cliente = ? AND cob.status_pagamento = 'Pago'
                        ORDER BY cob.data_pagamento ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$cliente_id]);
            }
            $pagas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Monta HTML com espaço para assinaturas
            $logo_path = __DIR__ . '/../assets/img/logo.png';
            $logo_img = '';
            if (file_exists($logo_path)) {
                $data = base64_encode(file_get_contents($logo_path));
                $logo_img = '<img src="data:image/png;base64,' . $data . '" style="max-height:80px;margin-bottom:10px;">';
            }

            // Usa templates configuráveis para termo
            $templates = getDocumentTemplates();
            $termo_header = $templates['termo_header'] ?? '';
            $termo_body = $templates['termo_body'] ?? '';
            $termo_footer = $templates['termo_footer'] ?? '';

            $html = '<html><head><meta charset="utf-8"><style>body{font-family: Arial, Helvetica, sans-serif; color:#222} table{width:100%;border-collapse:collapse} th,td{border:1px solid #ddd;padding:8px;text-align:left} th{background:#f5f5f5} .assinatura{margin-top:30px;display:flex;justify-content:space-between}.assinatura .box{width:45%;text-align:center;padding-top:60px;border-top:1px solid #000}</style></head><body>';
            // substituições para o termo
            $payments_table_html = '<table><thead><tr><th>#</th><th>Empresa</th><th>Descrição</th><th>Data Pagamento</th><th>Forma</th><th>Valor</th></tr></thead><tbody>';
            $total = 0;
            foreach ($pagas as $p) {
                $payments_table_html .= '<tr>';
                $payments_table_html .= '<td>' . htmlspecialchars($p['id']) . '</td>';
                $payments_table_html .= '<td>' . htmlspecialchars($p['razao_social'] ?? '') . '</td>';
                $payments_table_html .= '<td>' . htmlspecialchars($p['descricao'] ?? '') . '</td>';
                $payments_table_html .= '<td>' . htmlspecialchars(date('d/m/Y', strtotime($p['data_pagamento'] ?? ''))) . '</td>';
                $payments_table_html .= '<td>' . htmlspecialchars($p['forma_nome'] ?? '') . '</td>';
                $payments_table_html .= '<td>R$ ' . number_format($p['valor'], 2, ',', '.') . '</td>';
                $payments_table_html .= '</tr>';
                $total += (float)$p['valor'];
            }
            $payments_table_html .= '</tbody><tfoot><tr><th colspan="5">Total</th><th>R$ ' . number_format($total, 2, ',', '.') . '</th></tr></tfoot></table>';

            $replacements_term = [
                '{logo}' => $logo_img,
                '{logo_url}' => htmlspecialchars($logo_url ?? ''),
                '{date}' => date('d/m/Y'),
                '{payments_table}' => $payments_table_html,
                '{total}' => 'R$ ' . number_format($total, 2, ',', '.')
            ];

            $html .= strtr($termo_header, $replacements_term);
            $html .= strtr($termo_body, $replacements_term);
            // caso o corpo padrão não contenha a tabela, insere abaixo
            if (strpos($termo_body, '{payments_table}') === false) {
                $html .= $payments_table_html;
            }
            
            $html .= strtr($termo_footer, array_merge($replacements_term, ['{date}' => date('d/m/Y H:i')]));
            $html .= '</body></html>';

            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $filename = 'termo_quitacao_' . ($empresa_id ?: $cliente_id) . '.pdf';
            $dompdf->stream($filename, ['Attachment' => false]);
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erro ao gerar termo de quitação: ' . $e->getMessage();
            header('Location: ' . base_url('index.php?page=cobrancas'));
            exit;
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