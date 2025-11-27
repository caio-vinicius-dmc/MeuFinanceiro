-- migrations/0006_ensure_permissions.sql
-- Garante que permissões referenciadas no código existam e sejam ligadas ao papel 'admin'

START TRANSACTION;

-- Lista de permissões a garantir
INSERT INTO permissions (slug, name, description, created_at, updated_at)
SELECT 'visualizar_documentos', 'Visualizar documentos', 'Permite visualizar documentos e servir downloads', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'visualizar_documentos');

INSERT INTO permissions (slug, name, description, created_at, updated_at)
SELECT 'gerenciar_papeis', 'Gerenciar papéis', 'Permite criar/editar/excluir papéis e permissões', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'gerenciar_papeis');

INSERT INTO permissions (slug, name, description, created_at, updated_at)
SELECT 'gerenciar_documentos', 'Gerenciar documentos', 'Permite criar/editar/excluir e associar documentos', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'gerenciar_documentos');

INSERT INTO permissions (slug, name, description, created_at, updated_at)
SELECT 'gerenciar_empresas', 'Gerenciar empresas', 'Permite criar/editar/excluir empresas', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'gerenciar_empresas');

INSERT INTO permissions (slug, name, description, created_at, updated_at)
SELECT 'acessar_lancamentos', 'Acessar lançamentos', 'Permite visualizar e exportar lançamentos', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'acessar_lancamentos');

INSERT INTO permissions (slug, name, description, created_at, updated_at)
SELECT 'acessar_cobrancas', 'Acessar cobranças', 'Permite visualizar e exportar cobranças', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'acessar_cobrancas');

INSERT INTO permissions (slug, name, description, created_at, updated_at)
SELECT 'acessar_configuracoes', 'Acessar configurações', 'Permite visualizar páginas de configuração do sistema', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'acessar_configuracoes');

INSERT INTO permissions (slug, name, description, created_at, updated_at)
SELECT 'acessar_logs', 'Acessar logs', 'Permite visualizar logs do sistema', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'acessar_logs');

INSERT INTO permissions (slug, name, description, created_at, updated_at)
SELECT 'gerenciar_usuarios', 'Gerenciar usuários', 'Permite criar/editar/excluir usuários', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'gerenciar_usuarios');

INSERT INTO permissions (slug, name, description, created_at, updated_at)
SELECT 'acessar_associacoes_contador', 'Acessar associações de contador', 'Permite gerenciar associações entre contadores e clientes', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'acessar_associacoes_contador');

-- Associar todas as permissões acima ao papel 'admin' caso ainda não estejam associadas
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug IN (
  'visualizar_documentos','gerenciar_papeis','gerenciar_documentos','gerenciar_empresas','acessar_lancamentos','acessar_cobrancas','acessar_configuracoes','acessar_logs','gerenciar_usuarios','acessar_associacoes_contador'
)
WHERE r.slug = 'admin'
AND NOT EXISTS (
  SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
);

COMMIT;
