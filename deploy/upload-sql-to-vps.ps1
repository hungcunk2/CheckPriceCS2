# Upload checkpricecs2.sql lên VPS (chạy trong PowerShell trên Windows)
# Sửa $VpsIp nếu cần

$VpsIp = "160.187.146.255"
$VpsUser = "root"
$LocalFile = "D:\CheckPriceCS2\database\sql\checkpricecs2-mariadb.sql"

if (-not (Test-Path $LocalFile)) {
    $LocalFile = "$env:USERPROFILE\Downloads\checkpricecs2.sql"
}

if (-not (Test-Path $LocalFile)) {
    Write-Error "Không tìm thấy file SQL. Đặt file tại Downloads\checkpricecs2.sql"
    exit 1
}

Write-Host "Upload: $LocalFile -> ${VpsUser}@${VpsIp}:/tmp/checkpricecs2.sql"
scp $LocalFile "${VpsUser}@${VpsIp}:/tmp/checkpricecs2.sql"

if ($LASTEXITCODE -ne 0) {
    Write-Error "Upload thất bại. Kiểm tra mật khẩu SSH / IP VPS."
    exit 1
}

Write-Host ""
Write-Host "Upload xong. Trên VPS chạy:"
Write-Host "  apt install -y mariadb-server mariadb-client"
Write-Host "  mysql -e \"CREATE DATABASE IF NOT EXISTS checkpricecs2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""
Write-Host "  mysql checkpricecs2 < /tmp/checkpricecs2.sql"
Write-Host "  cd /var/www/checkpricecs2 && php artisan config:clear && php artisan config:cache"
