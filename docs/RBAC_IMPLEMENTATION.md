RBAC Implementation (Resumo)
============================

O que foi adicionado:

- `migrations/0001_create_rbac_tables.sql` : cria tabelas `roles`, `permissions`, `role_permissions`, `user_roles`.
- `migrations/0002_seed_rbac.sql` : insere permissões e papéis iniciais (super_admin, admin, contador, cliente).
- `migrations/apply_migrations.php` : script PHP para executar os arquivos .sql da pasta `migrations`.
- `lib/rbac.php` : biblioteca simples com funções para carregar roles/perms na sessão e helpers básicos (assign/revoke, require_permission, current_user_has_permission).
- Alterado `config/functions.php` para carregar `lib/rbac.php` automaticamente.
- Alterado `process/auth.php` para chamar `rbac_load_user_into_session()` ao logar.

Como aplicar (local):

1. Faça backup do banco de dados.
2. No terminal (dentro do projeto), rode:

```bash
php migrations/apply_migrations.php
```

3. Verifique se as tabelas e seeds foram criados corretamente. Se houver erro, o script exibe a falha e não aplica parcialmente (usa transações).

Como usar no código:

- `current_user_has_permission('acessar_dashboard')` — retorna true/false.
- `require_permission('gerenciar_usuarios')` — interrompe com 403 se o usuário não tiver permissão.
- `rbac_assign_role($userId, $roleId)` / `rbac_revoke_role($userId, $roleId)` — gerenciam atribuições por código.

Próximos passos recomendados (PRs pequenos):

1. Implementar UI administrativa para gerenciar papéis e permissões (`pages/gerenciar_papeis.php`, `pages/gerenciar_permissoes.php`).
2. Criar script para mapear `tipo` atual do usuário em roles e aplicar a todos os usuários.
3. Substituir checagens pontuais (`isAdmin()`, `hasLancamentosAccess()`) por `require_permission()` nas páginas críticas.
4. Adicionar testes manuais e instruções de rollback.

Observações:
- Este é um RBAC inicial e simples; podemos evoluir para caching com APCu, interfaces mais completas e suporte a permissões por recurso/ID.
