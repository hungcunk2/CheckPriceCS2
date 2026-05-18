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
php artisan migrate --force --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
systemctl reload php8.3-fpm nginx
echo "Deploy xong."
