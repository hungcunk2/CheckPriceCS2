# CheckPrice CS2 (Buff163)

Ứng dụng Laravel: **admin** quản lý link kho Steam + check giá Buff163; **trang công khai** chỉ xem giá các kho đã lưu.

## Phân quyền

| Khu vực | URL | Mô tả |
|---------|-----|--------|
| Công khai | `/` | Danh sách kho + tổng giá (Buff163) |
| Chi tiết | `/kho/{id}` | Bảng skin & giá (snapshot lần check cuối) |
| Admin | `/admin/login` | Đăng nhập quản trị |
| Quản lý | `/admin/inventories` | Thêm/sửa/xóa link, check giá, bật/tắt hiển thị |

## Cài đặt

```bash
cd D:\CheckPriceCS2
composer install
cp .env.example .env
php artisan key:generate
```

Cấu hình `.env`:

```env


BUFF163_SESSION=...   # cookie session từ buff.163.com
BUFF163_CSRF_TOKEN=... # tùy chọn
```

```bash
php artisan serve
# Terminal khác — tự động lấy giá mỗi 10 phút (dev):
php artisan schedule:work
```

- Trang ngoài: http://127.0.0.1:8000  
- Admin: http://127.0.0.1:8000/admin/login  

## Admin làm gì?

1. Đăng nhập admin  
2. **Thêm kho** — tên hiển thị + link Steam inventory (public)  
3. Tick **Check giá Buff163 ngay** để lưu snapshot giá  
4. Bật **Hiển thị trang công khai** — user mới thấy trên `/`  
5. Nút **sync** để cập nhật giá sau này  

Dữ liệu lưu tại `storage/app/tracked_inventories.json`.

## Đẩy lên GitHub

Các thư mục/file **không** lên repo (đã khai báo trong `.gitignore`):

| Không push | Lý do |
|------------|--------|
| `.env` | Mật khẩu admin, `BUFF163_SESSION`, `APP_KEY` |
| `vendor/` | Cài lại bằng `composer install` |
| `storage/app/tracked_inventories.json` | Dữ liệu kho + snapshot giá |
| `storage/app/price_history/` | Lịch sử giá theo ngày |
| `storage/logs/`, cache, session | Runtime |

```bash
git init
git add .
git status
# Phải KHÔNG thấy .env, tracked_inventories.json, price_history/*.json
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/<user>/<repo>.git
git push -u origin main
```

Trên VPS: clone repo → `cp .env.example .env` → điền secret → `composer install` → `php artisan key:generate` → sync kho lại.

## Lưu ý

- Chỉ **một** dòng `BUFF163_SESSION` trong `.env` (không để dòng trống trùng phía dưới).  
- `BUFF_PRICE_REFRESH_SECONDS=7200`: skin **đã có giá** chỉ gọi Buff lại sau **2 giờ**; skin **chưa có giá / lỗi** thử lại mỗi lần sync.  
- **Tự động mỗi 10 phút**: `php artisan schedule:work` hoặc cron `* * * * * php artisan schedule:run` — lệnh `cs2price:sync-prices` (ưu tiên chưa có giá → giá cũ > 2h). Tắt: `BUFF_PRICE_AUTO_SYNC=false`.  
- Kho lớn: check có thể mất vài phút (`CHECK_MAX_EXECUTION_SECONDS=600`).  
- Trang ngoài hiển thị giá **đã lưu lần cuối**, không tự gọi Buff khi user mở web.
