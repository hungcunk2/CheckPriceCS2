#!/bin/bash
# Tạo / cập nhật .env trên VPS (file .env không đưa lên git).
#
# Cách 1 — mặc định (APP_URL + tỷ giá + APP_KEY):
#   cd /var/www/checkpricecs2 && sudo bash deploy/setup-env.sh
#
# Cách 2 — truyền secret một dòng (khuyên dùng):
#   cd /var/www/checkpricecs2 && sudo \
#     APP_URL=http://160.187.146.255 \
#     ADMIN_PASSWORD='MatKhauAdmin123!' \
#     BUFF163_SESSION='session=...' \
#     bash deploy/setup-env.sh
#
# Cách 3 — chỉ sửa vài biến sau khi đã có .env:
#   cd /var/www/checkpricecs2 && sudo BUFF163_SESSION='...' bash deploy/setup-env.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/checkpricecs2}"
cd "$APP_DIR"

if [ ! -f .env.example ]; then
  echo "Không thấy .env.example trong $APP_DIR"
  exit 1
fi

if [ ! -f .env ]; then
  cp .env.example .env
  echo "Đã tạo .env từ .env.example"
fi

set_env() {
  local key="$1"
  local val="$2"
  local escaped
  escaped=$(printf '%s\n' "$val" | sed 's/[&|]/\\&/g')
  if grep -q "^${key}=" .env 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${escaped}|" .env
  else
    printf '%s=%s\n' "$key" "$val" >> .env
  fi
}

# --- Production mặc định ---
set_env APP_NAME CheckPriceCS2
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_TIMEZONE "${APP_TIMEZONE:-Asia/Ho_Chi_Minh}"
set_env APP_URL "${APP_URL:-http://160.187.146.255}"

set_env DB_CONNECTION mysql
set_env DB_HOST "${DB_HOST:-127.0.0.1}"
set_env DB_PORT "${DB_PORT:-3306}"
set_env DB_DATABASE "${DB_DATABASE:-checkpricecs2}"
set_env DB_USERNAME "${DB_USERNAME:-checkpricecs2}"

set_env SESSION_DRIVER file
set_env CACHE_STORE file
set_env QUEUE_CONNECTION sync

set_env ADMIN_USERNAME "${ADMIN_USERNAME:-admin}"
set_env CNY_TO_VND "${CNY_TO_VND:-3750}"
set_env VND_TO_USD "${VND_TO_USD:-26700}"
set_env BUFF_PRICE_REFRESH_SECONDS "${BUFF_PRICE_REFRESH_SECONDS:-14400}"
set_env BUFF_PRICE_AUTO_SYNC "${BUFF_PRICE_AUTO_SYNC:-true}"
set_env BUFF_PRICE_AUTO_SYNC_MINUTES "${BUFF_PRICE_AUTO_SYNC_MINUTES:-240}"
set_env BUFF_REQUEST_DELAY_MS "${BUFF_REQUEST_DELAY_MS:-350}"
set_env BUFF_CONCURRENCY "${BUFF_CONCURRENCY:-2}"
set_env CHECK_MAX_EXECUTION_SECONDS "${CHECK_MAX_EXECUTION_SECONDS:-600}"
set_env STEAM_INVENTORY_PAGE_SIZE "${STEAM_INVENTORY_PAGE_SIZE:-2000}"
set_env STEAM_REQUEST_DELAY_MS "${STEAM_REQUEST_DELAY_MS:-1500}"
set_env STEAM_REQUEST_DELAY_BETWEEN_INVENTORIES_MS "${STEAM_REQUEST_DELAY_BETWEEN_INVENTORIES_MS:-1200000}"
set_env STEAM_INVENTORY_CACHE_SECONDS "${STEAM_INVENTORY_CACHE_SECONDS:-14400}"
set_env BUFF_PRICE_CURRENT_WINDOW_HOURS "${BUFF_PRICE_CURRENT_WINDOW_HOURS:-2}"
set_env PRICE_HISTORY_DAYS "${PRICE_HISTORY_DAYS:-90}"
set_env PRICE_HISTORY_MAX_POINTS "${PRICE_HISTORY_MAX_POINTS:-3000}"

# --- Biến tùy chọn (chỉ ghi khi bạn truyền vào lệnh) ---
optional_vars=(
  DB_PASSWORD
  ADMIN_PASSWORD
  STEAM_API_KEY
  BUFF163_SESSION
  BUFF163_CSRF_TOKEN
)

for key in "${optional_vars[@]}"; do
  if [ -n "${!key:-}" ]; then
    set_env "$key" "${!key}"
  fi
done

# Mật khẩu admin: tạo ngẫu nhiên nếu chưa có và không truyền ADMIN_PASSWORD
if ! grep -q '^ADMIN_PASSWORD=.\+' .env 2>/dev/null; then
  if [ -z "${ADMIN_PASSWORD:-}" ]; then
    ADMIN_PASSWORD=$(openssl rand -base64 12 | tr -d '/+=' | head -c 12)
    set_env ADMIN_PASSWORD "$ADMIN_PASSWORD"
    echo "Đã tạo ADMIN_PASSWORD ngẫu nhiên: $ADMIN_PASSWORD"
  fi
fi

# APP_KEY
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force
  echo "Đã tạo APP_KEY"
fi

chown www-data:www-data .env 2>/dev/null || true
chmod 640 .env 2>/dev/null || true

php artisan config:clear
php artisan config:cache

echo ""
echo "=== .env trên VPS đã sẵn sàng ==="
echo "File: $APP_DIR/.env"
echo ""
echo "Kiểm tra / bổ sung (nếu chưa truyền qua lệnh):"
echo "  sudo nano $APP_DIR/.env"
echo "    BUFF163_SESSION=..."
echo "    BUFF163_CSRF_TOKEN=...   (nếu Buff yêu cầu)"
echo "    STEAM_API_KEY=...        (tùy chọn)"
echo ""
echo "Sau khi sửa .env:"
echo "  cd $APP_DIR && php artisan config:cache"
echo ""
if ! grep -q '^BUFF163_SESSION=.\+' .env 2>/dev/null; then
  echo "⚠ Chưa có BUFF163_SESSION — sync giá Buff sẽ không chạy."
fi
