#!/usr/bin/env bash
set -e

echo "==> Production entrypoint starting"

#  Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache}
chmod -R 775 storage

# Generate application key if needed
if [ -z "$(grep '^APP_KEY=' .env)" ]; then
    echo "==> Generating application key"
    php artisan key:generate --ansi
fi

#  Run migrations
if [ "${SKIP_MIGRATIONS,,}" != "true" ]; then
  echo "==> Running migrations"
  php artisan migrate --force
else
  echo "==> SKIP_MIGRATIONS=true – skipping migrations"
fi

#  Create storage symlink
if [ ! -e public/storage ]; then
  echo "==> Creating storage symlink"
  php artisan storage:link
fi

# Clear cached config
php artisan config:clear

echo "==> Production boot complete – launching Apache"
exec "$@"
