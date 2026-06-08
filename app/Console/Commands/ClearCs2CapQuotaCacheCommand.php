<?php

namespace App\Console\Commands;

use App\Support\Cs2CapQuotaTracker;
use Illuminate\Console\Command;

class ClearCs2CapQuotaCacheCommand extends Command
{
    protected $signature = 'cs2cap:clear-quota-cache';

    protected $description = 'Xóa cache quota CS2Cap (remaining/exhausted) — dùng khi key bị coi nhầm là hết quota sau tối ưu';

    public function handle(): int
    {
        $cleared = Cs2CapQuotaTracker::forgetAll();

        if ($cleared === 0) {
            $this->warn('Không có key CS2Cap nào trong pool — kiểm tra Admin hoặc .env.');

            return self::FAILURE;
        }

        $this->info("Đã xóa cache quota cho {$cleared} key. Bấm Kiểm tra key trong Admin để đo lại.");

        return self::SUCCESS;
    }
}
