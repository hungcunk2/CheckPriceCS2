<?php

namespace App\Services;

use App\Support\Cs2CapApiPool;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * CS2Cap — lấy giá theo shard: chia skin cho từng key admin, gọi batch song song, retry chunk lỗi 1 lần.
 */
class Cs2CapService
{
    /** @var array<string, array{buff: array<string, array<string, mixed>>, empire: array<string, array<string, mixed>>}> */
    private array $fetchMemo = [];

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @return array<string, array{sell_min_price: float|null, sell_num: int|null, buff_url: string|null, error: string|null}>
     */
    public function getBuffPricesForSteamItems(array $steamItems): array
    {
        return $this->shardedFetchAll($steamItems)['buff'];
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @return array<string, array{empire_price_usd: float|null, listing_count: int|null, empire_url: string|null, error: string|null}>
     */
    public function getEmpireUsdPricesForSteamItems(array $steamItems): array
    {
        return $this->shardedFetchAll($steamItems)['empire'];
    }

    /**
     * @return array{
     *   buff: array<string, array{sell_min_price: float|null, sell_num: int|null, buff_url: string|null, error: string|null}>,
     *   empire: array<string, array{empire_price_usd: float|null, listing_count: int|null, empire_url: string|null, error: string|null}>
     * }
     */
    public function getBuffCnyAndEmpireUsd(array $marketHashNames): array
    {
        $unique = array_values(array_unique(array_filter($marketHashNames)));
        if ($unique === [] || ! $this->isConfigured()) {
            return ['buff' => [], 'empire' => []];
        }

        $items = array_map(
            fn (string $hash) => ['market_hash_name' => $hash, 'phase' => null],
            $unique,
        );

        return $this->shardedFetchAll($items);
    }

    public function isConfigured(): bool
    {
        return filter_var(config('cs2price.cs2cap_enabled', false), FILTER_VALIDATE_BOOL)
            && Cs2CapApiPool::isConfigured();
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @return array{
     *   buff: array<string, array{sell_min_price: float|null, sell_num: int|null, buff_url: string|null, error: string|null}>,
     *   empire: array<string, array{empire_price_usd: float|null, listing_count: int|null, empire_url: string|null, error: string|null}>
     * }
     */
    private function shardedFetchAll(array $steamItems): array
    {
        $memoKey = $this->itemsMemoKey($steamItems);
        if (isset($this->fetchMemo[$memoKey])) {
            return $this->fetchMemo[$memoKey];
        }

        $buff = [];
        $empire = [];

        foreach ($steamItems as $item) {
            $hash = trim((string) ($item['market_hash_name'] ?? ''));
            if ($hash === '') {
                continue;
            }
            $buff[$hash] = $this->emptyBuffRow();
            $empire[$hash] = $this->emptyEmpireRow();
        }

        if ($steamItems === [] || ! $this->isConfigured()) {
            return $this->fetchMemo[$memoKey] = ['buff' => $buff, 'empire' => $empire];
        }

        if (Cs2CapApiPool::available() === []) {
            $error = Cs2CapApiPool::unusableReason() ?: 'CS2Cap không có key khả dụng';
            foreach (array_keys($buff) as $hash) {
                $buff[$hash]['error'] = $error;
                $empire[$hash]['error'] = $error;
            }

            return $this->fetchMemo[$memoKey] = ['buff' => $buff, 'empire' => $empire];
        }

        $jobs = $this->buildShardJobs($steamItems);
        $this->runJobsWithRetry($jobs, $buff, $empire);

        return $this->fetchMemo[$memoKey] = ['buff' => $buff, 'empire' => $empire];
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     * @return array<string, array<string, mixed>>
     */
    private function buildShardJobs(array $steamItems): array
    {
        $shards = Cs2CapApiPool::shardSteamItems($steamItems);
        $maxBatch = max(1, (int) config('cs2price.cs2cap_batch_max_items', 100));
        $buffCurrency = (string) config('cs2price.cs2cap_buff_currency', 'CNY');
        $empireCurrency = (string) config('cs2price.cs2cap_empire_currency', 'USD');
        $jobs = [];

        foreach ($shards as $shardIndex => $shard) {
            $account = $shard['account'];
            $noPhase = [];
            $withPhase = [];

            foreach ($shard['items'] as $item) {
                $phase = $item['phase'] ?? null;
                if (is_string($phase) && trim($phase) !== '') {
                    $withPhase[] = $item;
                } else {
                    $noPhase[] = $item;
                }
            }

            foreach (array_chunk($noPhase, $maxBatch) as $batchIndex => $batch) {
                $names = array_values(array_unique(array_filter(array_map(
                    fn (array $row) => trim((string) ($row['market_hash_name'] ?? '')),
                    $batch,
                ))));

                if ($names === []) {
                    continue;
                }

                $jobs[$this->jobId($shardIndex, 'batch', $batchIndex, 'buff')] = [
                    'account' => $account,
                    'kind' => 'batch',
                    'provider' => 'buff163',
                    'currency' => $buffCurrency,
                    'names' => $names,
                ];
                $jobs[$this->jobId($shardIndex, 'batch', $batchIndex, 'empire')] = [
                    'account' => $account,
                    'kind' => 'batch',
                    'provider' => 'csgoempire',
                    'currency' => $empireCurrency,
                    'names' => $names,
                ];
            }

            foreach ($withPhase as $phaseIndex => $item) {
                $hash = trim((string) ($item['market_hash_name'] ?? ''));
                if ($hash === '') {
                    continue;
                }

                $jobs[$this->jobId($shardIndex, 'phase', $phaseIndex, 'buff')] = [
                    'account' => $account,
                    'kind' => 'single',
                    'provider' => 'buff163',
                    'currency' => $buffCurrency,
                    'market_hash_name' => $hash,
                    'phase' => (string) $item['phase'],
                ];
                $jobs[$this->jobId($shardIndex, 'phase', $phaseIndex, 'empire')] = [
                    'account' => $account,
                    'kind' => 'single',
                    'provider' => 'csgoempire',
                    'currency' => $empireCurrency,
                    'market_hash_name' => $hash,
                    'phase' => (string) $item['phase'],
                ];
            }
        }

        return $jobs;
    }

    /**
     * @param  array<string, array<string, mixed>>  $jobs
     * @param  array<string, array<string, mixed>>  $buff
     * @param  array<string, array<string, mixed>>  $empire
     */
    private function runJobsWithRetry(array $jobs, array &$buff, array &$empire): void
    {
        if ($jobs === []) {
            return;
        }

        $responses = $this->executeJobPool($jobs);
        $failed = [];

        foreach ($jobs as $jobId => $job) {
            $response = $responses[$jobId] ?? null;
            if ($this->applyJobResponse($job, $response, $buff, $empire)) {
                continue;
            }

            if ($this->shouldRetryJob($response)) {
                $failed[$jobId] = ['job' => $job, 'response' => $response];
            } else {
                $this->applyJobFailure($job, $response, $buff, $empire);
            }
        }

        if ($failed === []) {
            return;
        }

        $retryJobs = [];
        foreach ($failed as $jobId => $failedRow) {
            $job = $failedRow['job'];
            $retryAccount = Cs2CapApiPool::pickRetryAccount((string) $job['account']['label']);
            if ($retryAccount === null) {
                $this->applyJobFailure($job, $failedRow['response'], $buff, $empire);

                continue;
            }
            $retryJob = $job;
            $retryJob['account'] = $retryAccount;
            $retryJobs[$jobId.'-retry'] = $retryJob;
        }

        if ($retryJobs === []) {
            return;
        }

        $retryResponses = $this->executeJobPool($retryJobs);
        foreach ($retryJobs as $retryId => $job) {
            $response = $retryResponses[$retryId] ?? null;
            if ($this->applyJobResponse($job, $response, $buff, $empire)) {
                continue;
            }

            $this->applyJobFailure($job, $response, $buff, $empire);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $jobs
     * @return array<string, Response>
     */
    private function executeJobPool(array $jobs): array
    {
        $base = rtrim((string) config('cs2price.cs2cap_base_url', 'https://api.cs2c.app/v1'), '/');

        /** @var array<string, Response> $responses */
        $responses = Http::pool(function (Pool $pool) use ($jobs, $base) {
            foreach ($jobs as $jobId => $job) {
                $headers = [
                    'Authorization' => 'Bearer '.$job['account']['api_key'],
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip',
                ];

                if ($job['kind'] === 'batch') {
                    $pool->as($jobId)
                        ->timeout(60)
                        ->withHeaders($headers)
                        ->post("{$base}/prices/batch", [
                            'market_hash_names' => $job['names'],
                            'providers' => [$job['provider']],
                            'currency' => strtoupper((string) $job['currency']),
                        ]);

                    continue;
                }

                $query = [
                    'market_hash_name' => $job['market_hash_name'],
                    'providers' => $job['provider'],
                    'currency' => strtoupper((string) $job['currency']),
                    'limit' => 5,
                    'phase' => $job['phase'],
                ];

                $pool->as($jobId)
                    ->timeout(25)
                    ->withHeaders($headers)
                    ->get("{$base}/prices", $query);
            }
        });

        foreach ($jobs as $jobId => $job) {
            $response = $responses[$jobId] ?? null;
            if ($response instanceof Response) {
                Cs2CapApiPool::handleResponse((string) $job['account']['label'], $response);
            }
        }

        return $responses;
    }

    /**
     * @param  array<string, mixed>  $job
     * @param  array<string, array<string, mixed>>  $buff
     * @param  array<string, array<string, mixed>>  $empire
     */
    private function applyJobResponse(array $job, mixed $response, array &$buff, array &$empire): bool
    {
        if (! $response instanceof Response || ! $response->successful()) {
            return false;
        }

        if ($job['kind'] === 'batch') {
            foreach ($response->json('items') ?? [] as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $hash = trim((string) ($block['market_hash_name'] ?? ''));
                if ($hash === '') {
                    continue;
                }
                foreach ($block['quotes'] ?? [] as $quote) {
                    if (! is_array($quote)) {
                        continue;
                    }
                    $this->applyQuoteRow($hash, $quote, (string) $job['currency'], $buff, $empire);
                }
            }

            return true;
        }

        $hash = trim((string) ($job['market_hash_name'] ?? ''));
        foreach ($response->json('items') ?? [] as $quote) {
            if (! is_array($quote)) {
                continue;
            }
            $this->applyQuoteRow($hash, $quote, (string) $job['currency'], $buff, $empire);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array<string, array<string, mixed>>  $buff
     * @param  array<string, array<string, mixed>>  $empire
     */
    private function applyQuoteRow(string $hash, array $quote, string $currency, array &$buff, array &$empire): void
    {
        $provider = (string) ($quote['provider'] ?? '');
        $minor = isset($quote['lowest_ask']) ? (int) $quote['lowest_ask'] : null;
        $amount = $this->minorToDecimal($minor, strtoupper($currency));
        $quantity = isset($quote['quantity']) ? (int) $quote['quantity'] : null;
        $url = $quote['url'] ?? $quote['link'] ?? null;
        $error = $minor === null ? 'Không có listing' : null;

        if ($provider === 'buff163') {
            $buff[$hash] = [
                'sell_min_price' => $amount,
                'sell_num' => $quantity,
                'buff_url' => $url,
                'error' => $error,
            ];

            return;
        }

        if ($provider === 'csgoempire') {
            $empire[$hash] = [
                'empire_price_usd' => $amount,
                'listing_count' => $quantity,
                'empire_url' => $url,
                'error' => $error,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $job
     * @param  array<string, array<string, mixed>>  $buff
     * @param  array<string, array<string, mixed>>  $empire
     */
    private function applyJobFailure(array $job, mixed $response, array &$buff, array &$empire): void
    {
        $error = $this->responseErrorMessage($response);
        $hashes = $job['kind'] === 'batch'
            ? ($job['names'] ?? [])
            : [trim((string) ($job['market_hash_name'] ?? ''))];

        foreach ($hashes as $hash) {
            $hash = trim((string) $hash);
            if ($hash === '') {
                continue;
            }

            if (($job['provider'] ?? '') === 'buff163') {
                $buff[$hash] = [
                    'sell_min_price' => null,
                    'sell_num' => null,
                    'buff_url' => null,
                    'error' => $error,
                ];
            } elseif (($job['provider'] ?? '') === 'csgoempire') {
                $empire[$hash] = [
                    'empire_price_usd' => null,
                    'listing_count' => null,
                    'empire_url' => null,
                    'error' => $error,
                ];
            }
        }
    }

    private function shouldRetryJob(mixed $response): bool
    {
        if (! $response instanceof Response) {
            return true;
        }

        if ($response->successful()) {
            return false;
        }

        if (in_array($response->status(), [401, 403], true)) {
            return false;
        }

        if ($response->status() === 429) {
            $code = (string) ($response->json('code') ?? '');

            return $code !== 'RATE_LIMIT_MONTHLY_QUOTA_EXCEEDED';
        }

        return in_array($response->status(), [408, 425, 429, 500, 502, 503, 504], true);
    }

    private function responseErrorMessage(mixed $response): string
    {
        if (! $response instanceof Response) {
            return 'CS2Cap không phản hồi';
        }

        if ($response->status() === 429) {
            $code = (string) ($response->json('code') ?? '');
            if ($code === 'RATE_LIMIT_MONTHLY_QUOTA_EXCEEDED') {
                return 'CS2Cap hết quota tháng';
            }

            return 'CS2Cap rate limited';
        }

        if ($response->status() === 401) {
            return 'CS2Cap key invalid';
        }

        $detail = $response->json('detail');
        if (is_string($detail) && $detail !== '') {
            return 'CS2Cap: '.$detail;
        }

        return 'CS2Cap HTTP '.$response->status();
    }

    /**
     * @param  list<array<string, mixed>>  $steamItems
     */
    private function itemsMemoKey(array $steamItems): string
    {
        $normalized = array_map(function (array $item) {
            return [
                trim((string) ($item['market_hash_name'] ?? '')),
                isset($item['phase']) && $item['phase'] !== '' ? (string) $item['phase'] : null,
            ];
        }, $steamItems);

        sort($normalized);

        return md5(json_encode($normalized, JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function jobId(int|string $shardIndex, string $type, int|string $chunkIndex, string $provider): string
    {
        return implode('-', [$shardIndex, $type, $chunkIndex, $provider]);
    }

    private function minorToDecimal(?int $minor, string $currency): ?float
    {
        if ($minor === null) {
            return null;
        }

        $divisor = $currency === 'VND' ? 1 : 100;

        return round($minor / $divisor, $currency === 'VND' ? 0 : 2);
    }

    /**
     * @return array{sell_min_price: null, sell_num: null, buff_url: null, error: null}
     */
    private function emptyBuffRow(): array
    {
        return [
            'sell_min_price' => null,
            'sell_num' => null,
            'buff_url' => null,
            'error' => null,
        ];
    }

    /**
     * @return array{empire_price_usd: null, listing_count: null, empire_url: null, error: null}
     */
    private function emptyEmpireRow(): array
    {
        return [
            'empire_price_usd' => null,
            'listing_count' => null,
            'empire_url' => null,
            'error' => null,
        ];
    }
}
