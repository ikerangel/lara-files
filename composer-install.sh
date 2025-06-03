#!/bin/bash
set -euo pipefail

# ======================
# CONFIGURATION
# ======================
APP_DIR="/var/www/html/app3"
CONTAINER_APP_DIR="/app"
USER="isl"
GROUP="www-data"
COMPOSER_VERSION="2.7"

# ======================
# PERMISSION CHECK
# ======================
if [ "$EUID" -ne 0 ]; then
  echo "❗ This script must be run as root to fix permissions properly"
  echo "❗ Please run with: sudo $0"
  exit 1
fi

# ======================
# COMPOSER INSTALL
# ======================
echo "🚀 Starting Composer installation..."
docker run -it --rm \
  -v "${APP_DIR}:${CONTAINER_APP_DIR}" \
  -v "${HOME}/.composer/cache:/tmp" \
  -w "${CONTAINER_APP_DIR}" \
  "composer:${COMPOSER_VERSION}" \
  sh -c "composer install --optimize-autoloader --no-dev --no-progress"

# ======================
# PERMISSION FIX
# ======================
echo "🔧 Fixing permissions for ${APP_DIR}/vendor..."
chown -R "${USER}:${GROUP}" "${APP_DIR}/vendor"
chmod -R 775 "${APP_DIR}/vendor"

echo "✅ All done! Vendor directory permissions:"
ls -ld "${APP_DIR}/vendor"
ls -l "${APP_DIR}/vendor" | head -n 5

exit 0
