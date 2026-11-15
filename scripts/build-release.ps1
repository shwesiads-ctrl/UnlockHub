# Builds a ready-to-upload zip (vendor + compiled CSS/JS included) for XAMPP or cPanel.
# Run in the project folder:  powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

Write-Host '1/4 Composer (production packages)...' -ForegroundColor Cyan
composer install --no-dev --optimize-autoloader --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }

Write-Host '2/4 Building CSS/JS...' -ForegroundColor Cyan
npm ci
npm run build
if ($LASTEXITCODE -ne 0) { throw 'npm run build failed' }

Write-Host '3/4 Creating zip...' -ForegroundColor Cyan
$version = (Get-Date -Format 'yyyyMMdd-HHmm')
New-Item -ItemType Directory -Force -Path release | Out-Null
$zip = "release\unlockhub-$version.zip"
if (Test-Path $zip) { Remove-Item $zip }
# Windows 10+ ships bsdtar, which writes .zip with --exclude
tar -a -c -f $zip `
  --exclude=./.git --exclude=./node_modules --exclude=./tests --exclude=./release `
  --exclude=./.env --exclude=./storage/installed --exclude=./storage/logs/*.log `
  --exclude=./storage/framework/sessions/* --exclude=./storage/framework/views/*.php --exclude=./storage/framework/cache/data/* `
  --exclude=./bootstrap/cache/*.php --exclude=./public/storage --exclude=./public/hot `
  --exclude=./.phpunit.cache --exclude=./.idea --exclude=./.vscode `
  .
if ($LASTEXITCODE -ne 0) { throw 'zip failed' }

Write-Host '4/4 Restoring dev packages for this PC...' -ForegroundColor Cyan
composer install --no-interaction | Out-Null

Write-Host "Done: $zip" -ForegroundColor Green
Write-Host 'Upload it, extract, then open https://your-site/ (the installer starts by itself).'
