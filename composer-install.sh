#!/bin/bash
set -euo pipefail

# ======================
# CONFIGURATION
# ======================

# Get the script's directory (root of Laravel project)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_DIR="$SCRIPT_DIR"
CONTAINER_APP_DIR="/var/www"  # Changed to match docker-compose.yml
COMPOSER_VERSION="2.7"

# Default values (if not found in .env)
USER="isl"
GROUP="www-data"

# Read .env file if exists
if [ -f "$APP_DIR/.env" ]; then
  echo "🔍 Reading .env file..."

  # Extract USER_ACCESS_SH
  if grep -q '^USER_ACCESS_SH=' "$APP_DIR/.env"; then
    USER=$(grep '^USER_ACCESS_SH=' "$APP_DIR/.env" | cut -d '=' -f2- | sed "s/['\"]//g" | cut -d '#' -f1 | xargs)
  fi

  # Extract GROUP_ACCESS_SH
  if grep -q '^GROUP_ACCESS_SH=' "$APP_DIR/.env"; then
    GROUP=$(grep '^GROUP_ACCESS_SH=' "$APP_DIR/.env" | cut -d '=' -f2- | sed "s/['\"]//g" | cut -d '#' -f1 | xargs)
  fi
fi

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
find "${APP_DIR}/vendor" -type d -exec chmod 775 {} \;
find "${APP_DIR}/vendor" -type f -exec chmod 664 {} \;

echo "✅ All done! Vendor directory permissions:"
ls -ld "${APP_DIR}/vendor"
ls -l "${APP_DIR}/vendor" | head -n 5

exit 0
