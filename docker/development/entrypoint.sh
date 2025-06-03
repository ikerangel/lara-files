#!/usr/bin/env bash
set -e

echo "==> Development entrypoint starting"

# 1. Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache}
chmod -R 775 storage

# 2. Install dependencies if needed
if [ ! -f vendor/autoload.php ]; then
  echo "==> vendor/ missing – running composer install"
  composer install --optimize-autoloader
fi

# 3. Run migrations
if [ "${SKIP_MIGRATIONS,,}" != "true" ]; then
  echo "==> Running migrations"
  php artisan migrate --force
else
  echo "==> SKIP_MIGRATIONS=true – skipping migrations"
fi

# 4. Create storage symlink
if [ ! -e public/storage ]; then
  echo "==> Creating storage symlink"
  php artisan storage:link
fi

# 5. Clear cached config
php artisan config:clear

echo "==> Development boot complete – launching Apache"
exec "$@"
