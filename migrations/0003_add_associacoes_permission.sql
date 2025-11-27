-- migrations/0003_add_associacoes_permission.sql
-- Adiciona a permissão para acessar as solicitações de associação de contadores

INSERT INTO permissions (`slug`, `name`, `description`, `created_at`, `updated_at`)
SELECT 'acessar_associacoes_contador', 'Acessar associações de contador', 'Permite ver e gerenciar solicitações de associação contador-cliente', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'acessar_associacoes_contador');

-- Associa a permissão ao papel 'admin' (se existir)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.slug = 'acessar_associacoes_contador'
WHERE r.slug = 'admin'
AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp
    WHERE rp.role_id = r.id AND rp.permission_id = p.id
);
