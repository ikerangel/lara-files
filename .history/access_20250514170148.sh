#!/bin/bash
set -e

# ======================================
# Configure Laravel Ownership/Permissions
# ======================================

APP_DIR="/var/www/html/app3"
USER="isl"
GROUP="www-data"

# Verify script is run as root
if [ "$EUID" -ne 0 ]
  then echo "❗ Please run with sudo"
  exit 1
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
