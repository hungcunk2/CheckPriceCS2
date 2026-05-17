# CheckPrice CS2

Web theo dõi **kho đồ CS2** (Steam inventory public) và **giá Buff163**, có khu vực quản trị riêng và trang xem công khai.

## Công dụng

- Gắn link kho Steam, lấy danh sách skin tradable và tra giá từ Buff163.
- Lưu snapshot giá để xem nhanh, không cần gọi Buff mỗi lần mở trang.
- Quy đổi CNY → VND theo tỷ giá cấu hình.
- Đồng bộ giá định kỳ: ưu tiên item chưa có giá, sau đó item có giá cũ hơn ngưỡng cache (mặc định 2 giờ).
- Bật/tắt từng kho trên trang công khai.

## Trang công khai

- Danh sách các kho được phép hiển thị, tổng giá và thời điểm cập nhật gần nhất.
- Chi tiết từng kho: avatar/tên Steam, bảng skin, giá CNY/VND, lọc theo loại vũ khí (súng, dao, găng, …).

## Khu vực admin

- Đăng nhập bảo vệ; quản lý danh sách kho (thêm, sửa, xóa, sync thủ công).
- Xem chi tiết kho ngay trong admin: thống kê theo loại vũ khí và bảng giá mở rộng.
- **Lịch sử giá theo mốc thời gian** (múi giờ Việt Nam):
  - **Hiện tại (2h):** giá sync gần nhất trong 2 giờ.
  - **Hôm qua:** giá lần đầu sau 0h hôm qua; kèm chênh số listing Buff (+/−).
  - **0h hôm nay:** giá lần đầu sau 0h hôm nay.
  - **7 ngày trước:** giá lần đầu sau 0h cách đây 7 ngày.

## Dữ liệu

- Snapshot kho và giá: lưu cục bộ trên server.
- Lịch sử giá từng item: tích lũy qua các lần sync để tính các mốc trên.
