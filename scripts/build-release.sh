#!/usr/bin/env bash
# Builds a ready-to-upload zip (vendor + compiled CSS/JS included).
set -euo pipefail
cd "$(dirname "$0")/.."
composer install --no-dev --optimize-autoloader --no-interaction
if [ -f package-lock.json ]; then npm ci; else npm install --no-audit --no-fund; fi
npm run build
mkdir -p release
zip_file="release/unlockhub-$(date +%Y%m%d-%H%M).zip"
zip -qr "$zip_file" . \
  -x '.git/*' 'node_modules/*' 'tests/*' 'release/*' '.env' 'storage/installed' 'storage/logs/*.log' \
     'storage/framework/sessions/*' 'storage/framework/views/*.php' 'storage/framework/cache/data/*' \
     'bootstrap/cache/*.php' 'public/storage' 'public/hot' '.phpunit.cache/*' '.github/*' 'phpunit.xml'
composer install --no-interaction >/dev/null
echo "Done: $zip_file"
