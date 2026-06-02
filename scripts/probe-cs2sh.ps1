$body = '{"items":["AK-47 | Redline (Field-Tested)"]}'
$tests = @(
    @{ Name = "POST /v1/prices/latest (no key)"; Url = "https://api.cs2.sh/v1/prices/latest"; Method = "POST"; Body = $body },
    @{ Name = "GET /v1/prices/latest (no key)"; Url = "https://api.cs2.sh/v1/prices/latest"; Method = "GET" },
    @{ Name = "POST /v1/prices/history (no key)"; Url = "https://api.cs2.sh/v1/prices/history"; Method = "POST"; Body = '{"items":["AK-47 | Redline (Field-Tested)"],"start":"2026-05-01","interval":"1d"}' },
    @{ Name = "GET /v1/market/buff/latest (no key)"; Url = "https://api.cs2.sh/v1/market/buff/latest"; Method = "GET" },
    @{ Name = "POST /v1/market/buff/history (no key)"; Url = "https://api.cs2.sh/v1/market/buff/history"; Method = "POST"; Body = '{"items":["AK-47 | Redline (Field-Tested)"],"start":"2026-05-01"}' },
    @{ Name = "GET /internal/health"; Url = "https://api.cs2.sh/internal/health"; Method = "GET" },
    @{ Name = "GET /api/buff/goods/sell_order"; Url = "https://api.cs2.sh/api/buff/goods/sell_order"; Method = "GET" },
    @{ Name = "GET /buff163/..."; Url = "https://api.cs2.sh/buff163/market/goods"; Method = "GET" },
    @{ Name = "OPTIONS /v1/prices/latest"; Url = "https://api.cs2.sh/v1/prices/latest"; Method = "OPTIONS" }
)

foreach ($t in $tests) {
    Write-Host "`n=== $($t.Name) ===" -ForegroundColor Cyan
    try {
        $params = @{
            Uri = $t.Url
            Method = $t.Method
            TimeoutSec = 20
            UseBasicParsing = $true
        }
        if ($t.Body) {
            $params.Body = $t.Body
            $params.ContentType = "application/json"
        }
        $r = Invoke-WebRequest @params -SkipHttpErrorCheck
        Write-Host "Status: $($r.StatusCode)"
        $interesting = @('Server','via','x-request-id','location','access-control-allow-origin','cf-ray')
        foreach ($h in $interesting) {
            if ($r.Headers[$h]) { Write-Host "$h`: $($r.Headers[$h])" }
        }
        $preview = if ($r.Content.Length -gt 500) { $r.Content.Substring(0, 500) + "..." } else { $r.Content }
        Write-Host "Body: $preview"
    } catch {
        Write-Host "Error: $_"
    }
}
