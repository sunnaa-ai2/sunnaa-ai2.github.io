#!/usr/bin/env bash
# Installs or updates the API on alwaysdata. Safe to run again after `git pull`.
#   bash ~/site/backend/deploy/alwaysdata.sh
set -euo pipefail
cd "$(dirname "$0")/.."

echo "== 1/4 Packages"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "== 2/4 Settings"
if [ ! -f .env ]; then cp .env.example .env; echo "   .env created"; fi
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
php artisan config:clear >/dev/null

tries=0
until php artisan db:show >/dev/null 2>&1; do
  tries=$((tries + 1))
  if [ "$tries" -gt 3 ]; then
    echo "   Cannot connect to MySQL. Details:"; php artisan db:show || true; exit 1
  fi
  read -rsp "   MySQL password for sunnaa_ (typing stays hidden): " DBP; echo
  sed -i '/^DB_PASSWORD=/d' .env
  printf "DB_PASSWORD='%s'\n" "$DBP" >> .env
  unset DBP
  php artisan config:clear >/dev/null
done
echo "   MySQL connection OK"

echo "== 3/4 Database tables"
php artisan migrate --force

echo "== 4/4 Cache"
php artisan config:cache

echo
echo "Done. In Web > Sites, the root directory must be: $(pwd)/public"
