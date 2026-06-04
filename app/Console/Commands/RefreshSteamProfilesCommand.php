<?php

namespace App\Console\Commands;

use App\Models\TrackedInventory;
use App\Support\InventorySteamProfileResolver;
use Illuminate\Console\Command;

class RefreshSteamProfilesCommand extends Command
{
    protected $signature = 'cs2price:refresh-steam-profiles {--missing : Chỉ kho chưa có avatar hoặc tên Steam}';

    protected $description = 'Cập nhật steam_id, tên và avatar từ link kho Steam';

    public function handle(InventorySteamProfileResolver $resolver): int
    {
        $query = TrackedInventory::query()->whereNotNull('url')->where('url', '!=', '');

        if ($this->option('missing')) {
            $query->where(function ($q) {
                $q->whereNull('steam_avatar_url')
                    ->orWhere('steam_avatar_url', '')
                    ->orWhereNull('steam_persona_name')
                    ->orWhere('steam_persona_name', '');
            });
        }

        $rows = $query->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->info('Không có kho cần cập nhật.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;

        foreach ($rows as $row) {
            $merged = $resolver->mergeIntoAttributes([
                'label' => $row->label,
                'url' => $row->url,
                'steam_id' => $row->steam_id,
                'steam_persona_name' => $row->steam_persona_name,
                'steam_avatar_url' => $row->steam_avatar_url,
            ], (string) $row->url);

            if (empty($merged['steam_avatar_url']) && empty($merged['steam_persona_name'])) {
                $this->warn("Bỏ qua #{$row->id} — không lấy được profile.");
                $fail++;

                continue;
            }

            $row->fill([
                'steam_id' => $merged['steam_id'] ?? $row->steam_id,
                'steam_persona_name' => $merged['steam_persona_name'] ?? $row->steam_persona_name,
                'steam_avatar_url' => $merged['steam_avatar_url'] ?? $row->steam_avatar_url,
            ]);
            $row->save();
            $ok++;
            $this->line("OK #{$row->id} — ".($merged['steam_persona_name'] ?? '—'));
        }

        $this->info("Xong: {$ok} cập nhật, {$fail} thất bại.");

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
