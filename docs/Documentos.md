# Módulo Documentos

Este documento descreve a implementação e uso do módulo Documentos.

## Visão geral
- Admin cria pastas raiz e pode associar múltiplos usuários a cada pasta (cliente, contador, etc).
- Usuários associados podem acessar a pasta e enviar arquivos. Os uploads ficam com status `pending` até que um administrador aprove.
- Admin pode aprovar/reprovar arquivos, e pode excluir arquivos.
- Downloads são servidos por um endpoint seguro (`process/serve_documento.php`) que valida permissões.
- É possível gerar tokens temporários para downloads com `process/generate_download_token.php?id=<arquivo_id>&ttl=<minutos>`.

## Tabelas criadas (migração 04)
- `documentos_pastas` — pastas (id, nome, parent_id, owner_user_id, timestamps)
- `documentos_arquivos` — arquivos (id, pasta_id, nome_original, caminho, tamanho, tipo_mime, enviado_por_user_id, status, aprovado_por, timestamps)
- `documentos_pastas_usuarios` — pivot many-to-many (pasta_id, user_id, papel, criado_em)
- `documentos_download_tokens` — tokens temporários para download (token, arquivo_id, criado_por, expires_at, criado_em)

## Como aplicar a migration (local)
1. Faça backup do banco de dados.
2. Pelo terminal no servidor web execute:

```powershell
# Em Windows, usando PHP CLI
cd C:\xampp\htdocs\DMC-Finanças\scripts
php apply_migration_04.php
```

O script `scripts/apply_migration_04.php` executa o SQL contido em `migrations/04_create_documentos_tables.sql`.

## Permissões e uploads
- Diretório de uploads: `uploads/documentos/<pasta_id>/`.
- Política de upload centralizada em `config/functions.php` através de `getDocumentUploadPolicy()`.
  - Padrão: MIME PDF, imagens, DOC/DOCX, XLS/XLSX e tamanho máximo 10 MB.
  - Você pode sobrescrever definindo `system_settings` chaves: `documents_max_size_bytes` e `documents_allowed_mimes` (lista CSV).

## Endpoints principais
- `pages/gerenciar_documentos.php` — página administrativa para criar pastas, associar usuários e aprovar/reprovar arquivos.
- `pages/documentos.php` — página para usuários navegarem e enviarem arquivos para pastas às quais estão associados.
- `process/documentos_handler.php` — handler que processa ações (criar pasta, upload, aprovar, excluir, associar usuários).
- `process/serve_documento.php` — serve arquivos com checagem de permissão. Aceita `?id=` (arquivo id) ou `?token=` (token temporário).
- `process/generate_download_token.php?id=<arquivo_id>&ttl=<minutos>` — gera token temporário e retorna URL JSON.

## Notificações por e-mail
- O módulo tenta notificar administradores e autores quando apropriado.
- Para envio real, configure SMTP em `system_settings` (ou ajuste `getSmtpSettings()`), instale PHPMailer com Composer:

```powershell
cd C:\xampp\htdocs\DMC-Finanças
composer require phpmailer/phpmailer
```

## Testes rápidos
- Execute `tools/test_documentos_flow.php` em seu browser: `http://localhost/DMC-Finanças/tools/test_documentos_flow.php` para checar tabelas e diretório de uploads.

## Segurança
- Arquivos não aprovados só podem ser vistos por admin, autor do upload ou usuários associados à pasta.
- Tokens temporários têm expiração e, por padrão, são consumidos (deletados) no uso.

## Próximos passos sugeridos
- Implementar versionamento de arquivos (opcional).
- Ajustar políticas por pasta (limites distintos por pasta) se necessário.
- Integrar logs/alertas mais avançados ou workflow de comentários na rejeição.

---
Se quiser que eu aplique algo mais (ex.: implementar versionamento ou tokens com rotas públicas expiradas), diga qual item prefere que eu implemente a seguir.