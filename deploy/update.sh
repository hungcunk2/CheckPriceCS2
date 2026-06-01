#!/bin/bash
# Cập nhật code sau khi git push: bash /var/www/checkpricecs2/deploy/update.sh
set -euo pipefail
cd /var/www/checkpricecs2
git pull origin main
# Đảm bảo .env tồn tại (không ghi đè secret đã có)
if [ ! -f .env ]; then
  bash deploy/setup-env.sh
fi
composer install --no-dev --optimize-autoloader --no-interaction
if [ -f scripts/generate-og-share.php ] && [ ! -f public/images/og-share.jpg ]; then
  php scripts/generate-og-share.php || true
fi
php artisan migrate --force --no-interaction
php artisan storage:link 2>/dev/null || true
# Đọc lại .env (sau khi sửa EMPIRE_ENABLED, API key, …)
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

APP_DIR="/var/www/checkpricecs2"
CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"
if ! crontab -u www-data -l 2>/dev/null | grep -qF "schedule:run"; then
  (crontab -u www-data -l 2>/dev/null; echo "$CRON_LINE") | crontab -u www-data -
  echo "Đã thêm cron tự chạy schedule (mỗi phút → sync kho/giá theo BUFF_PRICE_AUTO_SYNC_MINUTES)."
fi

systemctl reload php8.3-fpm nginx
echo "Deploy xong."
