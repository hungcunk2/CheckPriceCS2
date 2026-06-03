<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

enum PricingTier: string
{
    case Guest = 'guest';
    case Member = 'member';
    case Admin = 'admin';

    public static function current(): self
    {
        if (session('admin_authenticated')) {
            return self::Admin;
        }

        $user = Auth::user();
        if ($user instanceof User && $user->hasActiveSubscription()) {
            return self::Member;
        }

        return self::Guest;
    }

    public function usesEmpireApi(): bool
    {
        return $this === self::Member || $this === self::Admin;
    }

    public function usesCs2CapEmpireOnly(): bool
    {
        return $this === self::Guest;
    }

    /** @return 'guest'|'member'|'admin'|'sync'|'http' */
    public function empireMode(): string
    {
        return match ($this) {
            self::Guest => 'guest',
            self::Member => 'member',
            self::Admin => 'admin',
        };
    }
}
