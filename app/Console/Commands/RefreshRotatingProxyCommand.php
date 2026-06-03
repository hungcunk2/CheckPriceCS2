<?php

namespace App\Console\Commands;

use App\Services\FiveStarsRotatingProxyService;
use Illuminate\Console\Command;

class RefreshRotatingProxyCommand extends Command
{
    protected $signature = 'cs2price:refresh-proxy {--force : Bỏ qua khoảng chờ 62s}';

    protected $description = 'Gọi API 5Stars lấy proxy mới (theo FIVESTARS_PROXY_ROTATE_SECONDS)';

    public function handle(FiveStarsRotatingProxyService $proxy): int
    {
        if (! $proxy->isEnabled()) {
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $status = $proxy->refreshProxyIfDueWithStatus($force);
        $url = $status['url'];

        if ($url === null || $url === '') {
            $this->warn('Không lấy được proxy (xem log hoặc Admin → Proxy Empire).');

            return self::FAILURE;
        }

        $this->line($url);
        $this->comment(match ($status['source']) {
            'api_fresh' => 'Nguồn: gọi API 5Stars.',
            'api_throttled' => 'Nguồn: API từ chối đổi — giữ proxy cũ.',
            'throttle_cache' => 'Nguồn: cache (chưa gọi API).',
            'interval_cache' => 'Nguồn: cache (chưa đủ chu kỳ).',
            default => 'Nguồn: '.$status['source'],
        });
        if ($status['message'] !== '') {
            $this->comment($status['message']);
        }

        $exitIp = $proxy->probeExitIp($url);
        if ($exitIp !== null) {
            $this->line('IP ra ngoài (qua proxy): '.$exitIp);
        } else {
            $this->warn('Không đo được IP ra ngoài — kiểm tra whitelist VPS trên 5Stars.');
        }

        return self::SUCCESS;
    }
}
