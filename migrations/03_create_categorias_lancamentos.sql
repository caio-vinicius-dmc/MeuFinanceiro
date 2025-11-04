-- migrations/03_create_categorias_lancamentos.sql
-- Cria tabela de categorias de lançamentos e adiciona coluna em lancamentos

START TRANSACTION;

-- Tabela de categorias de lançamento
CREATE TABLE IF NOT EXISTS categorias_lancamento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    descricao TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Adiciona coluna em lancamentos (nullable) para compatibilidade retroativa
ALTER TABLE IF EXISTS lancamentos
    ADD COLUMN IF NOT EXISTS id_categoria INT UNSIGNED NULL;

-- Índice e FK (FK comentada por padrão para evitar bloqueios em ambientes com dados legados)
CREATE INDEX idx_lancamentos_id_categoria ON lancamentos (id_categoria);

-- Uncomment the following line to enable FK in controlled migration step
-- ALTER TABLE lancamentos ADD CONSTRAINT fk_lancamentos_categoria FOREIGN KEY (id_categoria) REFERENCES categorias_lancamento(id) ON DELETE SET NULL ON UPDATE CASCADE;

COMMIT;
