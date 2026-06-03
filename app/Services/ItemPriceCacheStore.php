<?php

namespace App\Services;

use App\Models\ItemPriceCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ItemPriceCacheStore
{
    public function ttlSeconds(): int
    {
        return (int) config('cs2price.db_item_price_cache_seconds', 14400);
    }

    /**
     * @param  'buff'|'empire'|'empire_cs2cap'  $source
     * @param  list<array{hash: string, phase: string|null}>  $keys
     * @return array<string, array<string, mixed>> map key => payload
     */
    public function getFresh(string $source, array $keys): array
    {
        $keys = array_values(array_filter($keys, fn ($k) => ! empty($k['hash'])));
        if ($keys === []) {
            return [];
        }

        $ttl = $this->ttlSeconds();
        $cutoff = CarbonImmutable::now()->subSeconds(max(60, $ttl));

        // Group by phase to handle nullable comparison.
        $byPhase = [];
        foreach ($keys as $k) {
            $phaseKey = $k['phase'] ?? '__NULL__';
            $byPhase[$phaseKey][] = $k['hash'];
        }

        $found = [];
        foreach ($byPhase as $phaseKey => $hashes) {
            $phase = $phaseKey === '__NULL__' ? null : $phaseKey;
            $q = ItemPriceCache::query()
                ->where('source', $source)
                ->whereIn('market_hash_name', array_values(array_unique($hashes)))
                ->where('fetched_at', '>=', $cutoff);

            if ($phase === null) {
                $q->whereNull('phase');
            } else {
                $q->where('phase', $phase);
            }

            /** @var Collection<int, ItemPriceCache> $rows */
            $rows = $q->get();
            foreach ($rows as $row) {
                $found[$this->key($row->market_hash_name, $row->phase)] = (array) ($row->payload ?? []);
            }
        }

        return $found;
    }

    /**
     * @param  'buff'|'empire'|'empire_cs2cap'  $source
     * @param  array<string, array<string, mixed>>  $payloadByKey
     */
    public function putMany(string $source, array $payloadByKey, ?string $currency = null): void
    {
        if ($payloadByKey === []) {
            return;
        }

        $now = now();

        foreach ($payloadByKey as $key => $payload) {
            [$hash, $phase] = $this->splitKey($key);

            $price = null;
            if ($source === 'buff') {
                $price = isset($payload['sell_min_price']) ? (float) $payload['sell_min_price'] : null;
            } elseif ($source === 'empire') {
                $price = isset($payload['market_value_coins']) ? (float) $payload['market_value_coins'] : null;
            } elseif ($source === 'empire_cs2cap') {
                $price = isset($payload['empire_price_usd']) ? (float) $payload['empire_price_usd'] : null;
            }

            ItemPriceCache::query()->updateOrCreate(
                [
                    'source' => $source,
                    'market_hash_name' => $hash,
                    'phase' => $phase,
                ],
                [
                    'currency' => $currency,
                    'price' => $price,
                    'payload' => $payload,
                    'fetched_at' => $now,
                ]
            );
        }
    }

    public function key(string $hash, ?string $phase): string
    {
        return $hash.'|'.($phase ?? '');
    }

    /**
     * @return array{0:string,1:string|null}
     */
    private function splitKey(string $key): array
    {
        $pos = strrpos($key, '|');
        if ($pos === false) {
            return [$key, null];
        }

        $hash = substr($key, 0, $pos);
        $phase = substr($key, $pos + 1);

        return [$hash, $phase !== '' ? $phase : null];
    }
}

