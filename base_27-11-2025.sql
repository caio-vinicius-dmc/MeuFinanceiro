-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 27/11/2025 às 20:15
-- Versão do servidor: 11.8.3-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u938628653_financasJP`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias_lancamento`
--

CREATE TABLE `categorias_lancamento` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categorias_lancamento`
--

INSERT INTO `categorias_lancamento` (`id`, `nome`, `descricao`, `ativo`, `created_at`) VALUES
(1, 'Conta de luz', '', 1, '2025-11-04 14:13:35'),
(2, 'Conta de agua', '', 1, '2025-11-04 14:13:37'),
(3, 'Aluguel', '', 1, '2025-11-04 14:13:42'),
(4, 'Contabilidade', '', 1, '2025-11-04 14:14:02'),
(5, 'Construção', '', 1, '2025-11-04 14:15:20'),
(6, 'Manutenção', '', 1, '2025-11-04 14:15:26'),
(9, 'Honorário', '', 1, '2025-11-04 14:27:57'),
(11, 'Reembolso', '', 1, '2025-11-04 14:28:05'),
(12, 'Venda', '', 1, '2025-11-04 14:28:08'),
(13, 'Fornecedor', '', 1, '2025-11-04 14:28:12'),
(14, 'Comissão', '', 1, '2025-11-04 14:29:32');

-- --------------------------------------------------------

--
-- Estrutura para tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nome_responsavel` varchar(100) NOT NULL,
  `email_contato` varchar(100) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `clientes`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `cobrancas`
--

