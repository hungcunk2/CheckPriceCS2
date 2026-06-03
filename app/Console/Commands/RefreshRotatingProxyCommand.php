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

        $url = $proxy->refreshProxyIfDue($this->option('force'));

        if ($url === null || $url === '') {
            $this->warn('Không lấy được proxy (xem log hoặc Admin → Proxy Empire).');

            return self::FAILURE;
        }

        $this->line($url);

        return self::SUCCESS;
    }
}
