<?php
// config/functions.php
session_start();
require_once 'db.php'; // Assumindo que este arquivo contém $pdo global
// Autoload de helpers de autenticação/empresa
$authFile = __DIR__ . '/auth.php';
if (file_exists($authFile)) {
    require_once $authFile;
}
// Tentativa de carregar autoload do Composer (PHPMailer)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}
// Carrega biblioteca RBAC (se existir)
$rbacFile = __DIR__ . '/../lib/rbac.php';
if (file_exists($rbacFile)) {
    require_once $rbacFile;
}
// Define timezone padrão para o sistema (evita horários incorretos ao usar date()/DateTime)
date_default_timezone_set('America/Sao_Paulo');
define('BASE_URL', 'http://localhost/DMC-finanças/');
//define('BASE_URL', 'https://jpconsultoriacontabil.dynamicmotioncentury.com.br/');

// Função para gerar URLs absolutas
function base_url($path = '') {
    return BASE_URL . $path;
}

// Verifica se o usuário está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redireciona se não estiver logado
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . base_url('index.php?page=login'));
        exit();
    }
}

// Funções de verificação de tipo (Role)
function isAdmin() {
    return (isLoggedIn() && isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] == 'admin');
}

function isContador() {
    return (isLoggedIn() && isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] == 'contador');
}

function isClient() {
    return (isLoggedIn() && isset($_SESSION['user_tipo']) && $_SESSION['user_tipo'] == 'cliente');
}

// Verifica se o usuário é Super Admin (flag armazenada na tabela `usuarios`).
function isSuperAdmin() {
    if (!isLoggedIn()) return false;
    if (isset($_SESSION['is_super_admin'])) return (bool) $_SESSION['is_super_admin'];
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT is_super_admin FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $val = $stmt->fetchColumn();
        $_SESSION['is_super_admin'] = (bool) $val;
        return (bool) $val;
    } catch (Exception $e) {
        return false;
    }
}

// Função para verificar se o usuário tem acesso à tela de lançamentos
function hasLancamentosAccess() {
    global $pdo;
    if (isAdmin() || isContador()) {
        return true;
    }
    if (isClient() && isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT acesso_lancamentos FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return (bool) $stmt->fetchColumn();
    }
    return false;
}

// Função de Log
function logAction($acao, $tabela = null, $id_registro = null, $detalhes = null) {
    global $pdo;
    
    $id_usuario = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Normaliza valores não numéricos para NULL (previne inserir string vazia)
    if ($id_usuario === '' || $id_usuario === 0 || $id_usuario === '0') {
        $id_usuario = null;
    }

    // Se houver um id de usuário, valida se ele realmente existe na tabela `usuarios`.
    // Caso contrário, força NULL para não violar a FK.
    if ($id_usuario !== null) {
        try {
            $check = $pdo->prepare('SELECT 1 FROM usuarios WHERE id = ? LIMIT 1');
            $check->execute([$id_usuario]);
            if (!$check->fetchColumn()) {
                $id_usuario = null;
            }
        } catch (Exception $e) {
            // Em caso de erro ao checar, evita falhar o fluxo principal: assume NULL
            $id_usuario = null;
        }
    }

    $sql = "INSERT INTO logs (id_usuario, acao, tabela_afetada, id_registro_afetado, detalhes) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    // Faz bind explícito para garantir que NULL seja enviado corretamente ao MySQL
    $stmt->bindValue(1, $id_usuario, $id_usuario === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(2, $acao, PDO::PARAM_STR);
    $stmt->bindValue(3, $tabela, PDO::PARAM_STR);
    $stmt->bindValue(4, $id_registro, $id_registro === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(5, $detalhes, PDO::PARAM_STR);

    $stmt->execute();
}

/**
 * Busca as configurações de SMTP do banco de dados.
 * @return array Configurações de SMTP.
 */
function getSmtpSettings() {
    global $pdo;
    
    $default_settings = [
        'smtp_host' => 'smtp.exemplo.com',
        'smtp_port' => '587',
        'smtp_username' => '', 
        'smtp_password' => '', // Nunca exibe a senha real do DB
        'smtp_secure' => 'tls',
        'email_from' => 'nao-responda@seuapp.com',
        // Customizações de template
        'email_subject_template' => 'Novo Lançamento Financeiro Disponível: R$ {valor}',
        'email_from_name' => 'DMC - Sistema Financeiro',
        'email_salutation' => 'Prezado(a) {toName},',
        'email_intro' => 'Informamos que um novo lançamento financeiro foi disponibilizado para sua empresa. Por favor, acesse o portal para visualizar os detalhes e a situação de pagamento.',
        'email_closing' => "Atenciosamente,\nEquipe Financeira",
        // Recibo de Pagamento - templates de email (personalização)
        'recibo_email_subject' => 'Recibo de Pagamento - Cobrança #{id}',
        'recibo_email_title' => 'Recibo de Pagamento',
        'recibo_email_body' => '<p>Prezados,</p><p>Em anexo segue o recibo de pagamento referente à cobrança #{id}.</p><p>Atenciosamente,</p>'
        ,
        // Template customizável do corpo do email de lançamento (HTML)
        'lancamento_email_body' => "<p>{logo}</p><p>{salutation}</p><p>{email_intro}</p>{lancamento_table}<p>{email_closing}</p>"
        ,
        // Título opcional para email de lançamento
        'lancamento_email_title' => ''
    ];

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $settings_db = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return array_merge($default_settings, $settings_db);

    } catch (PDOException $e) {
        // Se a tabela system_settings não existir ou houver outro erro, retorna os padrões.
        error_log("Erro ao buscar configurações SMTP: " . $e->getMessage());
        return $default_settings;
    }
}

/**
 * Retorna política de upload de documentos (mime whitelist e tamanho máximo em bytes).
 * Pode ser adaptada para ler do DB (system_settings) se necessário.
 */
function getDocumentUploadPolicy() {
    // Valores padrão
    $policy = [
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ],
        'max_size_bytes' => 10 * 1024 * 1024 // 10 MB
    ];

    // Tentar carregar ajustes do DB se existir tabela system_settings
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('documents_max_size_bytes','documents_allowed_mimes')");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($rows['documents_max_size_bytes'])) {
            $val = intval($rows['documents_max_size_bytes']);
            if ($val > 1024) $policy['max_size_bytes'] = $val;
        }
        if (!empty($rows['documents_allowed_mimes'])) {
            $m = array_map('trim', explode(',', $rows['documents_allowed_mimes']));
            if (!empty($m)) $policy['allowed_mimes'] = $m;
        }
    } catch (Exception $e) {
        // ignore and use defaults
    }

    return $policy;
}

