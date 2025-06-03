#!/usr/bin/env bash
set -e

echo "==> Production entrypoint starting"

# 1. Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache}
chmod -R 775 storage

# 2. Run migrations
if [ "${SKIP_MIGRATIONS,,}" != "true" ]; then
  echo "==> Running migrations"
  php artisan migrate --force
else
  echo "==> SKIP_MIGRATIONS=true – skipping migrations"
fi

# 3. Create storage symlink
if [ ! -e public/storage ]; then
  echo "==> Creating storage symlink"
  php artisan storage:link
fi

# 4. Clear cached config
php artisan config:clear

echo "==> Production boot complete – launching Apache"
exec "$@"
