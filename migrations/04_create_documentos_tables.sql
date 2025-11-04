-- Migration: 04_create_documentos_tables.sql
-- Cria tabelas para o módulo Documentos

CREATE TABLE IF NOT EXISTS documentos_pastas (
  id INT NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (id),
  nome VARCHAR(255) NOT NULL,
  parent_id INT DEFAULT NULL,
  owner_user_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (parent_id),
  INDEX (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documentos_arquivos (
  id INT NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (id),
  pasta_id INT NOT NULL,
  nome_original VARCHAR(512) NOT NULL,
  caminho VARCHAR(1024) NOT NULL,
  tamanho BIGINT DEFAULT 0,
  tipo_mime VARCHAR(255) DEFAULT NULL,
  enviado_por_user_id INT DEFAULT NULL,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  aprovado_por INT DEFAULT NULL,
  aprovado_em DATETIME DEFAULT NULL,
  INDEX (pasta_id),
  INDEX (enviado_por_user_id),
  INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Observação: adicionar chaves estrangeiras pode depender do esquema e permissões do ambiente.
-- Recomenda-se aplicar FK manualmente se desejar, por exemplo:
-- ALTER TABLE documentos_pastas ADD CONSTRAINT fk_pasta_owner FOREIGN KEY (owner_user_id) REFERENCES usuarios(id) ON DELETE SET NULL;
-- ALTER TABLE documentos_pastas ADD CONSTRAINT fk_pasta_parent FOREIGN KEY (parent_id) REFERENCES documentos_pastas(id) ON DELETE CASCADE;
-- ALTER TABLE documentos_arquivos ADD CONSTRAINT fk_arquivo_pasta FOREIGN KEY (pasta_id) REFERENCES documentos_pastas(id) ON DELETE CASCADE;
-- ALTER TABLE documentos_arquivos ADD CONSTRAINT fk_arquivo_enviado_por FOREIGN KEY (enviado_por_user_id) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Tabela de associação many-to-many entre pastas e usuarios
CREATE TABLE IF NOT EXISTS documentos_pastas_usuarios (
  pasta_id INT NOT NULL,
  user_id INT NOT NULL,
  papel VARCHAR(32) DEFAULT 'usuario',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pasta_id, user_id),
  INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tokens temporários para downloads
CREATE TABLE IF NOT EXISTS documentos_download_tokens (
  token VARCHAR(128) NOT NULL PRIMARY KEY,
  arquivo_id INT NOT NULL,
  criado_por INT DEFAULT NULL,
  expires_at DATETIME NOT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (arquivo_id),
  INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


COMMIT;

