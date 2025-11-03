param(
    [string]$DbHost = "127.0.0.1",
    [int]$DbPort = 3306,
    [string]$DbUser = "root",
    [string]$DbName = "gestao_financeira",
    [string]$MigrationFile = ".\migrations\02_add_id_forma_pagamento_to_lancamentos.sql",
    [string]$BackupPath = ".\backups\lancamentos_backup_pre_add_forma.sql"
)

# Local do executáveis do XAMPP
$mysqldumpExe = "C:\\xampp\\mysql\\bin\\mysqldump.exe"
$mysqlExe = "C:\\xampp\\mysql\\bin\\mysql.exe"

if (-not (Test-Path $MigrationFile)) {
    Write-Error "Migration file not found: $MigrationFile"
    exit 1
}

if (-not (Test-Path $mysqldumpExe)) {
    Write-Error "mysqldump not found at $mysqldumpExe. Ajuste o caminho no script e rode novamente."
    exit 1
}
if (-not (Test-Path $mysqlExe)) {
    Write-Error "mysql client not found at $mysqlExe. Ajuste o caminho no script e rode novamente."
    exit 1
}

Write-Host "Criando diretório de backup se necessário..."
$backupDir = Split-Path -Path $BackupPath -Parent
if (-not (Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir -Force | Out-Null }

Write-Host "Gerando backup da tabela 'lancamentos' em: $BackupPath"
& $mysqldumpExe -h $DbHost -P $DbPort -u $DbUser $DbName lancamentos | Out-File -Encoding UTF8 $BackupPath
if ($LASTEXITCODE -ne 0) { Write-Error "Erro ao gerar backup (exit=$LASTEXITCODE)"; exit 1 }
Write-Host "Backup criado com sucesso."

Write-Host "Aplicando migration: $MigrationFile"
# Aplica migration
& $mysqlExe -h $DbHost -P $DbPort -u $DbUser $DbName < $MigrationFile
if ($LASTEXITCODE -ne 0) { Write-Error "Erro ao aplicar migration (exit=$LASTEXITCODE)"; exit 1 }
Write-Host "Migration aplicada com sucesso."

Write-Host "Resumo: backup em $BackupPath, migration aplicada: $MigrationFile"
