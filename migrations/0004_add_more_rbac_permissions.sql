-- migrations/0004_add_more_rbac_permissions.sql
-- Adiciona permissões usadas nas pages atualizadas e associa ao papel 'admin'

-- Lista de permissões a criar

-- Inserir permissões individualmente se não existirem
INSERT INTO permissions (`slug`, `name`, `description`, `created_at`, `updated_at`)
SELECT 'acessar_logs', 'Acessar logs do sistema', 'Permite visualizar os logs do sistema', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'acessar_logs');

INSERT INTO permissions (`slug`, `name`, `description`, `created_at`, `updated_at`)
SELECT 'acessar_lancamentos', 'Acessar lançamentos', 'Permite visualizar e filtrar lançamentos', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'acessar_lancamentos');

INSERT INTO permissions (`slug`, `name`, `description`, `created_at`, `updated_at`)
SELECT 'gerenciar_empresas', 'Gerenciar empresas', 'Permite criar/editar/excluir empresas e associar usuários', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'gerenciar_empresas');

INSERT INTO permissions (`slug`, `name`, `description`, `created_at`, `updated_at`)
SELECT 'gerenciar_documentos', 'Gerenciar documentos', 'Permite criar pastas, aprovar envios e gerenciar documentos', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'gerenciar_documentos');

-- Associar cada permissão ao papel admin quando existente e não associado
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug = 'acessar_logs'
WHERE r.slug = 'admin'
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug = 'acessar_lancamentos'
WHERE r.slug = 'admin'
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug = 'gerenciar_empresas'
WHERE r.slug = 'admin'
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug = 'gerenciar_documentos'
WHERE r.slug = 'admin'
AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);
