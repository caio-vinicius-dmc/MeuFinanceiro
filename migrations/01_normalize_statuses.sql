-- migrations/01_normalize_statuses.sql
-- Normaliza o campo status em lançamentos para 'pendente' quando NULL ou vazio

UPDATE lancamentos
SET status = 'pendente'
WHERE status IS NULL OR TRIM(status) = '';

-- Opcional: normalizar casos diferentes (ex.: 'Em aberto' -> 'pendente')
UPDATE lancamentos
SET status = 'pendente'
WHERE LOWER(TRIM(status)) IN ('em aberto', 'aberto');

-- Centralize em um backup antes de rodar:
-- mysqldump -u root -p yourdb lancamentos > lancamentos_backup.sql
