#!/usr/bin/env bash
set -e

echo "==> Entrypoint starting"

# 1️ Install Composer if vendor/ missing
if [ ! -f vendor/autoload.php ]; then
  echo "==> vendor/ missing – You must run composer install ("./composer-install.sh")"
fi

# 2 Ensure APP_KEY
if grep -q '^APP_KEY=$' .env 2>/dev/null || [ -z "$APP_KEY" ]; then
  echo "==> You must generate a new APP_KEY, run: php artisan key:generate --ansi"
fi

# 3 Run migrations (unless disabled)
if [ "${SKIP_MIGRATIONS,,}" != "true" ]; then
  echo "==> Running migrations"
  php artisan migrate --force || true
else
  echo "==> SKIP_MIGRATIONS=true – skipping migrate"
fi


# 4 storage:link
if [ ! -e public/storage ]; then
  echo "==> Creating storage symlink"
  php artisan storage:link || true
fi

echo "==> Boot OK – launching Apache"
exec "$@"
