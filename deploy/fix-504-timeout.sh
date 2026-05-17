#!/bin/bash
# Sửa 504 khi thêm kho / sync giá (Nginx + PHP-FPM timeout 10 phút)
set -euo pipefail

PHP_VER="${PHP_VER:-8.3}"
APP_DIR="${APP_DIR:-/var/www/checkpricecs2}"
NGINX_SITE="/etc/nginx/sites-available/checkpricecs2"
POOL="/etc/php/${PHP_VER}/fpm/pool.d/www.conf"

if [ -f "$APP_DIR/deploy/nginx-checkpricecs2.conf" ]; then
  cp "$APP_DIR/deploy/nginx-checkpricecs2.conf" "$NGINX_SITE"
fi

if [ -f "$POOL" ]; then
  grep -q '^request_terminate_timeout' "$POOL" \
    && sed -i 's/^request_terminate_timeout.*/request_terminate_timeout = 600/' "$POOL" \
    || echo 'request_terminate_timeout = 600' >> "$POOL"
fi

nginx -t
systemctl reload nginx "php${PHP_VER}-fpm"
echo "OK: fastcgi_read_timeout=600s, PHP request_terminate_timeout=600"
