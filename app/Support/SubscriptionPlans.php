<?php

namespace App\Support;

use Illuminate\Support\Str;

final class SubscriptionPlans
{
    /** @var array<string, array{name: string, slots: string, prices: array<int, int>, features: list<string>}> */
    public const PLANS = [
        'pro' => [
            'name' => 'Pro',
            'slots' => '3 kho theo dõi',
            'prices' => [1 => 19_000, 3 => 54_000, 6 => 102_000],
            'features' => [
                'Lưu & theo dõi 3 kho',
                'Sync tự động mỗi 8h',
                'Refresh tay ~10 lần/ngày',
            ],
        ],
        'plus' => [
            'name' => 'Plus',
            'slots' => '20 kho theo dõi',
            'prices' => [1 => 39_000, 3 => 111_000, 6 => 210_000],
            'features' => [
                'Lưu & theo dõi 20 kho',
                'Sync tự động mỗi 4h',
                'Refresh tay ~30 lần/ngày',
            ],
        ],
        'max' => [
            'name' => 'Max',
            'slots' => '50 kho theo dõi',
            'prices' => [1 => 69_000, 3 => 197_000, 6 => 372_000],
            'features' => [
                'Lưu & theo dõi 50 kho',
                'Sync tự động mỗi 2h',
                'Refresh tay ~80 lần/ngày',
            ],
        ],
        'shop' => [
            'name' => 'Shop',
            'slots' => 'Không giới hạn kho*',
            'prices' => [1 => 159_000, 3 => 453_000, 6 => 858_000],
            'features' => [
                'Không giới hạn số kho',
                'Sync tự động mỗi 1h',
                'Refresh tay không giới hạn*',
            ],
        ],
    ];

    public const CYCLES = [1, 3, 6];

    public static function exists(string $plan): bool
    {
        return isset(self::PLANS[$plan]);
    }

    /**
     * @return array{name: string, slots: string, prices: array<int, int>, features: list<string>}|null
     */
    public static function get(string $plan): ?array
    {
        return self::PLANS[$plan] ?? null;
    }

    public static function price(string $plan, int $months): ?int
    {
        $data = self::get($plan);

        return $data['prices'][$months] ?? null;
    }

    /**
     * Nội dung CK: phần trước @ + gói + số tháng (vd. trantuanhung@gmail → Pro 2 tháng → trantuanhungpro2).
     */
    public static function transferReference(string $email, string $plan, int $months): string
    {
        return self::emailLocalPart($email).strtolower($plan).$months;
    }

    public static function emailLocalPart(string $email): string
    {
        $local = Str::before(strtolower(trim($email)), '@');
        $slug = preg_replace('/[^a-z0-9]/', '', $local) ?? '';

        return $slug !== '' ? $slug : 'user';
    }

    public static function formatVnd(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'đ';
    }
}
