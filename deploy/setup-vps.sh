#!/bin/bash
# Chạy trên VPS (root): bash setup-vps.sh
set -euo pipefail

APP_DIR="/var/www/checkpricecs2"
REPO="https://github.com/hungcunk2/CheckPriceCS2.git"
PHP_VER="8.3"

export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y

apt-get install -y \
  nginx git curl unzip mariadb-server \
  "php${PHP_VER}-fpm" "php${PHP_VER}-cli" \
  "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" \
  "php${PHP_VER}-zip" "php${PHP_VER}-bcmath" "php${PHP_VER}-intl" \
  "php${PHP_VER}-mysql"

systemctl enable mariadb
systemctl start mariadb

if ! command -v composer >/dev/null 2>&1; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

if [ ! -d "$APP_DIR/.git" ]; then
  git clone "$REPO" "$APP_DIR"
else
  echo "Đã có $APP_DIR — bỏ qua clone."
fi

cd "$APP_DIR"

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

bash "$APP_DIR/deploy/setup-env.sh"
bash "$APP_DIR/deploy/configure-mysql.sh"

bash "$APP_DIR/deploy/fix-storage-permissions.sh"

sudo -u www-data php artisan storage:link 2>/dev/null || true
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

bash "$APP_DIR/deploy/fix-storage-permissions.sh"

# PHP-FPM nhẹ cho VPS 1GB RAM
POOL="/etc/php/${PHP_VER}/fpm/pool.d/www.conf"
if [ -f "$POOL" ]; then
  sed -i 's/^pm = .*/pm = ondemand/' "$POOL" || true
  sed -i 's/^pm.max_children = .*/pm.max_children = 4/' "$POOL" || true
  sed -i 's/^;pm.process_idle_timeout = .*/pm.process_idle_timeout = 10s/' "$POOL" || true
fi

cp "$APP_DIR/deploy/nginx-checkpricecs2.conf" /etc/nginx/sites-available/checkpricecs2
ln -sf /etc/nginx/sites-available/checkpricecs2 /etc/nginx/sites-enabled/checkpricecs2
rm -f /etc/nginx/sites-enabled/default

nginx -t
systemctl enable nginx "php${PHP_VER}-fpm"
systemctl restart "php${PHP_VER}-fpm" nginx

CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
(crontab -u www-data -l 2>/dev/null | grep -F "schedule:run" || true) | grep -q schedule:run || \
  (crontab -u www-data -l 2>/dev/null; echo "$CRON_LINE") | crontab -u www-data -

echo ""
echo "=== Xong phần cài đặt ==="
echo "1. Bổ sung Buff (nếu chưa): sudo BUFF163_SESSION='...' bash $APP_DIR/deploy/setup-env.sh"
echo "   hoặc: sudo nano $APP_DIR/.env"
echo "2. php artisan config:cache"
echo "3. Mở: http://160.187.146.255"
echo "4. Admin: http://160.187.146.255/admin/login"
