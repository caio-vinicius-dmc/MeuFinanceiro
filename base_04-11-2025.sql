-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 04/11/2025 às 21:33
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `gestao_financeira`
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
(7, 'Sistema', '', 1, '2025-11-04 14:15:29'),
(8, 'Organizar', '', 1, '2025-11-04 14:15:39'),
(9, 'Honorário', '', 1, '2025-11-04 14:27:57'),
(10, 'Serviço', '', 1, '2025-11-04 14:28:01'),
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

INSERT INTO `clientes` (`id`, `nome_responsavel`, `email_contato`, `telefone`) VALUES
(1, 'Reverton', 'noreplay@dynamicmotioncentury.com.br', '81983656068'),
(2, 'Caio', 'caio@dynamicmotioncentury.com.br', '81983656068');

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
-- Despejando dados para a tabela `cobrancas`
--

INSERT INTO `cobrancas` (`id`, `id_empresa`, `id_tipo_cobranca`, `data_competencia`, `data_vencimento`, `valor`, `id_forma_pagamento`, `contexto_pagamento`, `descricao`, `status_pagamento`, `data_criacao`, `data_pagamento`) VALUES
(1, 3, 4, '2025-10-01', '2025-11-01', 1815.00, 2, '26091337299149385389835100000005312610000260161', 'Cobrança do escritório de contabilidade', 'Pago', '2025-11-02 19:55:35', '2025-11-03'),
(2, 4, 1, '2025-09-01', '2025-10-01', 1815.00, 2, '81900000000', 'Pagamento da empresa de contabilidade', 'Pago', '2025-11-02 20:31:34', '2025-11-03'),
(3, 4, 1, '2025-11-01', '2025-12-01', 1815.00, 1, '819000000', ' Cobrança do setor de contabilidade', 'Pendente', '2025-11-02 21:07:13', NULL),
(4, 4, 1, '2025-11-01', '2025-12-01', 1850.00, 2, '90381902830981203812930', ' ', 'Pago', '2025-11-03 02:59:39', '2025-11-03');

-- --------------------------------------------------------

--
-- Estrutura para tabela `contador_clientes_assoc`
--