CREATE TABLE `cobrancas` (
  `id` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `id_tipo_cobranca` int(11) DEFAULT NULL,
  `data_competencia` date NOT NULL,
  `data_vencimento` date NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `id_forma_pagamento` int(11) NOT NULL,
  `contexto_pagamento` text DEFAULT NULL COMMENT 'Chave PIX, link de pagamento, código de barras, etc.',
  `descricao` text DEFAULT NULL,
  `status_pagamento` enum('Pendente','Pago','Vencido') NOT NULL DEFAULT 'Pendente',
  `data_criacao` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_pagamento` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Estrutura para tabela `contador_clientes_assoc`
--

CREATE TABLE `contador_clientes_assoc` (
  `id_usuario_contador` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `documentos_arquivos`
--

CREATE TABLE `documentos_arquivos` (
  `id` int(11) NOT NULL,
  `pasta_id` int(11) NOT NULL,
  `nome_original` varchar(512) NOT NULL,
  `caminho` varchar(1024) NOT NULL,
  `tamanho` bigint(20) DEFAULT 0,
  `tipo_mime` varchar(255) DEFAULT NULL,
  `enviado_por_user_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `criado_em` datetime DEFAULT current_timestamp(),
  `aprovado_por` int(11) DEFAULT NULL,
  `aprovado_em` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `documentos_arquivos`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `documentos_download_tokens`
--

CREATE TABLE `documentos_download_tokens` (
  `token` varchar(128) NOT NULL,
  `arquivo_id` int(11) NOT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `documentos_pastas`
--

CREATE TABLE `documentos_pastas` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `owner_user_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `documentos_pastas`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `documentos_pastas_usuarios`
--

CREATE TABLE `documentos_pastas_usuarios` (
  `pasta_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `papel` varchar(32) DEFAULT 'usuario',
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `empresas`
--

CREATE TABLE `empresas` (
  `id` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `cnpj` varchar(18) NOT NULL,
  `razao_social` varchar(255) NOT NULL,
  `nome_fantasia` varchar(255) DEFAULT NULL,
  `data_abertura` date DEFAULT NULL,
  `data_contratacao` date DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `telefone_comercial` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `empresas`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `formas_pagamento`
--

CREATE TABLE `formas_pagamento` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `icone_bootstrap` varchar(50) DEFAULT NULL COMMENT 'Classe do ícone do Bootstrap, ex: bi-qr-code',
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo, 0 = Inativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `formas_pagamento`
--

INSERT INTO `formas_pagamento` (`id`, `nome`, `icone_bootstrap`, `ativo`) VALUES
(1, 'PIX', 'bi-qr-code', 1),
(2, 'Boleto Bancário', 'bi-upc-scan', 1),
(3, 'Cartão de Crédito', 'bi-credit-card', 1),
(4, 'Transferência Bancária', 'bi-bank', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `lancamentos`
--

CREATE TABLE `lancamentos` (
  `id` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `tipo` enum('receita','despesa') NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_competencia` date DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','contestado','confirmado_cliente') NOT NULL DEFAULT 'pendente',
  `anexo_path` varchar(255) DEFAULT NULL,
  `metodo_pagamento` varchar(255) DEFAULT NULL,
  `id_forma_pagamento` int(11) DEFAULT NULL,
  `id_categoria` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `acao` varchar(255) NOT NULL,
  `tabela_afetada` varchar(50) DEFAULT NULL,
  `id_registro_afetado` int(11) DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estrutura para tabela `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Acessar dashboard', 'acessar_dashboard', NULL),
(2, 'Acessar lançamentos', 'acessar_lancamentos', NULL),
(3, 'Criar lançamento', 'criar_lancamento', NULL),
(4, 'Editar lançamento', 'editar_lancamento', NULL),
(5, 'Excluir lançamento', 'excluir_lancamento', NULL),
(6, 'Acessar cobranças', 'acessar_cobrancas', NULL),
(7, 'Acessar configurações', 'acessar_configuracoes', NULL),
(8, 'Gerenciar usuários', 'gerenciar_usuarios', NULL),
(9, 'Gerenciar papéis', 'gerenciar_papeis', NULL),
(10, 'Gerar relatórios', 'gerar_relatorios', NULL),
(11, 'Visualizar documentos', 'visualizar_documentos', NULL),
(12, 'Acessar associações contador', 'acessar_associacoes_contador', NULL),
(13, 'Acessar logs', 'acessar_logs', NULL),
(14, 'Gerenciar empresas', 'gerenciar_empresas', NULL),
(15, 'Gerenciar documentos', 'gerenciar_documentos', NULL),
(16, 'Gerenciar cobranças', 'gerenciar_cobrancas', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `roles`
--

INSERT INTO `roles` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Super Admin', 'super_admin', 'Super administrator (protected)'),
(2, 'Admin', 'admin', 'Admin users'),
(3, 'Contador', 'contador', 'Accounting / bookkeeper'),
(5, 'Cliente - Lançamentos', 'cliente_lancamentos', 'Cliente com acesso a lançamentos'),
(6, 'Cliente - Cobranças', 'cliente_cobranca', 'Cliente com acesso a cobranças');

-- --------------------------------------------------------

--
-- Estrutura para tabela `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(3, 1),
(3, 2),
(3, 3),
(3, 4),
(3, 6),
(3, 10),
(5, 1),
(5, 2),
(5, 11);

-- --------------------------------------------------------

--
-- Estrutura para tabela `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('email_from', 'contato@jpconsultoriacontabil.com.br'),
('email_subject_template', 'Nova cobrança Disponível #{id}'),
('lancamento_email_body', '<p>Prezado(a) {toName},&nbsp;<br>Informamos que uma nova cobrança foi disponibilizado para sua empresa. Por favor, acesse o portal para visualizar os detalhes e a situação de pagamento.&nbsp;<br><br>&nbsp;</p><figure class=\"table\"><table><tbody><tr><td>Descrição</td><td>{descricao}</td></tr><tr><td>Valor</td><td><strong>R$ {valor}</strong></td></tr><tr><td>Data de vencimento</td><td>{data_vencimento}</td></tr><tr><td>Tipo da cobrança</td><td>{tipo}</td></tr><tr><td>Forma de Pagamento</td><td>{forma}</td></tr><tr><td>Contexto do Pagamento</td><td>{contexto}</td></tr></tbody></table></figure><p><br>&nbsp;</p><figure class=\"table\"><table><tbody><tr><td>{logo}</td><td>Atenciosamente,<br>Equipe financeira.</td></tr></tbody></table></figure>'),
('lancamento_email_title', 'JP contabilidade e assessoria'),
('rbac_version', '1'),
('recibo_body', '<hr><br>\r\n<table border=\"1\" cellpadding=\"6\" cellspacing=\"0\" style=\"border-collapse:collapse;width:100%;\">\r\n    <tr>\r\n        <td style=\"width:30%;text-align:right;\"><strong>Cliente/Responsável:</strong></td>\r\n        <td>{cliente}</td>\r\n    </tr>\r\n    <tr>\r\n        <td style=\"text-align:right;\"><strong>Empresa:</strong></td>\r\n        <td>{empresa}</td>\r\n    </tr>\r\n    <tr>\r\n        <td style=\"text-align:right;\"><strong>CNPJ:</strong></td>\r\n        <td>{cnpj}</td>\r\n    </tr>\r\n    <tr>\r\n        <td style=\"text-align:right;\"><strong>E-mail:</strong></td>\r\n        <td>{cliente_email}</td>\r\n    </tr>\r\n    <tr>\r\n        <td style=\"text-align:right;\"><strong>Descrição:</strong></td>\r\n        <td>{descricao}</td>\r\n    </tr>\r\n    <tr>\r\n        <td style=\"text-align:right;\"><strong>Valor:</strong></td>\r\n        <td>R${valor}</td>\r\n    </tr>\r\n    <tr>\r\n        <td style=\"text-align:right;\"><strong>Data de vencimento:</strong></td>\r\n        <td>{data_vencimento}</td>\r\n    </tr>\r\n    <tr>\r\n        <td style=\"text-align:right;\"><strong>Mês de competência:</strong></td>\r\n        <td>{data_competencia}</td>\r\n    </tr>\r\n    <tr>\r\n        <td style=\"text-align:right;\"><strong>Tipo de serviço:</strong></td>\r\n        <td>{tipo}</td>\r\n    </tr>\r\n    <tr>\r\n        <td style=\"text-align:right;\"><strong>Forma de Pagamento:</strong></td>\r\n        <td>{forma}</td>\r\n    </tr>\r\n    <tr>\r\n        <td style=\"text-align:right;\"><strong>Contexto de Pagamento:</strong></td>\r\n        <td>{contexto}</td>\r\n    </tr>\r\n</table>\r\n<br>\r\n<hr>'),
('recibo_email_body', '<p>Prezado(a) {cliente},&nbsp;<br>Em anexo segue o recibo de pagamento #{id}.&nbsp;<br>&nbsp;</p><figure class=\"table\"><table><tbody><tr><td>{logo}</td><td>Atenciosamente,<br>Equipe financeira.</td></tr></tbody></table></figure>'),
('recibo_email_subject', 'Novo recibo de pagamento disponível #{id}'),
('recibo_email_title', 'JP contabilidade e assessoria'),
('recibo_footer', '<br><br><br><br><br><br><br><br><br><br><br><br><br><br>\r\n<p>Documento gerado via sistema em <STRONG>{date}</STRONG></p>\r\n<p>\r\n <STRONG>Por:</STRONG> JAILSON PEREIRA CONSULTORIA CONTABIL</p>\r\n<p>\r\n <STRONG>CNPJ:</STRONG> 49.307.112/0001-89</p>'),
('recibo_header', '<div style=\"text-align:left\">{logo}<h2>Recibo de Pagamento</h2></div>\r\n<p>\r\n <strong>Data de Pagamento:</strong> {data_pagamento}\r\n</p>'),
('smtp_host', 'smtp.zoho.com'),
('smtp_password', 'k1gPb4h2pacG'),
('smtp_port', '587'),
('smtp_secure', 'tls'),
('smtp_username', 'contato@jpconsultoriacontabil.com.br'),
('termo_body', '<p>Lista de pagamentos confirmados até {date}:</p>{payments_table}<p>A empresa citada, está quite com os débitos do pagamentos dos serviços contábeis, relativo ao periodo de 01/01/2025 a 31/12/2025. Esta declaração substitui, para a comprovação do cumprimento das obrigações do consumidor, as quitações dos faturamentos mensais dos débitos do ano a que se refere e dos anos anteriores.\r\n\r\nEsta declaração é emitida para cumprimento da Lei 12.007/2009.</p>'),
('termo_footer', '<br><br><br><br><br><br><br><br><br><br>\r\n<p>Documento gerado em {date}</p>\r\n\r\n\r\n<table>\r\n  <tr>\r\n    <td style=\"text-align: right;width: 30%; height: 50px;\">\r\n      Assinatura do cliente:\r\n    </td>\r\n    <td>\r\n      \r\n    </td>\r\n  </tr>  \r\n<tr>\r\n<td colspan=\"2\"></td>\r\n</tr>\r\n  <tr>\r\n    <td style=\"text-align: right; width: 30%; height: 50px;\">\r\n      Assinatura do responsável: \r\n    </td>\r\n    <td>\r\n    </td>\r\n  </tr>\r\n</table>'),
('termo_header', '<div style=\"text-align:left\">{logo}<h2>Declaração de Quitação</h2></div>');

-- --------------------------------------------------------

--
-- Estrutura para tabela `tb_confg_emailCliente`
--

CREATE TABLE `tb_confg_emailCliente` (
  `id` int(11) NOT NULL,
  `id_client` int(11) NOT NULL,
  `permissao` varchar(100) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `tipos_cobranca`
--

CREATE TABLE `tipos_cobranca` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `tipos_cobranca`
--

INSERT INTO `tipos_cobranca` (`id`, `nome`, `ativo`) VALUES
(1, 'Serviços Contábeis Mensais', 1),
(2, 'Serviços de Despachante', 1),
(3, 'Impostos', 1),
(4, 'Consultoria', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(2, 1),
(2, 2);

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('admin','contador','cliente') NOT NULL,
  `id_cliente_associado` int(11) DEFAULT NULL,
  `acesso_lancamentos` tinyint(1) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `is_super_admin` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `telefone`, `senha`, `tipo`, `id_cliente_associado`, `acesso_lancamentos`, `ativo`, `is_super_admin`) VALUES
(2, 'Caio Vinícius', 'caio@dynamicmotioncentury.com.br', '81983656068', '$2y$10$33uB5eMzzl1ScCDQ4IBh/eApsREwPqfWeANhjZnn4ik6vf23Kgz.i', 'admin', NULL, 0, 1, 1);
--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categorias_lancamento`
--
ALTER TABLE `categorias_lancamento`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `cobrancas`
--
ALTER TABLE `cobrancas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_empresa` (`id_empresa`),
  ADD KEY `id_forma_pagamento` (`id_forma_pagamento`),
  ADD KEY `fk_cobrancas_tipo` (`id_tipo_cobranca`);

--
-- Índices de tabela `contador_clientes_assoc`
--
ALTER TABLE `contador_clientes_assoc`
  ADD PRIMARY KEY (`id_usuario_contador`,`id_cliente`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Índices de tabela `documentos_arquivos`
--
ALTER TABLE `documentos_arquivos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pasta_id` (`pasta_id`),
  ADD KEY `enviado_por_user_id` (`enviado_por_user_id`),
  ADD KEY `status` (`status`);

--
-- Índices de tabela `documentos_download_tokens`
--
ALTER TABLE `documentos_download_tokens`
  ADD PRIMARY KEY (`token`),
  ADD KEY `arquivo_id` (`arquivo_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Índices de tabela `documentos_pastas`
--
ALTER TABLE `documentos_pastas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `owner_user_id` (`owner_user_id`);

--
-- Índices de tabela `documentos_pastas_usuarios`
--
ALTER TABLE `documentos_pastas_usuarios`
  ADD PRIMARY KEY (`pasta_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices de tabela `empresas`
--
ALTER TABLE `empresas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Índices de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `lancamentos`
--
ALTER TABLE `lancamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_empresa` (`id_empresa`),
  ADD KEY `idx_lancamentos_id_forma_pagamento` (`id_forma_pagamento`),
  ADD KEY `idx_lancamentos_id_categoria` (`id_categoria`);

--
-- Índices de tabela `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Índices de tabela `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `idx_rp_role` (`role_id`),
  ADD KEY `idx_rp_perm` (`permission_id`);

--
-- Índices de tabela `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Índices de tabela `tb_confg_emailCliente`
--
ALTER TABLE `tb_confg_emailCliente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_client` (`id_client`),
  ADD KEY `idx_permissao` (`permissao`);

--
-- Índices de tabela `tipos_cobranca`
--
ALTER TABLE `tipos_cobranca`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `idx_ur_user` (`user_id`),
  ADD KEY `idx_ur_role` (`role_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categorias_lancamento`
--
ALTER TABLE `categorias_lancamento`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `cobrancas`
--
ALTER TABLE `cobrancas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de tabela `documentos_arquivos`
--
ALTER TABLE `documentos_arquivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `documentos_pastas`
--
ALTER TABLE `documentos_pastas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `lancamentos`
--
ALTER TABLE `lancamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT de tabela `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de tabela `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `tb_confg_emailCliente`
--
ALTER TABLE `tb_confg_emailCliente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tipos_cobranca`
--
ALTER TABLE `tipos_cobranca`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `cobrancas`
--
ALTER TABLE `cobrancas`
  ADD CONSTRAINT `fk_cobrancas_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cobrancas_forma_pagamento` FOREIGN KEY (`id_forma_pagamento`) REFERENCES `formas_pagamento` (`id`),
  ADD CONSTRAINT `fk_cobrancas_tipo` FOREIGN KEY (`id_tipo_cobranca`) REFERENCES `tipos_cobranca` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `contador_clientes_assoc`
--
ALTER TABLE `contador_clientes_assoc`
  ADD CONSTRAINT `contador_clientes_assoc_ibfk_1` FOREIGN KEY (`id_usuario_contador`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contador_clientes_assoc_ibfk_2` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `empresas`
--
ALTER TABLE `empresas`
  ADD CONSTRAINT `empresas_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `lancamentos`
--
ALTER TABLE `lancamentos`
  ADD CONSTRAINT `fk_lancamentos_forma_pagamento` FOREIGN KEY (`id_forma_pagamento`) REFERENCES `formas_pagamento` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `lancamentos_ibfk_1` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