/**
 * Carrega templates de documentos (termo, recibo) a partir de `system_settings`.
 * Retorna um array com chaves para 'termo' e 'recibo', cada uma contendo 'header','body','footer'.
 */
function getDocumentTemplates() {
    global $pdo;

    $defaults = [
        'termo_header' => '<div style="text-align:left">{logo}<h2>Termo de Quitação</h2></div>',
        'termo_body' => '<p>Lista de pagamentos confirmados até {date}:</p>{payments_table}<p>Declaro ter recebido a quantia acima referida, estando quitadas as cobranças listadas.</p>',
        'termo_footer' => '<p>Documento gerado em {date}</p>',

        'recibo_header' => '<div style="text-align:left">{logo}<h2>Recibo de Pagamento</h2></div>',
        'recibo_body' => '<p><strong>Empresa:</strong> {empresa} ({cnpj})</p><p><strong>Cliente/Responsável:</strong> {cliente} &lt;{cliente_email}&gt;</p><p><strong>Descrição:</strong> {descricao}</p><p><strong>Valor:</strong> R$ {valor}</p><p><strong>Data de Pagamento:</strong> {data_pagamento}</p>',
        'recibo_footer' => '<p>Documento gerado em {date}</p>'
    ];

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        // merge defaults with rows from DB (rows override defaults)
        $merged = array_merge($defaults, $rows);
        return $merged;
    } catch (Exception $e) {
        return $defaults;
    }
}

/**
 * Sanitiza HTML recebidos de editores WYSIWYG antes de persistir.
 * Usa HTMLPurifier se disponível via Composer (ezyang/htmlpurifier).
 * Retorna HTML purificado ou o HTML original se a lib não estiver instalada.
 */
function sanitize_html($html) {
    if ($html === null) return null;
    // Se a classe não existir, retornamos o HTML como fallback (não ideal)
    if (!class_exists('HTMLPurifier')) {
        return $html;
    }

    static $purifier = null;
    if ($purifier === null) {
        $config = HTMLPurifier_Config::createDefault();
        // Configurações seguras mínimas
        $config->set('HTML.SafeIframe', true);
        $config->set('URI.SafeIframeRegexp', '%^(https?:)?//(www.youtube.com/embed/|player.vimeo.com/video/)%');
        // Limitar propriedades CSS permitidas para reduzir risco
        $config->set('CSS.AllowedProperties', array('font','font-size','font-weight','font-style','text-decoration','padding','margin','color','background-color','text-align'));
        $purifier = new HTMLPurifier($config);
    }

    return $purifier->purify($html);
}

