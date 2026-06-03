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

        return self::query()->create([
            'enabled' => true,
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

    public function quickLinkImageUrl(?int $amount = null, ?string $addInfo = null): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $bankId = $this->vietqrBankId();
        $account = preg_replace('/\s+/', '', (string) $this->account_number);
        $template = in_array($this->qr_template, self::QR_TEMPLATES, true)
            ? $this->qr_template
            : 'compact';

        $url = sprintf(
            'https://img.vietqr.io/image/%s-%s-%s.png',
            rawurlencode($bankId),
            rawurlencode($account),
            rawurlencode($template)
        );

        $query = array_filter([
            'amount' => $amount > 0 ? $amount : null,
            'addInfo' => $addInfo !== null && $addInfo !== ''
                ? mb_substr($addInfo, 0, 50)
                : null,
            'accountName' => $this->vietqrAccountName() ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($query === []) {
            return $url;
        }

        return $url.'?'.http_build_query($query);
    }
}
