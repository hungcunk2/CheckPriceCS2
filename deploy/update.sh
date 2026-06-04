#!/bin/bash
# Cập nhật code sau khi git push: bash /var/www/checkpricecs2/deploy/update.sh
set -euo pipefail

APP_DIR="/var/www/checkpricecs2"
WEB_USER="${WEB_USER:-www-data}"

cd "$APP_DIR"
git pull origin main

# Đảm bảo .env tồn tại (không ghi đè secret đã có)
if [ ! -f .env ]; then
  bash deploy/setup-env.sh
fi

composer install --no-dev --optimize-autoloader --no-interaction

if [ -f scripts/generate-og-share.php ] && [ ! -f public/images/og-share.jpg ]; then
  php scripts/generate-og-share.php || true
fi

bash deploy/fix-storage-permissions.sh

run_artisan() {
  sudo -u "$WEB_USER" php artisan "$@"
}

run_artisan migrate --force --no-interaction
run_artisan storage:link 2>/dev/null || true
# Đọc lại .env (sau khi sửa EMPIRE_ENABLED, API key, …)
run_artisan config:clear
run_artisan config:cache
run_artisan route:clear
run_artisan route:cache
run_artisan view:clear
run_artisan view:cache

bash deploy/fix-storage-permissions.sh

CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
if ! crontab -u "$WEB_USER" -l 2>/dev/null | grep -qF "schedule:run"; then
  (crontab -u "$WEB_USER" -l 2>/dev/null; echo "$CRON_LINE") | crontab -u "$WEB_USER" -
  echo "Đã thêm cron tự chạy schedule (mỗi phút → sync kho/giá theo BUFF_PRICE_AUTO_SYNC_MINUTES)."
fi

systemctl reload php8.3-fpm nginx
echo "Deploy xong."
