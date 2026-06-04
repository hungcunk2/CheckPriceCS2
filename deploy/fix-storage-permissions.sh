#!/bin/bash
# Sửa quyền storage/cache — chạy trên VPS (root):
#   bash /var/www/checkpricecs2/deploy/fix-storage-permissions.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/checkpricecs2}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

cd "$APP_DIR"

mkdir -p \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chown -R "${WEB_USER}:${WEB_GROUP}" storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "Đã sửa quyền storage + bootstrap/cache cho ${WEB_USER}."
