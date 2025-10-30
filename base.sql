-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 30/10/2025 às 19:51
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
-- Estrutura para tabela `lancamentos`
--

CREATE TABLE `lancamentos` (
  `id` int(11) NOT NULL,
  `id_empresa` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `tipo` enum('receita','despesa') NOT NULL,
  `data_vencimento` date NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `status` enum('pendente','pago','contestado','confirmado_cliente') NOT NULL DEFAULT 'pendente',
  `anexo_path` varchar(255) DEFAULT NULL,
  `observacao_contestacao` text DEFAULT NULL,
  `data_pagamento_cliente` date DEFAULT NULL,
  `metodo_pagamento` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `lancamentos`
--

INSERT INTO `lancamentos` (`id`, `id_empresa`, `descricao`, `valor`, `tipo`, `data_vencimento`, `data_pagamento`, `status`, `anexo_path`, `observacao_contestacao`, `data_pagamento_cliente`, `metodo_pagamento`) VALUES
(3, 3, 'Mesa nova', 500.00, 'despesa', '2025-10-30', '2025-10-30', 'pago', NULL, NULL, NULL, NULL),
(4, 4, 'Banner novo da fachada', 1500.00, 'despesa', '2025-10-31', '2025-10-30', 'pago', NULL, NULL, NULL, NULL),
(5, 5, 'Balcão checkout', 850.00, 'despesa', '2025-10-30', NULL, 'pendente', NULL, NULL, NULL, NULL),
(6, 5, 'pneus de carro', 1500.00, 'despesa', '2025-10-31', NULL, 'contestado', NULL, 'Esse lançamento não é para mim', NULL, NULL);

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
(102, 1, 'Login bem-sucedido', NULL, NULL, NULL, '2025-10-30 15:44:45');

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
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `email`, `telefone`, `senha`, `tipo`, `id_cliente_associado`, `ativo`) VALUES
(1, 'Administrador', 'dmc@dynamicmotioncentury.com.br', '81983656068', '$2y$10$C35N4fSI5Yka7aqnMzAw2.L6uNUGUbtANCoVH/hRM8nh3q20d/sXO', 'admin', NULL, 1),
(2, 'Caio Vinícius', 'caio@dynamicmotioncentury.com.br', '81983656068', '$2y$10$yinAqUMDeTwEqsEpxUJJDeV.A9fcA3kP2.Fee3kFZ2UIP1X12gF/m', 'cliente', 2, 1),
(3, 'Reverton henrique', 'teste@gmail.com', '81000000000', '$2y$10$27Epf/DmGUJSuN2gpqnoYeCycRmZ57LWrlVCZn2DDB7A9y.2WROPe', 'cliente', 1, 1);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT de tabela `empresas`
--
ALTER TABLE `empresas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `lancamentos`
--
ALTER TABLE `lancamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restrições para tabelas despejadas
--

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