/**
 * Verifica se um usuário está associado a uma pasta (tabela pivot) ou é o owner antigo.
 * Retorna true se associado (ou se o usuário for admin).
 */
function isUserAssociatedToPasta($user_id, $pasta_id) {
    if (isAdmin()) return true;
    global $pdo;
    try {
        // verifica existência da tabela pivot
        $stmt = $pdo->prepare("SELECT 1 FROM documentos_pastas_usuarios LIMIT 1");
        $stmt->execute();
        // tabela existe, checa associação
        $stmt2 = $pdo->prepare('SELECT COUNT(1) FROM documentos_pastas_usuarios WHERE pasta_id = ? AND user_id = ?');
        $stmt2->execute([$pasta_id, $user_id]);
        return $stmt2->fetchColumn() > 0;
    } catch (Exception $e) {
        // fallback para coluna owner_user_id (compatibilidade com versões anteriores)
        try {
            $stmt = $pdo->prepare('SELECT owner_user_id FROM documentos_pastas WHERE id = ?');
            $stmt->execute([$pasta_id]);
            $owner = $stmt->fetchColumn();
            return ($owner !== false && intval($owner) === intval($user_id));
        } catch (Exception $e2) {
            return false;
        }
    }
}

/**
 * Verifica se um cliente (empresa) possui qualquer usuário associado a uma pasta.
 * Útil para contas do tipo 'cliente' que representam empresas com múltiplos usuários.
 */
