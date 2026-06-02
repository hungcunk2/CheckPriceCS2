<?php

$tests = [
    ['GET', 'https://api.cs2.sh/health', null],
    ['POST', 'https://api.cs2.sh/v1/prices/latest', '{"items":["AK-47 | Redline (Field-Tested)"]}'],
    ['GET', 'https://api.cs2.sh/v1/prices/latest', null],
    ['POST', 'https://api.cs2.sh/v1/prices/history', '{"items":["AK-47 | Redline (Field-Tested)"],"start":"2026-05-01","interval":"1d"}'],
    ['GET', 'https://api.cs2.sh/v1/market/buff/latest', null],
    ['POST', 'https://api.cs2.sh/v1/market/buff/history', '{"items":["AK-47 | Redline (Field-Tested)"],"start":"2026-05-01"}'],
    ['GET', 'https://api.cs2.sh/internal/health', null],
    ['GET', 'https://api.cs2.sh/api/buff/goods/sell_order', null],
    ['GET', 'https://api.cs2.sh/buff163/market/goods', null],
    ['POST', 'https://api.cs2.sh/graphql', '{}'],
    ['OPTIONS', 'https://api.cs2.sh/v1/prices/latest', null],
    ['POST', 'https://api.cs2.sh/v1/prices/latest', '{"items":["AK-47 | Redline (Field-Tested)"]}', 'Bearer invalid_key_test'],
    ['GET', 'https://api.cs2.sh/openapi.json', null],
    ['GET', 'https://api.cs2.sh/swagger/v1/swagger.json', null],
    ['GET', 'https://api.cs2.sh/.env', null],
    ['GET', 'https://api.cs2.sh/debug/pprof', null],
];

foreach ($tests as $row) {
    [$method, $url, $body] = $row;
    $auth = $row[3] ?? null;
    echo "\n=== {$method} {$url} ===\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array_filter([
            'Accept: application/json',
            'Accept-Encoding: gzip',
            $body ? 'Content-Type: application/json' : null,
            $auth,
            $method === 'OPTIONS' ? 'Origin: https://evil.example' : null,
            $method === 'OPTIONS' ? 'Access-Control-Request-Method: POST' : null,
        ]),
        CURLOPT_ENCODING => '',
        CURLOPT_POSTFIELDS => $body,
    ]);

    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $headers = substr((string) $raw, 0, $headerSize);
    $respBody = substr((string) $raw, $headerSize);

    echo "HTTP {$status}\n";
    foreach (['server:', 'via:', 'x-request-id:', 'location:', 'access-control-allow-origin:', 'cf-ray:'] as $needle) {
        if (preg_match('/^'.preg_quote($needle, '/').'.*$/mi', $headers, $m)) {
            echo trim($m[0])."\n";
        }
    }

    $preview = strlen($respBody) > 600 ? substr($respBody, 0, 600).'...' : $respBody;
    echo "Body: {$preview}\n";
}
