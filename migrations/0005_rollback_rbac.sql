-- migrations/0005_rollback_rbac.sql
-- Remove permissões e mapeamentos adicionados pelas migrations RBAC anteriores (0003 e 0004)

-- Lista de slugs adicionados (não usamos variável para compatibilidade com simples runners)

-- Remove entries from role_permissions that reference these permissions
DELETE rp FROM role_permissions rp
JOIN permissions p ON rp.permission_id = p.id
WHERE p.slug IN ('acessar_associacoes_contador','acessar_logs','acessar_lancamentos','gerenciar_empresas','gerenciar_documentos');

-- Remove the permissions themselves
DELETE FROM permissions WHERE slug IN ('acessar_associacoes_contador','acessar_logs','acessar_lancamentos','gerenciar_empresas','gerenciar_documentos');

-- Nota: Este rollback não remove roles nem user_roles; ele remove apenas as permissões e os mapeamentos role_permissions criados pelas migrations.
-- migrations/0005_rollback_rbac.sql
-- Remove as permissões adicionadas pelas migrations RBAC recentes (0003/0004).
-- ATENÇÃO: execute somente após backup completo do banco.

START TRANSACTION;

DELETE rp FROM role_permissions rp
JOIN permissions p ON p.id = rp.permission_id
WHERE p.slug IN ('acessar_associacoes_contador','acessar_logs','gerenciar_empresas','gerenciar_documentos');

DELETE FROM permissions WHERE slug IN ('acessar_associacoes_contador','acessar_logs','gerenciar_empresas','gerenciar_documentos');

COMMIT;
