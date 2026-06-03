<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentSetting extends Model
{
    public const QR_TEMPLATES = ['compact', 'compact2', 'qr_only', 'print'];

    protected $fillable = [
        'enabled',
        'bank_bin',
        'bank_code',
        'bank_display_name',
        'account_number',
        'account_holder',
        'qr_template',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $row = self::query()->first();
        if ($row) {
            return $row;
        }

        $legacy = config('cs2price.payment', []);

        return self::query()->create([
            'enabled' => true,
            'bank_display_name' => (string) ($legacy['bank_name'] ?? ''),
            'account_number' => (string) ($legacy['account_number'] ?? ''),
            'account_holder' => (string) ($legacy['account_holder'] ?? ''),
            'qr_template' => 'compact',
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->enabled
            && filled($this->bank_bin)
            && filled($this->account_number)
            && filled($this->account_holder);
    }

    /**
     * @return array{bank_name: string, account_number: string, account_holder: string, configured: bool, vietqr: array<string, mixed>|null}
     */
    public function forCheckout(): array
    {
        return [
            'bank_name' => (string) ($this->bank_display_name ?? ''),
            'account_number' => (string) ($this->account_number ?? ''),
            'account_holder' => (string) ($this->account_holder ?? ''),
            'configured' => $this->isConfigured(),
            'vietqr' => $this->isConfigured() ? [
                'bankId' => $this->vietqrBankId(),
                'account' => preg_replace('/\s+/', '', (string) $this->account_number),
                'template' => $this->qr_template ?: 'compact',
                'accountHolder' => $this->vietqrAccountName(),
            ] : null,
        ];
    }

    public function vietqrBankId(): string
    {
        return (string) ($this->bank_bin ?: $this->bank_code ?: '');
    }

    public function vietqrAccountName(): string
    {
        $name = Str::upper(Str::ascii((string) $this->account_holder));

        return preg_replace('/[^A-Z0-9 ]/', '', $name) ?? '';
    }

}