CREATE TABLE `contador_clientes_assoc` (
  `id_usuario_contador` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `contador_clientes_assoc`
--

INSERT INTO `contador_clientes_assoc` (`id_usuario_contador`, `id_cliente`) VALUES
(5, 1),
(5, 2);

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

INSERT INTO `documentos_pastas` (`id`, `nome`, `parent_id`, `owner_user_id`, `created_at`, `updated_at`) VALUES
(1, 'Documentos - Caiot', NULL, 1, '2025-11-04 17:17:45', '2025-11-04 17:30:38'),
(3, 'Organizar', 1, NULL, '2025-11-04 17:30:27', '2025-11-04 17:30:27');

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

--
-- Despejando dados para a tabela `documentos_pastas_usuarios`
--

INSERT INTO `documentos_pastas_usuarios` (`pasta_id`, `user_id`, `papel`, `criado_em`) VALUES
(1, 1, 'usuario', '2025-11-04 16:59:55'),
(1, 2, 'usuario', '2025-11-04 16:59:55'),
(3, 1, 'usuario', '2025-11-04 17:00:17'),
(3, 5, 'usuario', '2025-11-04 17:00:17');

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
  `endereco` text DEFAULT NULL,
  `telefone_comercial` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `empresas`
--

INSERT INTO `empresas` (`id`, `id_cliente`, `cnpj`, `razao_social`, `nome_fantasia`, `data_abertura`, `endereco`, `telefone_comercial`) VALUES
(3, 2, '56.252.542/0001-06', '56.252.542 LEANDRO DA MOTA PASCOALINO', '', '2024-08-03', NULL, NULL),
(4, 2, '23.480.469/0001-70', 'LAURIVAN DE SOUSA OLIVEIRA LTDA', 'VAM MED', '2015-10-15', NULL, NULL),
(5, 1, '16.752.022/0001-48', 'CATARINA DA SILVA ROCHA SANTIAGO FERREIRA', 'teste', '2012-08-23', NULL, NULL);

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
(2, 'Boleto Bancário', 'bi-barcode', 1),
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

--
-- Despejando dados para a tabela `lancamentos`
--

INSERT INTO `lancamentos` (`id`, `id_empresa`, `descricao`, `valor`, `tipo`, `data_vencimento`, `data_competencia`, `data_pagamento`, `status`, `anexo_path`, `metodo_pagamento`, `id_forma_pagamento`, `id_categoria`) VALUES
(3, 3, 'Mesa nova', 500.00, 'despesa', '2025-10-30', '0000-00-00', NULL, 'pendente', NULL, 'Boleto Bancário', 2, 5),
(4, 4, 'Banner novo da fachada', 1500.00, 'despesa', '2025-10-31', '0000-00-00', '2025-11-03', 'pago', NULL, 'PIX', 1, 8),
(5, 5, 'Balcão checkout', 850.00, 'despesa', '2025-10-30', '0000-00-00', '2025-11-03', 'pago', NULL, 'Boleto Bancário', 2, 5),
(6, 5, 'pneus de carro', 1500.00, 'despesa', '2025-10-31', '0000-00-00', '2025-11-03', 'pago', NULL, 'PIX', 1, 8),
(7, 3, 'Compra de agua', 3.00, 'despesa', '2025-11-03', '2025-11-03', '2025-11-03', 'pago', NULL, 'PIX', 1, 2),
(9, 3, 'Conta de luz ', 500.00, 'despesa', '2025-11-03', '0000-00-00', '2025-11-03', 'pago', NULL, 'PIX', 1, 1);

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

--
-- Despejando dados para a tabela `logs`
--

INSERT INTO `logs` (`id`, `id_usuario`, `acao`, `tabela_afetada`, `id_registro_afetado`, `detalhes`, `timestamp`) VALUES
(1, 1, 'Logout', NULL, NULL, 'Usuário: Desconhecido', '2025-10-30 10:43:54'),
(2, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 10:44:30'),
(3, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 10:44:35'),
(4, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 10:44:39'),
(5, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 10:46:25'),
(6, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 10:46:33'),
(7, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 10:46:50'),
(8, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 10:54:41'),
(9, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 11:00:09'),
(10, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 11:01:37'),
(11, 1, 'Cadastro Lançamento', 'lancamentos', 1, NULL, '2025-10-30 11:11:28'),
(12, 1, 'Tentativa de envio de email', 'usuarios', NULL, 'Email para: cliente2@email.com, Lançamento: banner', '2025-10-30 11:11:28'),
(13, 1, 'Cadastro Lançamento', 'lancamentos', 2, NULL, '2025-10-30 11:11:44'),
(14, 1, 'Tentativa de envio de email', 'usuarios', NULL, 'Email para: cliente1@email.com, Lançamento: teste', '2025-10-30 11:11:44'),
(15, 1, 'Edição Cliente', 'clientes', 1, NULL, '2025-10-30 12:57:14'),
(16, 1, 'Edição Cliente', 'clientes', 1, NULL, '2025-10-30 12:57:33'),
(17, 1, 'Edição Cliente', 'clientes', 1, NULL, '2025-10-30 12:58:09'),
(18, 1, 'Edição Cliente', 'clientes', 2, NULL, '2025-10-30 12:58:39'),
(19, 1, 'Cadastro Empresa', 'empresas', 3, NULL, '2025-10-30 12:59:36'),
(20, 1, 'Exclusão Empresa', 'empresas', 1, NULL, '2025-10-30 13:04:03'),
(21, 1, 'Exclusão Empresa', 'empresas', 2, NULL, '2025-10-30 13:04:06'),
(22, 1, 'Cadastro Empresa', 'empresas', 4, NULL, '2025-10-30 13:05:38'),
(23, 1, 'Cadastro Empresa', 'empresas', 5, NULL, '2025-10-30 13:05:59'),
(24, 1, 'Edição Cliente', 'clientes', 1, NULL, '2025-10-30 13:06:06'),
(25, 1, 'Edição Usuário', 'usuarios', 0, NULL, '2025-10-30 13:09:30'),
(26, 1, 'Edição Usuário', 'usuarios', 2, NULL, '2025-10-30 13:09:58'),
(27, 1, 'Cadastro Usuário', 'usuarios', 3, NULL, '2025-10-30 13:10:55'),
(28, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:11:07'),
(29, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:11:19'),
(30, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-10-30 13:11:47'),
(31, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:11:55'),
(32, 1, 'Cadastro Lançamento', 'lancamentos', 3, NULL, '2025-10-30 13:12:24'),
(33, 1, 'Tentativa de envio de email', 'usuarios', NULL, 'Email para: caio@dynamicmotioncentury.com.br, Lançamento: Mesa nova', '2025-10-30 13:12:24'),
(34, 1, 'Cadastro Lançamento', 'lancamentos', 4, NULL, '2025-10-30 13:12:46'),
(35, 1, 'Tentativa de envio de email', 'usuarios', NULL, 'Email para: caio@dynamicmotioncentury.com.br, Lançamento: Banner novo da fachada', '2025-10-30 13:12:46'),
(36, 1, 'Cadastro Lançamento', 'lancamentos', 5, NULL, '2025-10-30 13:13:07'),
(37, 1, 'Tentativa de envio de email', 'usuarios', NULL, 'Email para: noreplay@dynamicmotioncentury.com.br, Lançamento: Balcão checkout', '2025-10-30 13:13:07'),
(38, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:13:32'),
(39, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:13:38'),
(40, 2, 'Cliente Confirmou Pagamento', 'lancamentos', 3, NULL, '2025-10-30 13:13:47'),
(41, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-10-30 13:13:55'),
(42, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:14:03'),
(43, 1, 'Reverteu Lançamento', 'lancamentos', 3, NULL, '2025-10-30 13:21:25'),
(44, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:28:14'),
(45, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:28:20'),
(46, 2, 'Cliente Sinalizou Pagamento', 'lancamentos', 3, NULL, '2025-10-30 13:28:32'),
(47, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-10-30 13:28:37'),
(48, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:28:44'),
(49, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:32:26'),
(50, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:32:31'),
(51, 2, 'Cliente Confirmou Pagamento', 'lancamentos', 4, NULL, '2025-10-30 13:32:38'),
(52, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-10-30 13:32:40'),
(53, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:32:46'),
(54, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:44:01'),
(55, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:44:02'),
(56, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:45:37'),
(57, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:45:43'),
(58, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-10-30 13:45:48'),
(59, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:45:51'),
(60, 1, 'Baixa Lançamento', 'lancamentos', 4, NULL, '2025-10-30 13:47:35'),
(61, 1, 'Atualização de Perfil', 'usuarios', 1, NULL, '2025-10-30 13:48:55'),
(62, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:49:02'),
(63, NULL, 'Falha no login', 'usuarios', NULL, 'Email: noreplay@dynamicmotioncentury.com.br', '2025-10-30 13:49:19'),
(64, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 13:49:21'),
(65, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 13:49:22'),
(66, NULL, 'Falha no login', 'usuarios', NULL, 'Email: admin@seuapp.com', '2025-10-30 13:49:26'),
(67, NULL, 'Falha no login', 'usuarios', NULL, 'Email: dmc@dynamicmotioncentury.com.br', '2025-10-30 13:49:37'),
(68, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:49:43'),
(69, 1, 'Edição Usuário', 'usuarios', 3, 'Email: teste@gmail.com, Tipo: cliente', '2025-10-30 13:50:05'),
(70, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:50:07'),
(71, 3, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:50:12'),
(72, 3, 'Logout', NULL, NULL, 'Usuário: Reverton henrique', '2025-10-30 13:50:23'),
(73, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:50:33'),
(74, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:51:40'),
(75, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:54:35'),
(76, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:54:43'),
(77, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 13:58:44'),
(78, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 13:59:19'),
(79, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 14:15:46'),
(80, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 14:16:41'),
(81, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 14:16:47'),
(82, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 14:16:54'),
(83, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 14:18:40'),
(84, 1, 'Cadastro Lançamento', 'lancamentos', 6, 'Valor: R$ 1500, Descrição: pneus de carro', '2025-10-30 14:20:37'),
(85, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 14:20:40'),
(86, 3, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 14:20:51'),
(87, 3, 'Cliente Contestou Lançamento', 'lancamentos', 6, 'Motivo: Esse lançamento não é para mim', '2025-10-30 14:21:11'),
(88, 3, 'Logout', NULL, NULL, 'Usuário: Reverton henrique', '2025-10-30 14:21:14'),
(89, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 14:21:16'),
(90, 1, 'Baixa Lançamento', 'lancamentos', 3, NULL, '2025-10-30 14:26:29'),
(91, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 14:28:16'),
(92, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 14:28:22'),
(93, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 14:39:00'),
(94, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 14:39:02'),
(95, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 15:08:43'),
(96, 3, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 15:08:49'),
(97, 3, 'Logout', NULL, NULL, 'Usuário: Reverton henrique', '2025-10-30 15:09:19'),
(98, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 15:09:21'),
(99, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 15:39:03'),
(100, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 15:39:04'),
(101, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-10-30 15:44:44'),
(102, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 15:44:45'),
(103, NULL, 'Falha no login', 'usuarios', NULL, 'Email: dmc@dynamicmotioncentury.com.br', '2025-11-02 16:49:19'),
(104, NULL, 'Falha no login', 'usuarios', NULL, 'Email: caio@dynamicmotioncentury.com.br', '2025-11-02 16:49:29'),
(105, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 16:49:33'),
(106, 1, 'Gerou Cobrança', 'cobrancas', 1, 'Valor: R$ 1815 para empresa ID: 3', '2025-11-02 16:55:35'),
(107, 1, 'Editou Cobrança', 'cobrancas', 1, 'Valor: R$ 1815.00, Descrição: Cobrança do escritório de contabilidade', '2025-11-02 16:57:01'),
(108, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 16:57:07'),
(109, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 16:57:10'),
(110, 1, 'Edição Usuário', 'usuarios', 2, 'Email: caio@dynamicmotioncentury.com.br, Tipo: cliente', '2025-11-02 16:57:24'),
(111, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 16:57:26'),
(112, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 16:57:32'),
(113, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 17:00:35'),
(114, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:00:37'),
(115, 1, 'Baixa Cobrança', 'cobrancas', 1, 'Status: Pago em atraso, Data Pagamento: 2025-11-02', '2025-11-02 17:00:45'),
(116, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 17:00:50'),
(117, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:00:54'),
(118, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 17:00:59'),
(119, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:01:07'),
(120, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 17:08:43'),
(121, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:08:45'),
(122, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 17:11:02'),
(123, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:11:07'),
(124, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 17:13:46'),
(125, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:13:47'),
(126, 1, 'Baixa Cobrança', 'cobrancas', 1, 'Status: Pago em atraso, Data Pagamento: 2025-11-02', '2025-11-02 17:13:52'),
(127, 1, 'Baixa Cobrança', 'cobrancas', 1, 'Status: Pago em atraso, Data Pagamento: 2025-11-02', '2025-11-02 17:15:40'),
(128, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 17:15:45'),
(129, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:15:54'),
(130, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 17:17:58'),
(131, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:18:00'),
(132, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 17:18:04'),
(133, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:18:08'),
(134, 1, 'Baixa Cobrança', 'cobrancas', 1, 'Status: Pago em atraso, Data Pagamento: 2025-11-02', '2025-11-02 17:18:13'),
(135, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 17:24:13'),
(136, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:24:18'),
(137, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 17:28:09'),
(138, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:28:15'),
(139, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 17:28:42'),
(140, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:28:44'),
(141, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 17:30:02'),
(142, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:30:07'),
(143, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 17:30:28'),
(144, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:30:32'),
(145, 1, 'Gerou Cobrança', 'cobrancas', 2, 'Valor: R$ 1815 para empresa ID: 4', '2025-11-02 17:31:34'),
(146, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 17:31:53'),
(147, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:32:00'),
(148, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 17:33:06'),
(149, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:33:11'),
(150, 1, 'Editou Cobrança', 'cobrancas', 2, 'Valor: R$ 1815.00, Descrição: Pagamento da empresa de contabilidade', '2025-11-02 17:33:35'),
(151, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 17:33:40'),
(152, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:33:44'),
(153, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 17:52:45'),
(154, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 17:52:50'),
(155, 1, 'Gerou Cobrança', 'cobrancas', 3, 'Valor: R$ 1815 para empresa ID: 4', '2025-11-02 18:07:13'),
(156, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 18:07:29'),
(157, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:07:34'),
(158, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 18:07:51'),
(159, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:07:55'),
(160, 1, 'Editou Cobrança', 'cobrancas', 3, 'Valor: R$ 1815.00, Descrição:  Cobrança do setor de contabilidade', '2025-11-02 18:08:21'),
(161, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 18:08:23'),
(162, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:08:26'),
(163, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 18:08:38'),
(164, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:11:02'),
(165, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 18:19:25'),
(166, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:19:28'),
(167, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 18:21:19'),
(168, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:21:23'),
(169, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 18:25:24'),
(170, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:25:27'),
(171, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 18:28:19'),
(172, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:28:23'),
(173, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 18:31:08'),
(174, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:31:12'),
(175, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 18:31:29'),
(176, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:32:30'),
(177, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 18:32:34'),
(178, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:32:37'),
(179, 1, 'Edição Lançamento', 'lancamentos', 4, 'Campos alterados: Empresa: LAURIVAN DE SOUSA OLIVEIRA LTDA -> LAURIVAN DE SOUSA OLIVEIRA LTDA; Status: pago -> Em aberto', '2025-11-02 18:32:54'),
(180, 1, 'Edição Lançamento', 'lancamentos', 6, 'Campos alterados: Empresa: CATARINA DA SILVA ROCHA SANTIAGO FERREIRA -> CATARINA DA SILVA ROCHA SANTIAGO FERREIRA; Status: contestado -> Em aberto', '2025-11-02 18:32:58'),
(181, 1, 'Edição Lançamento', 'lancamentos', 3, 'Campos alterados: Empresa: 56.252.542 LEANDRO DA MOTA PASCOALINO -> 56.252.542 LEANDRO DA MOTA PASCOALINO; Status: pago -> Em aberto', '2025-11-02 18:33:02'),
(182, 1, 'Edição Lançamento', 'lancamentos', 5, 'Campos alterados: Empresa: CATARINA DA SILVA ROCHA SANTIAGO FERREIRA -> CATARINA DA SILVA ROCHA SANTIAGO FERREIRA; Status: pendente -> Em aberto', '2025-11-02 18:33:07'),
(183, 1, 'Edição Usuário', 'usuarios', 2, 'Email: caio@dynamicmotioncentury.com.br, Tipo: cliente', '2025-11-02 18:33:24'),
(184, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 18:33:26'),
(185, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:33:32'),
(186, 2, 'Edição Lançamento', 'lancamentos', 4, 'Campos alterados: Empresa: LAURIVAN DE SOUSA OLIVEIRA LTDA -> LAURIVAN DE SOUSA OLIVEIRA LTDA; Status:  -> Pago', '2025-11-02 18:33:56'),
(187, 2, 'Atualizou Status Lançamento', 'lancamentos', 4, 'Status: pago -> Pago', '2025-11-02 18:34:07'),
(188, 2, 'Atualizou Status Lançamento', 'lancamentos', 3, 'Status:  -> Pago', '2025-11-02 18:34:09'),
(189, 2, 'Atualizou Status Lançamento', 'lancamentos', 4, 'Status: pago -> Pago', '2025-11-02 18:37:13'),
(190, 2, 'Atualizou Status Lançamento', 'lancamentos', 4, 'Status: pago -> Pago', '2025-11-02 18:38:06'),
(191, 2, 'Atualizou Status Lançamento', 'lancamentos', 3, 'Status: pago -> Pago', '2025-11-02 18:38:11'),
(192, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 18:38:15'),
(193, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:38:36'),
(194, 2, 'Atualizou Status Lançamento', 'lancamentos', 4, 'Status: pago -> Pago', '2025-11-02 18:38:40'),
(195, 2, 'Atualizou Status Lançamento', 'lancamentos', 4, 'Status: pago -> Pago', '2025-11-02 18:40:54'),
(196, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 18:41:34'),
(197, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:41:48'),
(198, 1, 'Atualizou Status Lançamento', 'lancamentos', 4, 'Status: pago -> Pago', '2025-11-02 18:42:12'),
(199, 1, 'Atualizou Status Lançamento', 'lancamentos', 4, 'Status: pago -> Pago', '2025-11-02 18:42:35'),
(200, 1, 'Atualizou Status Lançamento', 'lancamentos', 4, 'Status: pago -> Pago', '2025-11-02 18:47:20'),
(201, 1, 'Atualizou Status Lançamento', 'lancamentos', 6, 'Status: pago -> Pago', '2025-11-02 18:47:23'),
(202, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 18:47:31'),
(203, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 18:47:34'),
(204, 2, 'Edição Lançamento', 'lancamentos', 4, 'Campos alterados: Empresa: LAURIVAN DE SOUSA OLIVEIRA LTDA -> LAURIVAN DE SOUSA OLIVEIRA LTDA; Status: pago -> Pago', '2025-11-02 18:52:39'),
(205, 2, 'Atualizou Status Lançamento', 'lancamentos', 4, 'Status: pago -> Pago', '2025-11-02 19:00:48'),
(206, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 19:03:18'),
(207, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 19:03:21'),
(208, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 19:11:21'),
(209, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 19:11:24'),
(210, 2, 'Atualizou Lançamento', 'lancamentos', 4, 'Status: pago -> pendente; Data Pagamento: 2025-10-30 -> NULL; Forma Pgto: N/D -> NULL', '2025-11-02 19:38:41'),
(211, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 19:42:26'),
(212, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 19:42:29'),
(213, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 19:42:39'),
(214, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 19:42:43'),
(215, 2, 'Atualizou Lançamento', 'lancamentos', 3, 'Status: pago -> pendente; Data Pagamento: 2025-10-30 -> NULL; Forma Pgto: N/D -> NULL', '2025-11-02 19:44:05'),
(216, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 19:57:24'),
(217, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 19:57:29'),
(218, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 20:02:33'),
(219, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 20:03:19'),
(220, 2, 'Cadastro Lançamento', 'lancamentos', 7, 'Valor: R$ 3, Descrição: Compra de agua, Status: Em aberto', '2025-11-02 23:14:39'),
(221, 2, 'Edição Lançamento', 'lancamentos', 7, 'Campos alterados: Empresa: 56.252.542 LEANDRO DA MOTA PASCOALINO -> 56.252.542 LEANDRO DA MOTA PASCOALINO; Status:  -> pendente', '2025-11-02 23:35:19'),
(222, 2, 'Atualizou Lançamento', 'lancamentos', 7, 'Status: pendente -> pago; Data Pagamento: N/D -> 2025-11-03', '2025-11-02 23:35:37'),
(223, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-02 23:37:18'),
(224, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 23:37:37'),
(225, 1, 'Atualizou Lançamento', 'lancamentos', 7, 'Status: pago -> pendente; Data Pagamento: 2025-11-03 -> NULL; Forma Pgto: PIX -> NULL', '2025-11-02 23:38:36'),
(226, 1, 'Atualizou Lançamento', 'lancamentos', 7, 'Status: pendente -> pago; Data Pagamento: N/D -> 2025-11-03; Forma Pgto: N/D -> PIX', '2025-11-02 23:38:42'),
(227, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 23:40:41'),
(228, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 23:42:46'),
(229, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 23:42:50'),
(230, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 23:46:17'),
(231, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 23:55:30'),
(232, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 23:55:33'),
(233, 1, 'Gerou Cobrança', 'cobrancas', 4, 'Valor: R$ 1850 para empresa ID: 4', '2025-11-02 23:59:39'),
(234, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-02 23:59:42'),
(235, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-02 23:59:46'),
(236, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 00:01:17'),
(237, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 00:01:21'),
(238, 1, 'Baixa Cobrança', 'cobrancas', 3, 'Status: Pago, Data Pagamento: 2025-11-03', '2025-11-03 00:01:39'),
(239, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 11:00:01'),
(240, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 11:00:04'),
(241, 1, 'Reverteu Baixa Cobrança', 'cobrancas', 1, NULL, '2025-11-03 11:14:20'),
(242, 1, 'Editou Cobrança', 'cobrancas', 2, 'Valor: R$ 1815.00, Descrição: Pagamento da empresa de contabilidade', '2025-11-03 11:14:24'),
(243, 1, 'Editou Cobrança', 'cobrancas', 1, 'Valor: R$ 1815.00, Descrição: Cobrança do escritório de contabilidade', '2025-11-03 11:14:28'),
(244, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 11:16:59'),
(245, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 11:17:02'),
(246, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 11:17:08'),
(247, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 11:17:10'),
(248, 1, 'Edição Usuário', 'usuarios', 2, 'Email: caio@dynamicmotioncentury.com.br, Tipo: cliente', '2025-11-03 11:17:45'),
(249, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 11:17:47'),
(250, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 11:17:49'),
(251, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 11:18:37'),
(252, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 11:18:41'),
(253, 1, 'Baixa Cobrança', 'cobrancas', 2, 'Status: Pago, Data Pagamento: 2025-11-03', '2025-11-03 11:18:50'),
(254, 1, 'Baixa Cobrança', 'cobrancas', 1, 'Status: Pago, Data Pagamento: 2025-11-03', '2025-11-03 11:18:52'),
(255, 1, 'Baixa Cobrança', 'cobrancas', 4, 'Status: Pago, Data Pagamento: 2025-11-03', '2025-11-03 11:18:53'),
(256, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 11:19:02'),
(257, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 11:19:06'),
(258, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 12:39:36'),
(259, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 12:39:37'),
(260, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 12:39:40'),
(261, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 12:39:42'),
(262, 1, 'Reverteu Baixa Cobrança', 'cobrancas', 3, NULL, '2025-11-03 13:29:30'),
(263, 1, 'Baixa Cobrança', 'cobrancas', 3, 'Status: Pago, Data Pagamento: 2025-11-03', '2025-11-03 13:29:40'),
(264, 1, 'Cadastro Lançamento', 'lancamentos', 8, 'Valor: R$ 500, Descrição: frwsdfsd, Status: Em aberto', '2025-11-03 14:00:18'),
(265, 1, 'Cadastro Lançamento', 'lancamentos', 9, 'Valor: R$ 500, Descrição: dasd asdas d, Status: pendente', '2025-11-03 14:06:57'),
(266, 1, 'Excluiu Lançamento', 'lancamentos', 8, NULL, '2025-11-03 14:07:03'),
(267, 1, 'Cadastro Usuário', 'usuarios', 5, 'Email: testee@gmail.com, Tipo: contador', '2025-11-03 14:08:37'),
(268, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 14:08:40'),
(269, NULL, 'Falha no login', 'usuarios', NULL, 'Email: testee@gmail.com', '2025-11-03 14:08:49'),
(270, 3, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:08:58'),
(271, 3, 'Logout', NULL, NULL, 'Usuário: Reverton henrique', '2025-11-03 14:09:04'),
(272, 5, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:09:13'),
(273, 5, 'Logout', NULL, NULL, 'Usuário: teste', '2025-11-03 14:11:35'),
(274, 5, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:11:42'),
(275, 5, 'Logout', NULL, NULL, 'Usuário: teste', '2025-11-03 14:14:12'),
(276, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:14:13'),
(277, 1, 'Edição Usuário', 'usuarios', 5, 'Email: testee@gmail.com, Tipo: contador', '2025-11-03 14:14:29'),
(278, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 14:14:32'),
(279, 5, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:14:40'),
(280, 5, 'Logout', NULL, NULL, 'Usuário: teste', '2025-11-03 14:15:55'),
(281, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:15:56'),
(282, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 14:35:41'),
(283, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:35:44'),
(284, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 14:39:33'),
(285, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:39:37'),
(286, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 14:40:25'),
(287, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:40:28'),
(288, 1, 'Reverteu Baixa Cobrança', 'cobrancas', 1, NULL, '2025-11-03 14:40:37'),
(289, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 14:40:50'),
(290, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:40:53'),
(291, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 14:41:43'),
(292, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:41:45'),
(293, 1, 'Baixa Cobrança', 'cobrancas', 1, 'Status: Pago, Data Pagamento: 2025-11-03', '2025-11-03 14:42:15'),
(294, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 14:42:19'),
(295, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:42:21'),
(296, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 14:42:41'),
(297, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:42:42'),
(298, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 14:44:01'),
(299, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:44:03'),
(300, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 14:44:41'),
(301, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:44:43'),
(302, 1, 'Edição Usuário', 'usuarios', 2, 'Email: caio@dynamicmotioncentury.com.br, Tipo: cliente', '2025-11-03 14:44:56'),
(303, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 14:44:57'),
(304, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:44:59'),
(305, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 14:48:30'),
(306, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:48:33'),
(307, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 14:53:16'),
(308, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:53:17'),
(309, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 14:53:42'),
(310, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 14:53:44'),
(311, 1, 'Reverteu Baixa Cobrança', 'cobrancas', 3, NULL, '2025-11-03 14:58:19'),
(312, 1, 'Edição Lançamento', 'lancamentos', 4, 'Campos alterados: Empresa: LAURIVAN DE SOUSA OLIVEIRA LTDA -> LAURIVAN DE SOUSA OLIVEIRA LTDA; Competência: N/D -> N/D; Forma Pgto: N/D -> Boleto Bancário', '2025-11-03 15:39:37'),
(313, 1, 'Edição Lançamento', 'lancamentos', 4, 'Campos alterados: Empresa: LAURIVAN DE SOUSA OLIVEIRA LTDA -> LAURIVAN DE SOUSA OLIVEIRA LTDA; Competência: 30/11/-0001 -> N/D; Forma Pgto: Boleto Bancário -> PIX', '2025-11-03 15:39:43'),
(314, 1, 'Edição Lançamento', 'lancamentos', 6, 'Campos alterados: Empresa: CATARINA DA SILVA ROCHA SANTIAGO FERREIRA -> CATARINA DA SILVA ROCHA SANTIAGO FERREIRA; Competência: N/D -> N/D; Forma Pgto: N/D -> Boleto Bancário', '2025-11-03 15:39:49'),
(315, 1, 'Edição Lançamento', 'lancamentos', 3, 'Campos alterados: Empresa: 56.252.542 LEANDRO DA MOTA PASCOALINO -> 56.252.542 LEANDRO DA MOTA PASCOALINO; Competência: N/D -> N/D; Forma Pgto: N/D -> Boleto Bancário', '2025-11-03 15:39:55'),
(316, 1, 'Edição Lançamento', 'lancamentos', 5, 'Campos alterados: Empresa: CATARINA DA SILVA ROCHA SANTIAGO FERREIRA -> CATARINA DA SILVA ROCHA SANTIAGO FERREIRA; Competência: N/D -> N/D; Forma Pgto: N/D -> PIX', '2025-11-03 15:39:59'),
(317, 1, 'Atualizou Lançamento', 'lancamentos', 9, 'Status: pendente -> pago; Data Pagamento: N/D -> 2025-11-03; Forma Pgto: Boleto Bancário -> PIX', '2025-11-03 15:40:05'),
(318, 1, 'Atualizou Lançamento', 'lancamentos', 4, 'Status: pendente -> pago; Data Pagamento: N/D -> 2025-11-03', '2025-11-03 15:40:09'),
(319, 1, 'Atualizou Lançamento', 'lancamentos', 6, 'Status: pago -> pendente; Data Pagamento: N/D -> NULL; Forma Pgto: Boleto Bancário -> NULL', '2025-11-03 15:40:17'),
(320, 1, 'Atualizou Lançamento', 'lancamentos', 6, 'Status: pendente -> pago; Data Pagamento: N/D -> 2025-11-03; Forma Pgto: N/D -> PIX', '2025-11-03 15:40:21'),
(321, 1, 'Atualizou Lançamento', 'lancamentos', 5, 'Status: pago -> pendente; Data Pagamento: N/D -> NULL; Forma Pgto: PIX -> NULL', '2025-11-03 15:40:25'),
(322, 1, 'Atualizou Lançamento', 'lancamentos', 5, 'Status: pendente -> pago; Data Pagamento: N/D -> 2025-11-03; Forma Pgto: N/D -> Boleto Bancário', '2025-11-03 15:40:29'),
(323, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 15:42:18'),
(324, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 15:42:20'),
(325, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 15:45:19'),
(326, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 15:45:23'),
(327, 1, 'Editou Forma de Pagamento', 'formas_pagamento', 0, 'Nome: Boleto Bancário', '2025-11-03 17:54:23'),
(328, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 17:59:07'),
(329, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 17:59:12'),
(330, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 18:00:01'),
(331, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 18:00:04'),
(332, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-03 18:00:38'),
(333, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 18:00:40'),
(334, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-03 18:05:55'),
(335, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-03 18:05:58'),
(336, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 1, 'Conta de luz', '2025-11-04 11:13:35'),
(337, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 2, 'Conta de agua', '2025-11-04 11:13:37'),
(338, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 3, 'Aluguel', '2025-11-04 11:13:42'),
(339, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 4, 'Contabilidade', '2025-11-04 11:14:02'),
(340, 1, 'Edição Lançamento', 'lancamentos', 7, 'Campos alterados: Empresa: 56.252.542 LEANDRO DA MOTA PASCOALINO -> 56.252.542 LEANDRO DA MOTA PASCOALINO; Forma Pgto: PIX (id: 1) -> PIX (id: 1); Categoria: N/D -> Conta de agua', '2025-11-04 11:14:24'),
(341, 1, 'Edição Lançamento', 'lancamentos', 9, 'Campos alterados: Empresa: 56.252.542 LEANDRO DA MOTA PASCOALINO -> 56.252.542 LEANDRO DA MOTA PASCOALINO; Descrição: dasd asdas d -> Conta de luz ; Competência: 30/11/-0001 -> N/D; Forma Pgto: PIX (id: 1) -> PIX (id: 1); Categoria: N/D -> Conta de luz', '2025-11-04 11:14:41'),
(342, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 5, 'Construção', '2025-11-04 11:15:20'),
(343, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 6, 'Manutenção', '2025-11-04 11:15:26'),
(344, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 7, 'Sistema', '2025-11-04 11:15:29'),
(345, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 8, 'Organizar', '2025-11-04 11:15:39'),
(346, 1, 'Edição Lançamento', 'lancamentos', 4, 'Campos alterados: Empresa: LAURIVAN DE SOUSA OLIVEIRA LTDA -> LAURIVAN DE SOUSA OLIVEIRA LTDA; Competência: 30/11/-0001 -> N/D; Forma Pgto: PIX (id: 1) -> PIX (id: 1); Categoria: N/D -> Organizar', '2025-11-04 11:16:00'),
(347, 1, 'Edição Lançamento', 'lancamentos', 6, 'Campos alterados: Empresa: CATARINA DA SILVA ROCHA SANTIAGO FERREIRA -> CATARINA DA SILVA ROCHA SANTIAGO FERREIRA; Competência: 30/11/-0001 -> N/D; Forma Pgto: PIX (id: 1) -> PIX (id: 1); Categoria: N/D -> Organizar', '2025-11-04 11:16:10'),
(348, 1, 'Edição Lançamento', 'lancamentos', 3, 'Campos alterados: Empresa: 56.252.542 LEANDRO DA MOTA PASCOALINO -> 56.252.542 LEANDRO DA MOTA PASCOALINO; Competência: 30/11/-0001 -> N/D; Forma Pgto: Boleto Bancário (id: 2) -> Boleto Bancário (id: 2); Categoria: N/D -> Construção', '2025-11-04 11:16:23'),
(349, 1, 'Edição Lançamento', 'lancamentos', 5, 'Campos alterados: Empresa: CATARINA DA SILVA ROCHA SANTIAGO FERREIRA -> CATARINA DA SILVA ROCHA SANTIAGO FERREIRA; Competência: 30/11/-0001 -> N/D; Forma Pgto: Boleto Bancário (id: 2) -> Boleto Bancário (id: 2); Categoria: N/D -> Construção', '2025-11-04 11:16:30'),
(350, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 11:18:29'),
(351, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 11:18:32'),
(352, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 11:27:42'),
(353, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 11:27:44'),
(354, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 11:27:48'),
(355, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 11:27:50'),
(356, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 9, 'Honorário', '2025-11-04 11:27:57'),
(357, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 10, 'Serviço', '2025-11-04 11:28:01'),
(358, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 11, 'Reembolso', '2025-11-04 11:28:05'),
(359, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 12, 'Venda', '2025-11-04 11:28:08'),
(360, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 13, 'Fornecedor', '2025-11-04 11:28:12'),
(361, 1, 'Editou Categoria Lancamento', 'categorias_lancamento', 2, 'Conta de água', '2025-11-04 11:29:03'),
(362, 1, 'Editou Categoria Lancamento', 'categorias_lancamento', 2, 'Conta de agua', '2025-11-04 11:29:09'),
(363, 1, 'Criou Categoria Lancamento', 'categorias_lancamento', 14, 'Comissão', '2025-11-04 11:29:32'),
(364, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 12:52:06'),
(365, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 12:52:07'),
(366, 1, 'Criou Pasta Raiz', 'documentos_pastas', 1, 'Documentos', '2025-11-04 12:54:12'),
(367, 1, 'Upload Arquivo', 'documentos_arquivos', 1, 'logo.png', '2025-11-04 12:55:07'),
(368, 1, 'Criou Pasta Raiz', 'documentos_pastas', 1, 'TESTE', '2025-11-04 13:03:31'),
(369, 1, 'Editou Pasta', 'documentos_pastas', 1, 'Documentos - Caio LTDA', '2025-11-04 13:25:33'),
(370, 1, 'Associou Pasta a Usuários', 'documentos_pastas', 1, 'user_ids=1,2', '2025-11-04 13:25:43'),
(371, 1, 'Upload Arquivo', 'documentos_arquivos', 1, 'Novo Projeto.png', '2025-11-04 13:26:04'),
(372, 1, 'Excluiu Arquivo', 'documentos_arquivos', 1, NULL, '2025-11-04 13:26:14'),
(373, 1, 'Criou Pasta Raiz', 'documentos_pastas', 2, 'Documentos - Reverton LTDA', '2025-11-04 13:26:33'),
(374, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 13:26:35'),
(375, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 13:26:38'),
(376, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 13:26:49'),
(377, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 13:26:53'),
(378, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 13:33:04'),
(379, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 13:33:08'),
(380, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 13:33:51'),
(381, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 13:33:54'),
(382, 1, 'Upload Arquivo', 'documentos_arquivos', 2, 'Novo Projeto.png', '2025-11-04 13:34:01'),
(383, 1, 'Aprovou Arquivo', 'documentos_arquivos', 2, 'aprovar', '2025-11-04 13:43:24'),
(384, 1, 'Criou Subpasta', 'documentos_pastas', 3, 'Novela', '2025-11-04 13:43:38'),
(385, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 13:44:16'),
(386, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 13:44:19'),
(387, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 13:44:33'),
(388, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 13:44:38'),
(389, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 14:10:52'),
(390, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 14:10:53'),
(391, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 14:36:05'),
(392, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 14:36:06'),
(393, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 14:44:56'),
(394, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 14:44:57'),
(395, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 14:48:37'),
(396, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 14:48:40'),
(397, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 14:51:00'),
(398, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 14:51:02'),
(399, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 14:51:09'),
(400, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 14:51:12'),
(401, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 14:53:43'),
(402, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 14:53:44'),
(403, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 14:54:05'),
(404, 5, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 14:54:09'),
(405, 5, 'Logout', NULL, NULL, 'Usuário: teste', '2025-11-04 14:54:24'),
(406, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 14:54:32'),
(407, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 15:22:58'),
(408, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 15:23:10'),
(409, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 15:53:21'),
(410, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 15:53:25'),
(411, 2, 'Upload Arquivo', 'documentos_arquivos', 3, 'WhatsApp Image 2025-11-03 at 16.44.31.png', '2025-11-04 15:53:48'),
(412, 2, 'Excluiu Arquivo', 'documentos_arquivos', 3, NULL, '2025-11-04 15:53:54'),
(413, 2, 'Upload Arquivo', 'documentos_arquivos', 4, 'WhatsApp Image 2025-11-03 at 16.44.31.png', '2025-11-04 15:54:16'),
(414, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 15:54:18'),
(415, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 15:54:21'),
(416, 1, 'Reprovou Arquivo', 'documentos_arquivos', 4, 'reprovar', '2025-11-04 15:55:00'),
(417, 1, 'Criou Pasta Raiz', 'documentos_pastas', 4, 'Teste de criação de pasta', '2025-11-04 15:57:29'),
(418, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 16:20:24'),
(419, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 16:20:25'),
(420, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 16:24:13'),
(421, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 16:24:15'),
(422, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 16:25:09'),
(423, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 16:25:11'),
(424, 1, 'Criou Pasta Raiz', 'documentos_pastas', 1, 'Documentos - Caio', '2025-11-04 16:59:55'),
(425, 1, 'Criou Pasta Raiz', 'documentos_pastas', 2, 'Documentos - Reverton', '2025-11-04 17:00:07'),
(426, 1, 'Criou Pasta Raiz', 'documentos_pastas', 3, 'Documentos - teste', '2025-11-04 17:00:17'),
(427, 1, 'Criou Subpasta', 'documentos_pastas', 4, 'Contrato', '2025-11-04 17:00:31'),
(428, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 17:00:56'),
(429, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 17:00:59'),
(430, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 17:06:02'),
(431, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 17:06:04'),
(432, 1, 'Criou Pasta Raiz', 'documentos_pastas', 1, 'Documentos - Caio', '2025-11-04 17:17:45'),
(433, 1, 'Criou Subpasta', 'documentos_pastas', 2, 'Organizar', '2025-11-04 17:17:52'),
(434, 1, 'Excluiu Pasta', 'documentos_pastas', 2, NULL, '2025-11-04 17:30:18'),
(435, 1, 'Criou Subpasta', 'documentos_pastas', 3, 'Organizar', '2025-11-04 17:30:27'),
(436, 1, 'Editou Pasta', 'documentos_pastas', 3, 'Organizar', '2025-11-04 17:30:32'),
(437, 1, 'Editou Pasta', 'documentos_pastas', 1, 'Documentos - Caiot', '2025-11-04 17:30:38'),
(438, 1, 'Editou Pasta', 'documentos_pastas', 3, 'Organizar', '2025-11-04 17:30:45'),
(439, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 17:30:50'),
(440, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 17:30:54'),
(441, 2, 'Upload Arquivo', 'documentos_arquivos', 1, 'Novo Projeto.png', '2025-11-04 17:31:08'),
(442, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 17:31:15'),
(443, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 17:31:17'),
(444, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 17:31:20'),
(445, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 17:31:23'),
(446, 1, 'Excluiu Arquivo', 'documentos_arquivos', 1, NULL, '2025-11-04 17:31:40'),
(447, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 17:31:43'),
(448, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 17:31:46'),
(449, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 17:31:48'),
(450, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 17:31:51'),
(451, 2, 'Upload Arquivo', 'documentos_arquivos', 2, 'Novo Projeto.png', '2025-11-04 17:32:13'),
(452, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 17:32:15'),
(453, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 17:32:18'),
(454, 1, 'Reprovou Arquivo', 'documentos_arquivos', 2, 'reprovar', '2025-11-04 17:32:23'),
(455, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 17:32:25'),
(456, 2, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 17:32:27'),
(457, 2, 'Logout', NULL, NULL, 'Usuário: Caio Vinícius', '2025-11-04 17:32:44'),
(458, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-11-04 17:32:46'),
(459, 1, 'Excluiu Arquivo', 'documentos_arquivos', 2, NULL, '2025-11-04 17:32:52'),
(460, 1, 'Logout', NULL, NULL, 'Usuário: Administrador', '2025-11-04 17:33:02');

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
('email_from', 'nao-responda@seuapp.com'),
('smtp_host', 'smtp.exemplo.com'),
('smtp_password', 'suasenha'),
('smtp_port', '587'),
('smtp_secure', 'tls'),
('smtp_username', 'seuemail@exemplo.com');

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
(1, 'Honorários Contábeis', 1),
(2, 'Serviços de Despachante', 1),
(3, 'Impostos', 1),
(4, 'Consultoria', 1);

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
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `telefone`, `senha`, `tipo`, `id_cliente_associado`, `acesso_lancamentos`, `ativo`) VALUES
(1, 'Administrador', 'dmc@dynamicmotioncentury.com.br', '81983656068', '$2y$10$C35N4fSI5Yka7aqnMzAw2.L6uNUGUbtANCoVH/hRM8nh3q20d/sXO', 'admin', NULL, 0, 1),
(2, 'Caio Vinícius', 'caio@dynamicmotioncentury.com.br', '81983656068', '$2y$10$E5MG4Xmq8ZE8TQ87NYdHHOARpUjw0kAG1c6dXkVJaeipwEkg6SJDO', 'cliente', 2, 1, 1),
(3, 'Reverton henrique', 'teste@gmail.com', '81000000000', '$2y$10$27Epf/DmGUJSuN2gpqnoYeCycRmZ57LWrlVCZn2DDB7A9y.2WROPe', 'cliente', 1, 0, 1),
(5, 'teste', 'testee@gmail.com', '', '$2y$10$uou8SbJ1MP9W2x6dBlBtV.t2Fce9v8Uft1Atmz1flIvavzgNaD38i', 'contador', NULL, 0, 1);

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
-- Índices de tabela `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Índices de tabela `tipos_cobranca`
--
ALTER TABLE `tipos_cobranca`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `cobrancas`
--
ALTER TABLE `cobrancas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `documentos_arquivos`
--
ALTER TABLE `documentos_arquivos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `documentos_pastas`
--
ALTER TABLE `documentos_pastas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `formas_pagamento`
--
ALTER TABLE `formas_pagamento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `lancamentos`
--
ALTER TABLE `lancamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=461;

--
-- AUTO_INCREMENT de tabela `tipos_cobranca`
--
ALTER TABLE `tipos_cobranca`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
