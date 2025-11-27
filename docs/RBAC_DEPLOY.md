RBAC Deploy & Rollback
=====================

Resumo rápido
- Antes de aplicar mudanças em produção faça backup completo do banco de dados.
- As migrations RBAC adicionam permissões e mapeamentos (migrations/0001..0004). Se precisar reverter, use migrations/0005_rollback_rbac.sql ou o script `scripts/rollback_rbac.php`.

Passos recomendados para deploy em staging/prod:

1) Backup
   - Faça dump do banco: `mysqldump -u root -p gestao_financeira > backup_before_rbac.sql`
2) Aplicar migrations (staging primeiro)
   - Local/servidor: execute os scripts criados (ex: `php scripts/apply_migration_0003.php` e `php scripts/apply_migration_0004.php`).
   - Verifique que as permissões foram criadas: `SELECT slug, name FROM permissions WHERE slug LIKE 'acessar_%' OR slug LIKE 'gerenciar_%';`
3) Mapear usuários legados
   - Se você já tem o script de migração de usuários legados (`scripts/migrate_legacy_roles.php`), execute-o em staging e verifique `user_roles`.
4) Testes manuais
   - Teste logins com usuários admin/contador/cliente e verifique páginas protegidas (dashboard, lançamentos, documentos, empresas, configurações).
   - Teste ações AJAX (criar/editar/associar) com usuários que receberam permissões e com usuários sem permissão para confirmar 403/erros.
5) Deploy em produção
   - Repita passos 1-4 em produção. Monitore logs e erros por 24h.

Rollback
- Se necessário reverter as permissões adicionadas, execute:

  php scripts/rollback_rbac.php

- OU execute o SQL:

  mysql -u root -p gestao_financeira < migrations/0005_rollback_rbac.sql

Notas
- O rollback remove apenas as permissões e mapeamentos criados nas migrations 0003 e 0004. Não restaura dados de usuários nem alterações manuais feitas via UI após o deploy.
- Sempre verifique dependências: se alguma lógica do código já começou a depender das permissões, remova ou ajuste antes de reverter em produção.

Atualização (migration 0006)
-----------------------------
- Foi adicionada a `migrations/0006_ensure_permissions.sql` que garante a existência de permissões referenciadas pelo código (`visualizar_documentos`, `gerenciar_papeis`, `gerenciar_documentos`, `gerenciar_empresas`, `acessar_lancamentos`, `acessar_cobrancas`, `acessar_configuracoes`, `acessar_logs`, `gerenciar_usuarios`, `acessar_associacoes_contador`).
- Recomenda-se executar `php scripts/apply_migration_0006.php` em staging antes de produção para inserir qualquer permissão faltante e associá-las ao papel `admin`.
- O script também exibe um resumo das permissões criadas/garantidas.

