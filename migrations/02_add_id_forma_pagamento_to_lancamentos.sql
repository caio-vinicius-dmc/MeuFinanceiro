-- migrations/02_add_id_forma_pagamento_to_lancamentos.sql
-- Adiciona a coluna id_forma_pagamento em lancamentos (nullable), cria índice
-- e faz backfill tentando mapear o valor textual em metodo_pagamento para formas_pagamento.id
-- NOTA: Este script é seguro para rodar localmente após criar backup.

START TRANSACTION;

-- 1) Adiciona coluna (nullable para evitar falha em registros existentes)
ALTER TABLE lancamentos
    ADD COLUMN id_forma_pagamento INT NULL;

-- 2) Tenta mapear o texto em metodo_pagamento para o id da forma de pagamento
-- Usa comparação case-insensitive e trim
UPDATE lancamentos l
LEFT JOIN formas_pagamento fp
    ON LOWER(TRIM(fp.nome)) = LOWER(TRIM(COALESCE(l.metodo_pagamento, '')))
SET l.id_forma_pagamento = fp.id
WHERE l.id_forma_pagamento IS NULL AND COALESCE(l.metodo_pagamento, '') <> '';

-- 3) Cria índice para acelerar joins/ buscas
CREATE INDEX idx_lancamentos_id_forma_pagamento ON lancamentos (id_forma_pagamento);

-- 4) (Opcional) Se desejar, pode-se criar FK. Comentado por padrão para evitar erros em ambientes com dados inconsistentes.
-- ALTER TABLE lancamentos ADD CONSTRAINT fk_lancamentos_forma_pagamento FOREIGN KEY (id_forma_pagamento) REFERENCES formas_pagamento(id) ON DELETE SET NULL ON UPDATE CASCADE;

COMMIT;

-- FIM
