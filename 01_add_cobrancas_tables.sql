-- Etapa 1: Criação das tabelas para o módulo de Cobranças

-- Tabela para gerenciar as formas de pagamento
CREATE TABLE formas_pagamento (
   id  int(11) NOT NULL,
   nome  varchar(50) NOT NULL,
   icone_bootstrap  varchar(50) DEFAULT NULL COMMENT 'Classe do ícone do Bootstrap, ex: bi-qr-code',
   ativo  tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Ativo, 0 = Inativo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Inserir algumas formas de pagamento padrão
INSERT INTO  formas_pagamento  ( id ,  nome ,  icone_bootstrap ) VALUES
(1, 'PIX', 'bi-qr-code'),
(2, 'Boleto Bancário', 'bi-barcode'),
(3, 'Cartão de Crédito', 'bi-credit-card'),
(4, 'Transferência Bancária', 'bi-bank');

-- Tabela principal de cobranças
CREATE TABLE  cobrancas  (
   id  int(11) NOT NULL,
   id_empresa  int(11) NOT NULL,
   data_competencia  date NOT NULL,
   data_vencimento  date NOT NULL,
   valor  decimal(10,2) NOT NULL,
   id_forma_pagamento  int(11) NOT NULL,
   contexto_pagamento  text DEFAULT NULL COMMENT 'Chave PIX, link de pagamento, código de barras, etc.',
   descricao  text DEFAULT NULL,
   status_pagamento  enum('Pendente','Pago','Vencido') NOT NULL DEFAULT 'Pendente',
   data_criacao  timestamp NOT NULL DEFAULT current_timestamp(),
   data_pagamento  date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Adicionar Índices
ALTER TABLE  formas_pagamento 
  ADD PRIMARY KEY ( id ),
  ADD UNIQUE KEY  nome  ( nome );

ALTER TABLE  cobrancas 
  ADD PRIMARY KEY ( id ),
  ADD KEY  id_empresa  ( id_empresa ),
  ADD KEY  id_forma_pagamento  ( id_forma_pagamento );

-- Adicionar o AUTO_INCREMENT para as novas tabelas
ALTER TABLE  formas_pagamento 
  MODIFY  id  int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE  cobrancas 
  MODIFY  id  int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

-- Adicionar as chaves estrangeiras
ALTER TABLE  cobrancas 
  ADD CONSTRAINT  fk_cobrancas_empresa  FOREIGN KEY ( id_empresa ) REFERENCES  empresas  ( id ) ON DELETE CASCADE,
  ADD CONSTRAINT  fk_cobrancas_forma_pagamento  FOREIGN KEY ( id_forma_pagamento ) REFERENCES  formas_pagamento  ( id ) ON DELETE RESTRICT;

COMMIT;
