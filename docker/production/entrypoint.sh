#!/usr/bin/env bash
set -e

echo "==> Production entrypoint starting"

# --------------------------------------------------
# Critical Dependency Checks
# --------------------------------------------------
echo "==> Verifying dependencies"

# Verify Node.js is available
if ! command -v node &> /dev/null; then
    echo "ERROR: Node.js is not installed!"
    exit 1
fi

# Verify inotify extension is loaded
if ! php -m | grep -q inotify; then
    echo "ERROR: inotify PHP extension is not loaded!"

    # Attempt to manually enable as fallback
    PHP_EXT_DIR=$(php -r "echo ini_get('extension_dir');")
    if [ -f "$PHP_EXT_DIR/inotify.so" ]; then
        echo "Trying to manually enable inotify..."
        echo "extension=$PHP_EXT_DIR/inotify.so" > /usr/local/etc/php/conf.d/inotify.ini

        if php -m | grep -q inotify; then
            echo "Successfully enabled inotify extension"
        else
            echo "Failed to enable inotify extension"
            exit 1
        fi
    else
        echo "inotify.so not found in $PHP_EXT_DIR"
        exit 1
    fi
fi

# --------------------------------------------------
# Laravel Application Setup
# --------------------------------------------------
echo "==> Configuring Laravel application"

# Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache}
chmod -R 775 storage

# Generate application key if needed
if [ -z "$(grep '^APP_KEY=' .env)" ]; then
    echo "==> Generating application key"
    php artisan key:generate --ansi
fi

# Run migrations
if [ "${SKIP_MIGRATIONS,,}" != "true" ]; then
    echo "==> Running migrations"
    php artisan migrate --force
else
    echo "==> SKIP_MIGRATIONS=true – skipping migrations"
fi

# Create storage symlink
if [ ! -e public/storage ]; then
    echo "==> Creating storage symlink"
    php artisan storage:link
fi

# Clear cached config
php artisan config:clear

# --------------------------------------------------
# Final Launch
# --------------------------------------------------
echo "==> Production boot complete – launching Apache"
exec "$@"
