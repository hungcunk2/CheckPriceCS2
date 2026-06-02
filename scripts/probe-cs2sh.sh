#!/bin/bash
probe() {
  echo ""
  echo "=== $1 ==="
  shift
  curl.exe -sS -i --max-time 20 "$@" | head -c 1200
  echo ""
}

probe "GET /health" "https://api.cs2.sh/health"

probe "POST /v1/prices/latest no key" -X POST "https://api.cs2.sh/v1/prices/latest" \
  -H "Content-Type: application/json" \
  -d '{"items":["AK-47 | Redline (Field-Tested)"]}'

probe "GET /v1/prices/latest no key" "https://api.cs2.sh/v1/prices/latest"

probe "POST /v1/prices/history no key" -X POST "https://api.cs2.sh/v1/prices/history" \
  -H "Content-Type: application/json" \
  -d '{"items":["AK-47 | Redline (Field-Tested)"],"start":"2026-05-01","interval":"1d"}'

probe "GET /v1/market/buff/latest no key" "https://api.cs2.sh/v1/market/buff/latest"

probe "POST /v1/market/buff/history no key" -X POST "https://api.cs2.sh/v1/market/buff/history" \
  -H "Content-Type: application/json" \
  -d '{"items":["AK-47 | Redline (Field-Tested)"],"start":"2026-05-01"}'

probe "GET /internal/health" "https://api.cs2.sh/internal/health"
probe "GET /api/v1/buff" "https://api.cs2.sh/api/v1/buff"
probe "GET /buff163/market" "https://api.cs2.sh/buff163/market/goods"
probe "GET /graphql" -X POST "https://api.cs2.sh/graphql" -H "Content-Type: application/json" -d '{}'
probe "OPTIONS /v1/prices/latest" -X OPTIONS "https://api.cs2.sh/v1/prices/latest" \
  -H "Origin: https://evil.example" \
  -H "Access-Control-Request-Method: POST"
