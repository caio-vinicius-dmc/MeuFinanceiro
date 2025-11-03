<#
apply_migration.ps1

Script interativo para criar backup da tabela `lancamentos` e (opcionalmente) aplicar a migration
migrations/01_normalize_statuses.sql.

Instruções:
- Execute este script em PowerShell na raiz do projeto (onde está a pasta `migrations`).
- Será solicitado o nome do banco, usuário e senha. O script criará um dump apenas da tabela
  `lancamentos` e salvará em `./backups/lancamentos_backup.sql` por padrão.
- Após o backup, você pode escolher aplicar a migration automaticamente.

Segurança: este script solicita a senha e a converte para uso nas ferramentas `mysqldump`/`mysql`.
Use em ambiente local. Faça backup dos arquivos gerados e valide antes de aplicar em produção.
#>

param(
    [string]$DbHost = "127.0.0.1",
    [int]$DbPort = 3306,
    [string]$DbUser = "root",
    [string]$DbName = "",
    [string]$BackupPath = ".\backups\lancamentos_backup.sql",
    [string]$MigrationFile = ".\migrations\01_normalize_statuses.sql"
)

if (-not $DbName) {
    $DbName = Read-Host "Nome do banco de dados (ex: seu_db)"
}

# Ler senha em modo seguro
$securePwd = Read-Host -AsSecureString "Senha do MySQL (pressione Enter se vazia)"
$ptr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePwd)
$plainPwd = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($ptr)
[System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)

# Criar diretório de backup se necessário
$backupDir = Split-Path -Path $BackupPath -Parent
if (-not (Test-Path $backupDir)) {
    Write-Host "Criando diretório de backup: $backupDir"
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
}

# Comandos
$mysqldumpCmd = "mysqldump -h $DbHost -P $DbPort -u $DbUser"
$mysqlCmd = "mysql -h $DbHost -P $DbPort -u $DbUser"

# Constrói e executa o mysqldump
if ($plainPwd -ne "") {
    $dumpFull = "$mysqldumpCmd -p`"$plainPwd`" $DbName lancamentos > `"$BackupPath`""
} else {
    $dumpFull = "$mysqldumpCmd $DbName lancamentos > `"$BackupPath`""
}

Write-Host "Criando backup da tabela 'lancamentos' em: $BackupPath"
Write-Host "Executando: $dumpFull"

$dumpResult = Invoke-Expression $dumpFull 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Error "Erro ao executar mysqldump. Saída: $dumpResult"
    exit 1
}

Write-Host "Backup realizado com sucesso."

# Pergunta para aplicar a migration
$apply = Read-Host "Deseja aplicar a migration agora? (s/n)"
if ($apply -match '^[sS]') {
    if (-not (Test-Path $MigrationFile)) {
        Write-Error "Arquivo de migration não encontrado em: $MigrationFile"
        exit 1
    }

    if ($plainPwd -ne "") {
        $applyFull = "$mysqlCmd -p`"$plainPwd`" $DbName < `"$MigrationFile`""
    } else {
        $applyFull = "$mysqlCmd $DbName < `"$MigrationFile`""
    }

    Write-Host "Aplicando migration: $MigrationFile"
    Write-Host "Executando: $applyFull"

    $applyResult = Invoke-Expression $applyFull 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Erro ao aplicar a migration. Saída: $applyResult"
        exit 1
    }

    Write-Host "Migration aplicada com sucesso."
} else {
    Write-Host "Nenhuma alteração aplicada. Para aplicar manualmente execute:" 
    Write-Host "mysql -h $DbHost -P $DbPort -u $DbUser -p < $MigrationFile"
}

Write-Host "Fim. Verifique o backup em: $BackupPath" | Out-Host
