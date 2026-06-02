#!/usr/bin/env bash
set -u

WAYVIO_MAIN_DIR="${WAYVIO_MAIN_DIR:-/Users/luca/projects/wayvio-all/wayvio-main}"
INTERNAL_APIS_DIR="${INTERNAL_APIS_DIR:-/Users/luca/projects/wayvio-all/internal-apis}"
WEB_URL="${WEB_URL:-http://localhost:8888/}"
ANALYTICS_BASE="${ANALYTICS_BASE:-http://127.0.0.1:8001}"

log() {
  printf '[prod-smoke] %s\n' "$*"
}

fail() {
  printf '[prod-smoke][error] %s\n' "$*"
  exit 1
}

http_code() {
  local url="$1"
  curl -sS -o /dev/null -w '%{http_code}' --max-time 8 "$url" 2>/dev/null || printf '000'
}

extract_env_value() {
  local file="$1"
  local key="$2"
  [ -f "$file" ] || return 1
  sed -n "s/^${key}=//p" "$file" | head -n 1 | tr -d '\r'
}

main() {
  local code
  code="$(http_code "$WEB_URL")"
  if [ "$code" != '200' ]; then
    fail "Web check failed: $WEB_URL => HTTP $code"
  fi
  log "Web check OK: $WEB_URL => HTTP 200"

  local api_key
  api_key="$(extract_env_value "$WAYVIO_MAIN_DIR/.env" 'ANALYTICS_INTERNAL_API_KEY')"
  if [ -z "$api_key" ]; then
    api_key="$(extract_env_value "$INTERNAL_APIS_DIR/.env" 'ANALYTICS_INTERNAL_API_KEY')"
  fi
  [ -n "$api_key" ] || fail 'ANALYTICS_INTERNAL_API_KEY missing in .env'

  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 8 -H "X-API-KEY: $api_key" "$ANALYTICS_BASE/health" 2>/dev/null || printf '000')"
  if [ "$code" != '200' ]; then
    fail "Internal API health failed: $ANALYTICS_BASE/health => HTTP $code"
  fi
  log "Internal API health OK: $ANALYTICS_BASE/health => HTTP 200"

  local payload
  payload=$(cat <<JSON
{"event_type":"view","site_id":1,"site_slug":"smoke-check","occurred_at":"$(date -u +%Y-%m-%dT%H:%M:%SZ)","tier_level":"business","metadata":{"source":"prod-smoke-check","ip":"127.0.0.1"}}
JSON
)

  code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 8 -H "X-API-KEY: $api_key" -H 'Content-Type: application/json' -d "$payload" "$ANALYTICS_BASE/api/event" 2>/dev/null || printf '000')"
  if [ "$code" != '202' ]; then
    fail "Analytics ingest failed: $ANALYTICS_BASE/api/event => HTTP $code (expected 202)"
  fi
  log "Analytics ingest OK: $ANALYTICS_BASE/api/event => HTTP 202"

  log 'All smoke checks passed.'
}

main "$@"
