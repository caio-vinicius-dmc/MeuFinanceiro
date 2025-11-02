-- Etapa 2: Adicionar a funcionalidade de Tipos de Cobrança

-- Tabela para gerenciar os tipos de cobrança
CREATE TABLE `tipos_cobranca` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Adicionar Índices e AUTO_INCREMENT para a nova tabela
ALTER TABLE `tipos_cobranca`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

ALTER TABLE `tipos_cobranca`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

-- Adicionar alguns tipos de cobrança padrão
INSERT INTO `tipos_cobranca` (`nome`) VALUES
('Honorários Contábeis'),
('Serviços de Despachante'),
('Impostos'),
('Consultoria');

-- Modificar a tabela de cobranças para incluir a nova chave estrangeira
ALTER TABLE `cobrancas`
  ADD COLUMN `id_tipo_cobranca` INT(11) NULL AFTER `id_empresa`;

-- Adicionar a chave estrangeira
ALTER TABLE `cobrancas`
  ADD CONSTRAINT `fk_cobrancas_tipo`
  FOREIGN KEY (`id_tipo_cobranca`)
  REFERENCES `tipos_cobranca`(`id`)
  ON DELETE SET NULL;

COMMIT;
