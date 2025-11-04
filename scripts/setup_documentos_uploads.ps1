# PowerShell script to create uploads folder for Documentos and set permissões
$base = Join-Path $PSScriptRoot '..\uploads\documentos'
if (-Not (Test-Path $base)) {
    New-Item -ItemType Directory -Path $base -Force | Out-Null
    Write-Host "Criada pasta: $base"
} else {
    Write-Host "Pasta já existe: $base"
}

# Create sample subfolder for folder id 1 (opcional)
$sub = Join-Path $base '1'
if (-Not (Test-Path $sub)) {
    New-Item -ItemType Directory -Path $sub -Force | Out-Null
    Write-Host "Criada subpasta: $sub"
}

# Set permissions: grant Modify to IIS_IUSRS and Users (adapt as necessário)
try {
    icacls $base /grant "IIS_IUSRS:(OI)(CI)(M)" /T | Out-Null
    icacls $base /grant "Users:(OI)(CI)(M)" /T | Out-Null
    Write-Host "Permissões atualizadas para $base"
} catch {
    Write-Warning "Não foi possível aplicar permissões automaticamente. Execute o PowerShell como Administrador ou ajuste manualmente." 
}
