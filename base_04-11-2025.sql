-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 05/11/2025 às 04:48
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

-- SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
-- START TRANSACTION;
-- SET time_zone = "+00:00";


--!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT; 
--!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS ;
--!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION ;
--!40101 SET NAMES utf8mb4; 

--
-- Banco de dados: gestao_financeira
--

-- --------------------------------------------------------

--
-- Estrutura para tabela categorias_lancamento
--

CREATE TABLE categorias_lancamento (
  id int(10) UNSIGNED NOT NULL,
  nome varchar(150) NOT NULL,
  descricao text DEFAULT NULL,
  ativo tinyint(1) NOT NULL DEFAULT 1,
  created_at timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela categorias_lancamento
--

INSERT INTO categorias_lancamento (id, nome, descricao, ativo, created_at) VALUES
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
-- Estrutura para tabela clientes
--

CREATE TABLE clientes (
  id int(11) NOT NULL,
  nome_responsavel varchar(100) NOT NULL,
  email_contato varchar(100) NOT NULL,
  telefone varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela clientes
--

INSERT INTO clientes (id, nome_responsavel, email_contato, telefone) VALUES
(3, 'Jailson Pereira', 'escritoriojpcontabil@gmail.com', '81 99918-0003');

-- --------------------------------------------------------

--
-- Estrutura para tabela cobrancas
--

CREATE TABLE cobrancas (
  id int(11) NOT NULL,
  id_empresa int(11) NOT NULL,
  id_tipo_cobranca int(11) DEFAULT NULL,
  data_competencia date NOT NULL,
  data_vencimento date NOT NULL,
  valor decimal(10,2) NOT NULL,
  id_forma_pagamento int(11) NOT NULL,
  contexto_pagamento text DEFAULT NULL COMMENT 'Chave PIX, link de pagamento, código de barras, etc.',
  descricao text DEFAULT NULL,
  status_pagamento enum('Pendente','Pago','Vencido') NOT NULL DEFAULT 'Pendente',
  data_criacao timestamp NOT NULL DEFAULT current_timestamp(),
  data_pagamento date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela contador_clientes_assoc
--

CREATE TABLE contador_clientes_assoc (
  id_usuario_contador int(11) NOT NULL,
  id_cliente int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela documentos_arquivos
--

CREATE TABLE documentos_arquivos (
  id int(11) NOT NULL,
  pasta_id int(11) NOT NULL,
  nome_original varchar(512) NOT NULL,
  caminho varchar(1024) NOT NULL,
  tamanho bigint(20) DEFAULT 0,
  tipo_mime varchar(255) DEFAULT NULL,
  enviado_por_user_id int(11) DEFAULT NULL,
  status enum('pending','approved','rejected') DEFAULT 'pending',
  criado_em datetime DEFAULT current_timestamp(),
  aprovado_por int(11) DEFAULT NULL,
  aprovado_em datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela documentos_download_tokens
--

CREATE TABLE documentos_download_tokens (
  token varchar(128) NOT NULL,
  arquivo_id int(11) NOT NULL,
  criado_por int(11) DEFAULT NULL,
  expires_at datetime NOT NULL,
  criado_em datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela documentos_pastas
--

CREATE TABLE documentos_pastas (
  id int(11) NOT NULL,
  nome varchar(255) NOT NULL,
  parent_id int(11) DEFAULT NULL,
  owner_user_id int(11) DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp(),
  updated_at datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela documentos_pastas_usuarios
--

CREATE TABLE documentos_pastas_usuarios (
  pasta_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  papel varchar(32) DEFAULT 'usuario',
  criado_em datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela empresas
--

CREATE TABLE empresas (
  id int(11) NOT NULL,
  id_cliente int(11) NOT NULL,
  cnpj varchar(18) NOT NULL,
  razao_social varchar(255) NOT NULL,
  nome_fantasia varchar(255) DEFAULT NULL,
  data_abertura date DEFAULT NULL,
  data_contratacao DATE DEFAULT NULL,
  endereco text DEFAULT NULL,
  telefone_comercial varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- Estrutura para tabela tb_confg_emailCliente
CREATE TABLE IF NOT EXISTS tb_confg_emailCliente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_client INT NOT NULL,
    permissao VARCHAR(100) NULL,
    descricao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_id_client (id_client),
    INDEX idx_permissao (permissao)
);

--
-- Despejando dados para a tabela empresas
--

INSERT INTO empresas (id, id_cliente, cnpj, razao_social, nome_fantasia, data_abertura, endereco, telefone_comercial) VALUES
(6, 3, '49.307.112/0001-89', 'JAILSON PEREIRA CONSULTORIA CONTABIL', 'JP CONSULTORIA CONTABIL', '2023-01-23', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela formas_pagamento
--

CREATE TABLE formas_pagamento (
  id int(11) NOT NULL,
  nome varchar(50) NOT NULL,
  icone_bootstrap varchar(50) DEFAULT NULL COMMENT 'Classe do ícone do Bootstrap, ex: bi-qr-code',
  ativo tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo, 0 = Inativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela formas_pagamento
--

INSERT INTO formas_pagamento (id, nome, icone_bootstrap, ativo) VALUES
(1, 'PIX', 'bi-qr-code', 1),
(2, 'Boleto Bancário', 'bi-upc-scan', 1),
(3, 'Cartão de Crédito', 'bi-credit-card', 1),
(4, 'Transferência Bancária', 'bi-bank', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela lancamentos
--

CREATE TABLE lancamentos (
  id int(11) NOT NULL,
  id_empresa int(11) NOT NULL,
  descricao varchar(255) NOT NULL,
  valor decimal(10,2) NOT NULL,
  tipo enum('receita','despesa') NOT NULL,
  data_vencimento date NOT NULL,
  data_competencia date DEFAULT NULL,
  data_pagamento date DEFAULT NULL,
  status enum('pendente','pago','contestado','confirmado_cliente') NOT NULL DEFAULT 'pendente',
  anexo_path varchar(255) DEFAULT NULL,
  metodo_pagamento varchar(255) DEFAULT NULL,
  id_forma_pagamento int(11) DEFAULT NULL,
  id_categoria int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela logs
--

CREATE TABLE logs (
  id int(11) NOT NULL,
  id_usuario int(11) DEFAULT NULL,
  acao varchar(255) NOT NULL,
  tabela_afetada varchar(50) DEFAULT NULL,
  id_registro_afetado int(11) DEFAULT NULL,
  detalhes text DEFAULT NULL,
  timestamp datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela system_settings
--

CREATE TABLE system_settings (
  setting_key varchar(50) NOT NULL,
  setting_value text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela system_settings
--

INSERT INTO system_settings (setting_key, setting_value) VALUES
('email_from', 'nao-responda@seuapp.com'),
('smtp_host', 'smtp.exemplo.com'),
('smtp_password', 'suasenha'),
('smtp_port', '587'),
('smtp_secure', 'tls'),
('smtp_username', 'seuemail@exemplo.com');

-- --------------------------------------------------------

--
-- Estrutura para tabela tipos_cobranca
--

CREATE TABLE tipos_cobranca (
  id int(11) NOT NULL,
  nome varchar(100) NOT NULL,
  ativo tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela tipos_cobranca
--

INSERT INTO tipos_cobranca (id, nome, ativo) VALUES
(1, 'Honorários Contábeis', 1),
(2, 'Serviços de Despachante', 1),
(3, 'Impostos', 1),
(4, 'Consultoria', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela usuarios
--

CREATE TABLE usuarios (
  id int(11) NOT NULL,
  nome varchar(100) NOT NULL,
  email varchar(100) NOT NULL,
  telefone varchar(20) DEFAULT NULL,
  senha varchar(255) NOT NULL,
  tipo enum('admin','contador','cliente') NOT NULL,
  id_cliente_associado int(11) DEFAULT NULL,
  acesso_lancamentos tinyint(1) NOT NULL DEFAULT 0,
  ativo tinyint(1) DEFAULT 1,
  is_super_admin TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela usuarios
--

INSERT INTO usuarios (id, nome, email, telefone, senha, tipo, id_cliente_associado, acesso_lancamentos, ativo, is_super_admin) VALUES
(2, 'Caio Vinícius', 'caio@dynamicmotioncentury.com.br', '81983656068', '$2y$10$33uB5eMzzl1ScCDQ4IBh/eApsREwPqfWeANhjZnn4ik6vf23Kgz.i', 'admin', NULL, 0, 1,1),
(6, 'Jailson Pereira', 'escritoriojpcontabil@gmail.com', '81 99918-0003', '$2y$10$yju5dmZWhOjUgTCSbZ6FCOVnmKO9c86fPP8BvCWJghQpp2ehfWJgS', 'admin', NULL, 0, 1,0);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela categorias_lancamento
--
ALTER TABLE categorias_lancamento
  ADD PRIMARY KEY (id);

--
-- Índices de tabela clientes
--
ALTER TABLE clientes
  ADD PRIMARY KEY (id);

--
-- Índices de tabela cobrancas
--
ALTER TABLE cobrancas
  ADD PRIMARY KEY (id),
  ADD KEY id_empresa (id_empresa),
  ADD KEY id_forma_pagamento (id_forma_pagamento),
  ADD KEY fk_cobrancas_tipo (id_tipo_cobranca);

--
-- Índices de tabela contador_clientes_assoc
--
ALTER TABLE contador_clientes_assoc
  ADD PRIMARY KEY (id_usuario_contador,id_cliente),
  ADD KEY id_cliente (id_cliente);

--
-- Índices de tabela documentos_arquivos
--
ALTER TABLE documentos_arquivos
  ADD PRIMARY KEY (id),
  ADD KEY pasta_id (pasta_id),
  ADD KEY enviado_por_user_id (enviado_por_user_id),
  ADD KEY status (status);

--
-- Índices de tabela documentos_download_tokens
--
ALTER TABLE documentos_download_tokens
  ADD PRIMARY KEY (token),
  ADD KEY arquivo_id (arquivo_id),
  ADD KEY expires_at (expires_at);

--
-- Índices de tabela documentos_pastas
--
ALTER TABLE documentos_pastas
  ADD PRIMARY KEY (id),
  ADD KEY parent_id (parent_id),
  ADD KEY owner_user_id (owner_user_id);

--
-- Índices de tabela documentos_pastas_usuarios
--
ALTER TABLE documentos_pastas_usuarios
  ADD PRIMARY KEY (pasta_id,user_id),
  ADD KEY user_id (user_id);

--
-- Índices de tabela empresas
--
ALTER TABLE empresas
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY cnpj (cnpj),
  ADD KEY id_cliente (id_cliente);

--
-- Índices de tabela formas_pagamento
--
ALTER TABLE formas_pagamento
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY nome (nome);

--
-- Índices de tabela lancamentos
--
ALTER TABLE lancamentos
  ADD PRIMARY KEY (id),
  ADD KEY id_empresa (id_empresa),
  ADD KEY idx_lancamentos_id_forma_pagamento (id_forma_pagamento),
  ADD KEY idx_lancamentos_id_categoria (id_categoria);

--
-- Índices de tabela logs
--
ALTER TABLE logs
  ADD PRIMARY KEY (id),
  ADD KEY id_usuario (id_usuario);

--
-- Índices de tabela system_settings
--
ALTER TABLE system_settings
  ADD PRIMARY KEY (setting_key);

--
-- Índices de tabela tipos_cobranca
--
ALTER TABLE tipos_cobranca
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY nome (nome);

--
-- Índices de tabela usuarios
--
ALTER TABLE usuarios
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY email (email);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela categorias_lancamento
--
ALTER TABLE categorias_lancamento
  MODIFY id int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela clientes
--
ALTER TABLE clientes
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela cobrancas
--
ALTER TABLE cobrancas
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela documentos_arquivos
--
ALTER TABLE documentos_arquivos
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela documentos_pastas
--
ALTER TABLE documentos_pastas
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela empresas
--
ALTER TABLE empresas
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela formas_pagamento
--
ALTER TABLE formas_pagamento
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela lancamentos
--
ALTER TABLE lancamentos
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela logs
--
ALTER TABLE logs
  MODIFY id int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela tipos_cobranca
--
ALTER TABLE tipos_cobranca
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela usuarios
--
ALTER TABLE usuarios
  MODIFY id int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas cobrancas
--
ALTER TABLE cobrancas
  ADD CONSTRAINT fk_cobrancas_empresa FOREIGN KEY (id_empresa) REFERENCES empresas (id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_cobrancas_forma_pagamento FOREIGN KEY (id_forma_pagamento) REFERENCES formas_pagamento (id),
  ADD CONSTRAINT fk_cobrancas_tipo FOREIGN KEY (id_tipo_cobranca) REFERENCES tipos_cobranca (id) ON DELETE SET NULL;

--
-- Restrições para tabelas contador_clientes_assoc
--
ALTER TABLE contador_clientes_assoc
  ADD CONSTRAINT contador_clientes_assoc_ibfk_1 FOREIGN KEY (id_usuario_contador) REFERENCES usuarios (id) ON DELETE CASCADE,
  ADD CONSTRAINT contador_clientes_assoc_ibfk_2 FOREIGN KEY (id_cliente) REFERENCES clientes (id) ON DELETE CASCADE;

--
-- Restrições para tabelas empresas
--
ALTER TABLE empresas
  ADD CONSTRAINT empresas_ibfk_1 FOREIGN KEY (id_cliente) REFERENCES clientes (id) ON DELETE CASCADE;

--
-- Restrições para tabelas lancamentos
--
ALTER TABLE lancamentos
  ADD CONSTRAINT fk_lancamentos_forma_pagamento FOREIGN KEY (id_forma_pagamento) REFERENCES formas_pagamento (id) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT lancamentos_ibfk_1 FOREIGN KEY (id_empresa) REFERENCES empresas (id) ON DELETE CASCADE;

--
-- Restrições para tabelas logs
--
ALTER TABLE logs
  ADD CONSTRAINT logs_ibfk_1 FOREIGN KEY (id_usuario) REFERENCES usuarios (id) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- Migration: Add RBAC tables, seed roles/permissions, and assign existing users
-- Generated: 2025-11-27

SELECT @@SQL_MODE INTO @OLD_SQL_MODE;
SET SESSION SQL_MODE='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';
START TRANSACTION;

-- 1) Create core RBAC tables if they do not exist
CREATE TABLE IF NOT EXISTS roles (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) NOT NULL UNIQUE,
  description TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) NOT NULL UNIQUE,
  description TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  INDEX idx_rp_role (role_id),
  INDEX idx_rp_perm (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  INDEX idx_ur_user (user_id),
  INDEX idx_ur_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Ensure system_settings.rbac_version exists (initialize to 1 if absent)
INSERT INTO system_settings (setting_key, setting_value)
SELECT 'rbac_version', '0'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM system_settings WHERE setting_key = 'rbac_version');

-- 3) Seed core roles (no duplicates)
INSERT INTO roles (name, slug, description)
SELECT t.name, t.slug, t.description FROM (
  SELECT 'Super Admin' AS name, 'super_admin' AS slug, 'Super administrator (protected)' AS description
  UNION ALL SELECT 'Admin', 'admin', 'Admin users'
  UNION ALL SELECT 'Contador', 'contador', 'Accounting / bookkeeper'
  UNION ALL SELECT 'Cliente', 'cliente', 'Client (default)'
  UNION ALL SELECT 'Cliente - Lançamentos', 'cliente_lancamentos', 'Cliente com acesso a lançamentos'
  UNION ALL SELECT 'Cliente - Cobranças', 'cliente_cobranca', 'Cliente com acesso a cobranças'
) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug = t.slug);

-- 4) Seed common permissions (observed in current codebase)
INSERT INTO permissions (name, slug, description)
SELECT p.name, p.slug, p.description FROM (
  SELECT 'Acessar dashboard' AS name, 'acessar_dashboard' AS slug, NULL AS description
  UNION ALL SELECT 'Acessar lançamentos','acessar_lancamentos',NULL
  UNION ALL SELECT 'Criar lançamento','criar_lancamento',NULL
  UNION ALL SELECT 'Editar lançamento','editar_lancamento',NULL
  UNION ALL SELECT 'Excluir lançamento','excluir_lancamento',NULL
  UNION ALL SELECT 'Acessar cobranças','acessar_cobrancas',NULL
  UNION ALL SELECT 'Acessar configurações','acessar_configuracoes',NULL
  UNION ALL SELECT 'Gerenciar usuários','gerenciar_usuarios',NULL
  UNION ALL SELECT 'Gerenciar papéis','gerenciar_papeis',NULL
  UNION ALL SELECT 'Gerar relatórios','gerar_relatorios',NULL
  UNION ALL SELECT 'Visualizar documentos','visualizar_documentos',NULL
  UNION ALL SELECT 'Acessar associações contador','acessar_associacoes_contador',NULL
  UNION ALL SELECT 'Acessar logs','acessar_logs',NULL
  UNION ALL SELECT 'Gerenciar empresas','gerenciar_empresas',NULL
  UNION ALL SELECT 'Gerenciar documentos','gerenciar_documentos',NULL
  UNION ALL SELECT 'Gerenciar cobranças','gerenciar_cobrancas',NULL
) AS p
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = p.slug);

-- 5) Map sensible default role_permissions (idempotent)
-- Admin and Super Admin should have all seeded permissions; contador a subset; cliente roles limited

-- Grant all seeded permissions to admin
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id AS role_id, p.id AS permission_id
FROM roles r
JOIN permissions p ON 1=1
WHERE r.slug IN ('admin','super_admin')
  AND p.slug IN (
    'acessar_dashboard','acessar_lancamentos','criar_lancamento','editar_lancamento','excluir_lancamento',
    'acessar_cobrancas','acessar_configuracoes','gerenciar_usuarios','gerenciar_papeis','gerar_relatorios',
    'visualizar_documentos','acessar_associacoes_contador','acessar_logs','gerenciar_empresas','gerenciar_documentos','gerenciar_cobrancas'
  );

-- Grant subset to contador
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id AS role_id, p.id AS permission_id
FROM roles r
JOIN permissions p ON p.slug IN ('acessar_dashboard','acessar_lancamentos','criar_lancamento','editar_lancamento','acessar_cobrancas','gerar_relatorios')
WHERE r.slug = 'contador';

-- Grant client-lançamentos limited perms
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('acessar_dashboard','acessar_lancamentos','visualizar_documentos') WHERE r.slug = 'cliente_lancamentos';

-- 6) Assign roles to existing users based on `usuarios.tipo` or `is_super_admin` flag
-- Assign super_admin role to users flagged as is_super_admin
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM usuarios u JOIN roles r ON r.slug = 'super_admin' WHERE u.is_super_admin = 1;

-- Map legacy tipo -> role
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM usuarios u JOIN roles r ON r.slug = 'admin' WHERE u.tipo = 'admin';
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM usuarios u JOIN roles r ON r.slug = 'contador' WHERE u.tipo = 'contador';
INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM usuarios u JOIN roles r ON r.slug = 'cliente' WHERE u.tipo = 'cliente';

-- 7) Add foreign key constraints for pivot tables if possible
ALTER TABLE role_permissions
  ADD CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE;
ALTER TABLE role_permissions
  ADD CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE;

ALTER TABLE user_roles
  ADD CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES usuarios (id) ON DELETE CASCADE;
ALTER TABLE user_roles
  ADD CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE;

-- 8) Initialize rbac_version to current value + 1 to force session refresh on deploy
UPDATE system_settings SET setting_value = (
  SELECT CAST((SELECT COALESCE(MAX(CAST(setting_value AS SIGNED)), 0) FROM system_settings WHERE setting_key = 'rbac_version') + 1 AS CHAR)
) WHERE setting_key = 'rbac_version';

COMMIT;
SET SESSION SQL_MODE = @OLD_SQL_MODE;

-- End of migration
