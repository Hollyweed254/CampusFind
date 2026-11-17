# CampusFind local setup for XAMPP (Windows). Run from the project folder.
$ErrorActionPreference = "Stop"
if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole("Administrator")) {
    Start-Process powershell "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`"" -Verb RunAs; exit
}
$xampp = "C:\xampp"
if (-not (Test-Path "$xampp\htdocs")) { $xampp = Read-Host "Enter XAMPP root (e.g. D:\xampp)" }
$mysql = "$xampp\mysql\bin\mysql.exe"; $source = $PSScriptRoot; $target = "$xampp\htdocs\CampusFind"

if (Test-Path $target) { robocopy $source $target /E /XD uploads .git /NFL /NDL /NJH /NJS | Out-Null }
else { Copy-Item $source $target -Recurse -Force }
New-Item -ItemType Directory -Force "$target\uploads" | Out-Null

if (-not (Get-Process mysqld -ErrorAction SilentlyContinue)) { Start-Process "$xampp\mysql\bin\mysqld.exe" "--defaults-file=$xampp\mysql\bin\my.ini --standalone" -WindowStyle Hidden }
if (-not (Get-Process httpd -ErrorAction SilentlyContinue)) { Start-Process "$xampp\apache\bin\httpd.exe" -WindowStyle Hidden }
$ready = $false
1..15 | ForEach-Object { if (-not $ready) { & $mysql -u root -e "SELECT 1" 2>$null | Out-Null; if ($LASTEXITCODE -eq 0) { $ready = $true } else { Start-Sleep 2 } } }
if (-not $ready) { throw "MySQL did not start. Check XAMPP Control Panel / port 3306." }

$reset = Read-Host "Create/reset database 'campusfind'? This DELETES existing data (y/N)"
if ($reset -eq "y") {
    & $mysql -u root -e "CREATE DATABASE IF NOT EXISTS campusfind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    cmd /c "`"$mysql`" -u root campusfind < `"$target\database\campusfind.sql`""
    if ($LASTEXITCODE -ne 0) { throw "SQL import failed." }
}
icacls "$target\uploads" /grant "Users:(OI)(CI)M" /T /C /Q | Out-Null
Start-Process "http://localhost/CampusFind/"
Write-Host "URL: http://localhost/CampusFind/  |  Admin: admin@campusfind.local / admin123  |  Check: /test-connection.php" -ForegroundColor Yellow
Read-Host "Press Enter to exit"
