#!/bin/bash
# Import file SQL lên MariaDB (chạy trên VPS sau khi upload vào /tmp/checkpricecs2.sql)
set -euo pipefail

SQL_FILE="${1:-/tmp/checkpricecs2.sql}"
DB_NAME="checkpricecs2"

if [ ! -f "$SQL_FILE" ]; then
  echo "Không thấy file: $SQL_FILE"
  exit 1
fi

if ! command -v mysql >/dev/null 2>&1; then
  apt-get update -y
  apt-get install -y mariadb-server
  systemctl start mariadb
fi

mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

TMP_SQL="/tmp/checkpricecs2-import-$$.sql"
sed 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' "$SQL_FILE" > "$TMP_SQL"
mysql "$DB_NAME" < "$TMP_SQL"
rm -f "$TMP_SQL"

APP_DIR="${APP_DIR:-/var/www/checkpricecs2}"
if [ -f "$APP_DIR/.env" ]; then
  cd "$APP_DIR"
  grep -q '^DB_CONNECTION=mysql' .env || sed -i 's|^DB_CONNECTION=.*|DB_CONNECTION=mysql|' .env
  grep -q '^DB_DATABASE=' .env && sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env || echo "DB_DATABASE=${DB_NAME}" >> .env
  php artisan config:clear
  php artisan config:cache
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
fi

echo "Import xong database ${DB_NAME} từ ${SQL_FILE}"
