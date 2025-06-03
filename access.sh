#!/bin/bash
set -e

# ======================================
# Configure Laravel Ownership/Permissions
# ======================================

# Get the script's directory (root of Laravel project)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_DIR="$SCRIPT_DIR"

# Verify script is run as root
if [ "$EUID" -ne 0 ]
  then echo "❗ Please run with sudo"
  exit 1
fi

# Default values (if not found in .env)
USER="isl"
GROUP="www-data"

# Read .env file if exists
if [ -f "$APP_DIR/.env" ]; then
  echo "🔍 Reading .env file..."

  # Extract USER_ACCESS_SH (remove quotes/comments if present)
  if grep -q '^USER_ACCESS_SH=' "$APP_DIR/.env"; then
    USER=$(grep '^USER_ACCESS_SH=' "$APP_DIR/.env" | cut -d '=' -f2- | sed "s/['\"]//g" | cut -d '#' -f1 | xargs)
  fi

  # Extract GROUP_ACCESS_SH (remove quotes/comments if present)
  if grep -q '^GROUP_ACCESS_SH=' "$APP_DIR/.env"; then
    GROUP=$(grep '^GROUP_ACCESS_SH=' "$APP_DIR/.env" | cut -d '=' -f2- | sed "s/['\"]//g" | cut -d '#' -f1 | xargs)
  fi
fi

echo "🔧 Setting ownership to $USER:$GROUP..."
chown -R ${USER}:${GROUP} "$APP_DIR"

echo "🔧 Setting directory permissions (775)..."
find "$APP_DIR" -type d -exec chmod 775 {} \;

echo "🔧 Setting file permissions (664)..."
find "$APP_DIR" -type f -exec chmod 664 {} \;

echo "🔧 Making artisan and index.php executable..."
chmod 755 "$APP_DIR/artisan"
chmod 755 "$APP_DIR/public/index.php"

echo "🔒 Securing .env file..."
chmod 640 "$APP_DIR/.env"

echo "📁 Fixing storage/cache permissions..."
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

echo "🛠  Fixing node_modules ownership..."
chown -R ${USER}:${GROUP} "$APP_DIR/node_modules"

echo "🚩 Setting setgid for consistent group inheritance..."
find "$APP_DIR" -type d -exec chmod g+s {} \;

# ======================
# Verification
# ======================
echo -e "\n✅ Done! Verification:"
ls -l "$APP_DIR/.env" \
     "$APP_DIR/artisan" \
     "$APP_DIR/storage" \
     "$APP_DIR/bootstrap/cache" \
     "$APP_DIR/node_modules"

echo -e "\n💡 Maintenance notes:"
echo "1. After composer/npm commands, rerun this script"
echo "2. After file uploads via PHP, manually set permissions"
echo -e "\n⚙️  Configuration Used:"
echo "User: $USER"
echo "Group: $GROUP"
echo "App Directory: $APP_DIR"
