-- migrations/0002_seed_rbac.sql
-- Popula permissões e papéis iniciais
START TRANSACTION;

-- Permissões básicas (slug)
INSERT IGNORE INTO permissions (name, slug, description) VALUES
('Acessar Dashboard', 'acessar_dashboard', 'Permite visualizar o dashboard'),
('Acessar Lançamentos', 'acessar_lancamentos', 'Acessar tela de lançamentos'),
('Criar Lançamento', 'criar_lancamento', 'Criar lançamentos'),
('Editar Lançamento', 'editar_lancamento', 'Editar lançamentos'),
('Excluir Lançamento', 'excluir_lancamento', 'Excluir lançamentos'),
('Acessar Cobranças', 'acessar_cobrancas', 'Acessar tela de cobranças'),
('Acessar Configurações', 'acessar_configuracoes', 'Acessar configurações do sistema'),
('Gerenciar Usuários', 'gerenciar_usuarios', 'Criar/Editar/Remover usuários'),
('Gerenciar Papéis', 'gerenciar_papeis', 'Criar/Editar/Remover papéis e permissões'),
('Gerar Relatórios', 'gerar_relatorios', 'Acessar relatórios'),
('Visualizar Documentos', 'visualizar_documentos', 'Acessar documentos e downloads');

-- Papéis iniciais
INSERT IGNORE INTO roles (name, slug, description) VALUES
('Super Admin', 'super_admin', 'Acesso total ao sistema'),
('Admin', 'admin', 'Administrador do sistema'),
('Contador', 'contador', 'Contador com funcionalidades financeiras'),
('Cliente', 'cliente', 'Cliente com acesso limitado');

-- Vincula permissões a papéis (exemplos)
-- Super Admin recebe todas as permissões
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'super_admin';

-- Admin: maioria das permissões exceto gerenciar_papeis
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('acessar_dashboard','acessar_lancamentos','criar_lancamento','editar_lancamento','excluir_lancamento','acessar_cobrancas','acessar_configuracoes','gerenciar_usuarios','gerar_relatorios','visualizar_documentos') WHERE r.slug = 'admin';

-- Contador: foco em lançamentos e cobranças
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('acessar_dashboard','acessar_lancamentos','criar_lancamento','editar_lancamento','acessar_cobrancas','gerar_relatorios') WHERE r.slug = 'contador';

-- Cliente: acesso limitado
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.slug IN ('acessar_dashboard','acessar_lancamentos','visualizar_documentos') WHERE r.slug = 'cliente';

COMMIT;
