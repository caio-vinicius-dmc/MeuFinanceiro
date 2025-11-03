-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 03/11/2025 às 04:03
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

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
(1, 3, NULL, '2025-10-01', '2025-11-01', 1815.00, 2, '26091337299149385389835100000005312610000260161', 'Cobrança do escritório de contabilidade', 'Pago', '2025-11-02 19:55:35', '2025-11-02'),
(2, 4, NULL, '2025-09-01', '2025-10-01', 1815.00, 2, '81900000000', 'Pagamento da empresa de contabilidade', 'Pendente', '2025-11-02 20:31:34', NULL),
(3, 4, 1, '2025-11-01', '2025-12-01', 1815.00, 1, '819000000', ' Cobrança do setor de contabilidade', 'Pago', '2025-11-02 21:07:13', '2025-11-03'),
(4, 4, 1, '2025-11-01', '2025-12-01', 1850.00, 2, '90381902830981203812930', ' ', 'Pendente', '2025-11-03 02:59:39', NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `contador_clientes_assoc`
--

CREATE TABLE `contador_clientes_assoc` (
  `id_usuario_contador` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL
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
  `metodo_pagamento` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `lancamentos`
--

INSERT INTO `lancamentos` (`id`, `id_empresa`, `descricao`, `valor`, `tipo`, `data_vencimento`, `data_competencia`, `data_pagamento`, `status`, `anexo_path`, `metodo_pagamento`) VALUES
(3, 3, 'Mesa nova', 500.00, 'despesa', '2025-10-30', NULL, NULL, 'pendente', NULL, NULL),
(4, 4, 'Banner novo da fachada', 1500.00, 'despesa', '2025-10-31', NULL, NULL, 'pendente', NULL, NULL),
(5, 5, 'Balcão checkout', 850.00, 'despesa', '2025-10-30', NULL, NULL, 'pago', NULL, NULL),
(6, 5, 'pneus de carro', 1500.00, 'despesa', '2025-10-31', NULL, NULL, 'pago', NULL, NULL),
(7, 3, 'Compra de agua', 3.00, 'despesa', '2025-11-03', '2025-11-03', '2025-11-03', 'pago', NULL, 'PIX');

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
(238, 1, 'Baixa Cobrança', 'cobrancas', 3, 'Status: Pago, Data Pagamento: 2025-11-03', '2025-11-03 00:01:39');

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
(3, 'Reverton henrique', 'teste@gmail.com', '81000000000', '$2y$10$27Epf/DmGUJSuN2gpqnoYeCycRmZ57LWrlVCZn2DDB7A9y.2WROPe', 'cliente', 1, 0, 1);

--
-- Índices para tabelas despejadas
--

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
  ADD KEY `id_empresa` (`id_empresa`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT de tabela `tipos_cobranca`
--
ALTER TABLE `tipos_cobranca`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