function isClientAssociatedToPasta($cliente_id, $pasta_id) {
    if (isAdmin()) return true;
    global $pdo;
    try {
        // verifica pivot via usuários que pertencem ao cliente
        $stmt = $pdo->prepare('SELECT COUNT(1) FROM documentos_pastas_usuarios dpu JOIN usuarios u ON dpu.user_id = u.id WHERE dpu.pasta_id = ? AND u.id_cliente_associado = ?');
        $stmt->execute([$pasta_id, $cliente_id]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        // fallback: verifica owner_user_id
        try {
            $stmt = $pdo->prepare('SELECT owner_user_id FROM documentos_pastas WHERE id = ?');
            $stmt->execute([$pasta_id]);
            $owner = $stmt->fetchColumn();
            if ($owner === false || $owner === null) return false;
            $stmt2 = $pdo->prepare('SELECT id_cliente_associado FROM usuarios WHERE id = ?');
            $stmt2->execute([$owner]);
            $owner_cliente = $stmt2->fetchColumn();
            return ($owner_cliente !== false && intval($owner_cliente) === intval($cliente_id));
        } catch (Exception $e2) {
            return false;
        }
    }
}

/**
 * Retorna array com usuários associados a uma pasta (id e nome). Se pivot ausente, tenta owner_user_id.
 */
function getUsuariosAssociadosPasta($pasta_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT u.id, u.nome FROM documentos_pastas_usuarios dpu JOIN usuarios u ON dpu.user_id = u.id WHERE dpu.pasta_id = ?');
        $stmt->execute([$pasta_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // fallback
        try {
            $stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE id = (SELECT owner_user_id FROM documentos_pastas WHERE id = ?)');
            $stmt->execute([$pasta_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? [$row] : [];
        } catch (Exception $e2) {
            return [];
        }
    }
}

/**
 * Envia um email de notificação sobre um lançamento.
 * @param string $toEmail Email do destinatário (cliente).
 * @param string $toName Nome do destinatário.
 * @param array $lancamento Dados do lançamento (descricao, valor, data_vencimento, tipo).
 * @return bool True se enviado com sucesso, False caso contrário.
 */
function sendNotificationEmail($toEmail, $toName, $lancamento) {
    // IMPORTANTE: Adicione o require da sua biblioteca PHPMailer aqui!
    // Ex: require 'vendor/autoload.php'; 
    
    $settings = getSmtpSettings();

    if (empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
        error_log("Erro de E-mail: Configurações SMTP ausentes ou incompletas.");
        return false;
    }

    $valor = number_format($lancamento['valor'], 2, ',', '.');
    $data_vencimento = date('d/m/Y', strtotime($lancamento['data_vencimento']));
    $tipo = $lancamento['tipo'] ?? '';
    $forma_pagamento = $lancamento['forma_pagamento'] ?? '';
    $contexto_pagamento = $lancamento['contexto_pagamento'] ?? '';

    // Monta assunto a partir do template configurado (substitui placeholders)
    $subject_template = $settings['email_subject_template'] ?? "Novo Lançamento Financeiro Disponível: R$ {valor}";
    // valores de placeholder com escaping onde apropriado
    $placeholders = [
        '{id}' => htmlspecialchars($lancamento['id'] ?? ''),
        '{toName}' => htmlspecialchars($toName),
        '{descricao}' => htmlspecialchars($lancamento['descricao'] ?? ''),
        '{valor}' => $valor,
        '{data_vencimento}' => $data_vencimento,
        '{tipo}' => htmlspecialchars($tipo)
    ];
    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject_template);

    // Determina origem do logo: prefere embutir arquivo local (CID), senão usa URL pública
    $logo_path_fs = __DIR__ . '/../assets/img/logo.png';
    $logo_src = file_exists($logo_path_fs) ? 'cid:logo_cid' : base_url('assets/img/logo.png');
    // HTML do logo que pode ser usado como placeholder {logo}
    $logo_html = '<img src="' . $logo_src . '" alt="Logo" style="max-height:80px;margin-bottom:10px;">';
    // Saudação / intro / closing configuráveis
    $salutation_template = $settings['email_salutation'] ?? 'Prezado(a) {toName},';
    $email_intro = $settings['email_intro'] ?? 'Informamos que um novo lançamento financeiro foi disponibilizado para sua empresa. Por favor, acesse o portal para visualizar os detalhes e a situação de pagamento.';
    $email_closing = $settings['email_closing'] ?? "Atenciosamente,\nEquipe Financeira";

    // Adiciona {logo} aos placeholders para permitir seu uso nos templates
    $placeholders['{logo}'] = $logo_html;
    // Adiciona {logo_url} que pode ser usada em atributos src (retorna cid:logo_cid ou URL pública)
    $placeholders['{logo_url}'] = $logo_src;

    // Preparar a tabela resumida do lançamento (para uso em templates customizados)
    $lancamento_table = "<table style='border: 1px solid #ccc; border-collapse: collapse; width: 100%; font-family: Arial, sans-serif;'>"
        . "<tr><td style='padding: 8px; border: 1px solid #ccc; background-color: #f0f0f0; width: 30%;'>Descrição</td><td style='padding: 8px; border: 1px solid #ccc;'>{$lancamento['descricao']}</td></tr>"
        . "<tr><td style='padding: 8px; border: 1px solid #ccc; background-color: #f0f0f0;'>Valor</td><td style='padding: 8px; border: 1px solid #ccc;'><b>R$ {$valor}</b></td></tr>"
        . "<tr><td style='padding: 8px; border: 1px solid #ccc; background-color: #f0f0f0;'>Data de vencimento</td><td style='padding: 8px; border: 1px solid #ccc;'>{$data_vencimento}</td></tr>"
        . "<tr><td style='padding: 8px; border: 1px solid #ccc; background-color: #f0f0f0;'>Tipo da cobrança</td><td style='padding: 8px; border: 1px solid #ccc;'>{$tipo}</td></tr>"
        . "<tr><td style='padding: 8px; border: 1px solid #ccc; background-color: #f0f0f0;'>Forma de Pagamento</td><td style='padding: 8px; border: 1px solid #ccc;'>{$forma_pagamento}</td></tr>"
        . "<tr><td style='padding: 8px; border: 1px solid #ccc; background-color: #f0f0f0;'>Contexto do Pagamento</td><td style='padding: 8px; border: 1px solid #ccc;'>{$contexto_pagamento}</td></tr>"
        . "</table>";

    $placeholders['{lancamento_table}'] = $lancamento_table;

    // Também expõe versões sem HTML (caso o template queira apenas texto simples)
    $placeholders['{descricao}'] = htmlspecialchars($lancamento['descricao']);
    $placeholders['{valor}'] = $valor;
    $placeholders['{data_vencimento}'] = $data_vencimento;
    $placeholders['{tipo}'] = htmlspecialchars($tipo);
    $placeholders['{forma}'] = htmlspecialchars($forma_pagamento);
    $placeholders['{contexto}'] = htmlspecialchars($contexto_pagamento);

    $salutation = str_replace(array_keys($placeholders), array_values($placeholders), $salutation_template);

    // Preenche intro/closing com placeholders já escapados; permitimos HTML do {logo} embutido
    $email_intro_filled = str_replace(array_keys($placeholders), array_values($placeholders), $email_intro);
    $email_closing_filled = str_replace(array_keys($placeholders), array_values($placeholders), $email_closing);

    // Se o usuário customizou um template HTML para o corpo do email de lançamento, usa-o
    $body = "";
    $custom_body_tpl = $settings['lancamento_email_body'] ?? null;
    if (!empty($custom_body_tpl)) {
        // Garante que placeholders básicos estejam presentes no array
        $placeholders['{salutation}'] = nl2br($salutation);
        $placeholders['{email_intro}'] = nl2br($email_intro_filled);
        $placeholders['{email_closing}'] = nl2br($email_closing_filled);
        // Substitui
        $body = str_replace(array_keys($placeholders), array_values($placeholders), $custom_body_tpl);
    } else {
        // comportamento antigo (padrão)
        $body = "
        <p>" . nl2br($salutation) . "</p>
        <p>" . nl2br($email_intro_filled) . "</p>
        " . $lancamento_table . "

        <br>

        <table style='width: 100%; font-family: Arial, sans-serif;'>
                <tr>
                <td style='width: 10%;'>{$logo_html}</td>
                <td>
                    <p>" . nl2br($email_closing_filled) . "</p>
                </td>
            </tr>
        </table>

    ";
    }
    
    // --- Lógica de Envio usando PHPMailer (recomendada) ---
    $mail_success = false;

    // Verifica se PHPMailer está disponível via autoload
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log('PHPMailer não encontrado. Execute "composer install" no diretório do projeto para habilitar envio real de emails.');
        return false;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        // Garantir charset UTF-8 e codificação adequada para prevenir caracteres "quebrados"
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isSMTP();
        $mail->Host = $settings['smtp_host'];
        $mail->Port = intval($settings['smtp_port']);
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp_username'];
        $mail->Password = $settings['smtp_password'];

        // Mapear valores amigáveis para o que o PHPMailer espera
        $secure = strtolower(trim($settings['smtp_secure'] ?? ''));
        if ($secure === 'starttls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }

        // Ajustes opcionais: tempo limite
        $mail->Timeout = 30;

        $mail->setFrom($settings['email_from'], $settings['email_from_name']);
        $mail->addAddress($toEmail, $toName);

        // Tenta embutir o logo localmente (CID) se existir
        $logo_path_fs = __DIR__ . '/../assets/img/logo.png';
        if (file_exists($logo_path_fs)) {
            try {
                $mail->addEmbeddedImage($logo_path_fs, 'logo_cid', 'logo.png');
            } catch (Exception $e) {
                error_log('Falha ao embutir logo (documentos): ' . $e->getMessage());
            }
        }

        // Tenta embutir o logo localmente (CID). Se o arquivo existir, adiciona como embedded image.
        if (file_exists($logo_path_fs)) {
            try {
                $mail->addEmbeddedImage($logo_path_fs, 'logo_cid', 'logo.png');
            } catch (Exception $e) {
                error_log('Falha ao embutir logo no e-mail: ' . $e->getMessage());
            }
        }
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->isHTML(true);

        $mail_success = $mail->send();
    } catch (Exception $e) {
        error_log("Erro no PHPMailer: " . $e->getMessage());
        $mail_success = false;
    }

    return $mail_success;
}

/**
 * Envia um e-mail genérico de notificação (documentos)
 */
function sendDocumentNotification($toEmail, $toName, $subject, $bodyHtml) {
    $settings = getSmtpSettings();

    if (empty($settings['smtp_username']) || empty($settings['smtp_password'])) {
        error_log("Erro de E-mail (Documentos): Configurações SMTP ausentes ou incompletas.");
        return false;
    }

    // Use PHPMailer para envio real de e-mails de documentos
    $mail_success = false;

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log('PHPMailer não encontrado. Execute "composer install" no diretório do projeto para habilitar envio real de emails.');
        return false;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        // Garantir charset UTF-8 e codificação adequada para prevenir caracteres "quebrados"
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

        $mail->setFrom($settings['email_from'], 'Sistema Financeiro');
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body = $bodyHtml;
        $mail->isHTML(true);

        $mail_success = $mail->send();
    } catch (Exception $e) {
        error_log("Erro no PHPMailer (Documentos): " . $e->getMessage());
        $mail_success = false;
    }

    return $mail_success;
}

// A função enviarEmailNotificacao antiga foi removida e substituída por sendNotificationEmail.

/**
 * Renderiza um título padrão para páginas administrativas.
 * Uso: render_page_title('Título da Página', 'Uma descrição curta', 'bi-icon-class');
 */
function render_page_title($title, $subtitle = '', $icon = 'bi-info-circle') {
    echo '<div class="mb-4">';
    echo '<h3><i class="bi ' . htmlspecialchars($icon) . ' me-2"></i> ' . htmlspecialchars($title) . '</h3>';
    if (!empty($subtitle)) {
        echo '<p class="text-muted">' . htmlspecialchars($subtitle) . '</p>';
    }
    echo '</div>';
}
?>