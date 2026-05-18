#!/bin/bash
# Cấu hình MySQL/MariaDB cho CheckPriceCS2 trên VPS (giống local: database checkpricecs2).
# Chạy: sudo bash deploy/configure-mysql.sh [mat_khau_mysql]
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/checkpricecs2}"
DB_NAME="checkpricecs2"
DB_USER="checkpricecs2"
DB_PASS="${1:-}"

if [ -z "$DB_PASS" ]; then
  DB_PASS=$(openssl rand -base64 16 | tr -d '/+=' | head -c 16)
  echo "Tạo mật khẩu MySQL ngẫu nhiên: $DB_PASS"
fi

if ! command -v mysql >/dev/null 2>&1; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y mariadb-server
  systemctl enable mariadb
  systemctl start mariadb
fi

# MariaDB Ubuntu: root qua socket, không cần pass
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

if [ ! -f "$APP_DIR/.env" ]; then
  echo "Chưa có $APP_DIR/.env — chạy: bash $APP_DIR/deploy/setup-env.sh"
  bash "$APP_DIR/deploy/setup-env.sh"
fi

cd "$APP_DIR"

set_env() {
  local key="$1"
  local val="$2"
  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${val}|" .env
  else
    echo "${key}=${val}" >> .env
  fi
}

set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASS"

php artisan config:clear
php artisan migrate --force --no-interaction

if [ -f storage/app/tracked_inventories.json ]; then
  php artisan cs2price:import-json --force 2>/dev/null || php artisan cs2price:import-json || true
fi

php artisan config:cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo ""
echo "=== MySQL VPS đã cấu hình ==="
echo "Database : $DB_NAME"
echo "User     : $DB_USER"
echo "Password : $DB_PASS  (đã ghi vào .env)"
echo "Chạy: php artisan migrate:status"
