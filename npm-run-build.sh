#!/bin/bash
set -euo pipefail

# ======================
# CONFIGURATION
# ======================

# Get the script's directory (root of Laravel project)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_DIR="$SCRIPT_DIR"
CONTAINER_APP_DIR="/var/www"  # Matches docker-compose.yml

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
# NPM BUILD
# ======================
echo "🚀 Starting npm build..."
docker run -it --rm \
  -v "${APP_DIR}:${CONTAINER_APP_DIR}" \
  -v "${CONTAINER_APP_DIR}/node_modules" \
  -w "${CONTAINER_APP_DIR}" \
  node:18 \
  sh -c "npm install && npm run build"

# ======================
# PERMISSION FIX
# ======================
echo "🔧 Fixing permissions for ${APP_DIR}/public/build..."
chown -R "${USER}:${GROUP}" "${APP_DIR}/public/build"
find "${APP_DIR}/public/build" -type d -exec chmod 775 {} \;
find "${APP_DIR}/public/build" -type f -exec chmod 664 {} \;

echo "✅ All done! Build directory permissions:"
ls -ld "${APP_DIR}/public/build"
ls -l "${APP_DIR}/public/build" | head -n 5

exit 0
