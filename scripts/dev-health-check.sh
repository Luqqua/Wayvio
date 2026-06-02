#!/usr/bin/env bash
set -u

WAYVIO_MAIN_DIR="${WAYVIO_MAIN_DIR:-/Users/luca/projects/wayvio-all/wayvio-main}"
INTERNAL_APIS_DIR="${INTERNAL_APIS_DIR:-/Users/luca/projects/wayvio-all/internal-apis}"
WEB_URL="${WEB_URL:-http://localhost:8888/}"
ANALYTICS_BASE="${ANALYTICS_BASE:-http://127.0.0.1:8001}"
MAMP_START_SCRIPT="${MAMP_START_SCRIPT:-/Applications/MAMP/bin/startNginx.sh}"
ANALYTICS_LOG_FILE="${ANALYTICS_LOG_FILE:-$INTERNAL_APIS_DIR/internal-apis.log}"

log() {
  printf '[dev-health] %s\n' "$*"
}

warn() {
  printf '[dev-health][warn] %s\n' "$*"
}

fail() {
  printf '[dev-health][error] %s\n' "$*"
  exit 1
}

http_code() {
  local url="$1"
  curl -sS -o /dev/null -w '%{http_code}' --max-time 5 "$url" 2>/dev/null || printf '000'
}

extract_env_value() {
  local file="$1"
  local key="$2"
  if [ ! -f "$file" ]; then
    return 1
  fi

  sed -n "s/^${key}=//p" "$file" | head -n 1 | tr -d '\r'
}

ensure_mamp_php() {
  if pgrep -f '/Applications/MAMP/bin/php/.*/bin/php-cgi' >/dev/null 2>&1; then
    log 'MAMP php-cgi is running.'
    return 0
  fi

  warn 'MAMP php-cgi not running. Starting MAMP Nginx/FastCGI...'
  [ -x "$MAMP_START_SCRIPT" ] || fail "Missing executable: $MAMP_START_SCRIPT"

  if ! "$MAMP_START_SCRIPT" >/tmp/wayvio_mamp_start.log 2>&1; then
    tail -n 20 /tmp/wayvio_mamp_start.log 2>/dev/null || true
    fail 'Failed to start MAMP Nginx/FastCGI.'
  fi

  sleep 1
  pgrep -f '/Applications/MAMP/bin/php/.*/bin/php-cgi' >/dev/null 2>&1 || fail 'MAMP php-cgi still not running after restart.'
  log 'MAMP Nginx/FastCGI restarted successfully.'
}

ensure_web() {
  ensure_mamp_php

  local code
  code="$(http_code "$WEB_URL")"

  if [ "$code" = '502' ] || [ "$code" = '000' ]; then
    warn "Web check returned $code. Restarting MAMP Nginx/FastCGI..."
    [ -x "$MAMP_START_SCRIPT" ] || fail "Missing executable: $MAMP_START_SCRIPT"

    if ! "$MAMP_START_SCRIPT" >/tmp/wayvio_mamp_start.log 2>&1; then
      tail -n 20 /tmp/wayvio_mamp_start.log 2>/dev/null || true
      fail 'Failed to restart MAMP Nginx/FastCGI for web recovery.'
    fi

    sleep 1
    code="$(http_code "$WEB_URL")"
  fi

  if [ "$code" -ge 200 ] && [ "$code" -lt 500 ]; then
    log "Web reachable: $WEB_URL (HTTP $code)"
  else
    fail "Web not healthy: $WEB_URL (HTTP $code)"
  fi
}

start_analytics_if_needed() {
  if lsof -nP -iTCP:8001 -sTCP:LISTEN >/dev/null 2>&1; then
    log 'Analytics port 8001 is listening.'
    return 0
  fi

  warn 'Analytics port 8001 is not listening. Starting internal-apis service...'
  [ -d "$INTERNAL_APIS_DIR" ] || fail "Missing directory: $INTERNAL_APIS_DIR"

  (
    cd "$INTERNAL_APIS_DIR" || exit 1
    nohup npm start >"$ANALYTICS_LOG_FILE" 2>&1 &
    echo $! > .internal-apis.pid
  )

  sleep 2
  lsof -nP -iTCP:8001 -sTCP:LISTEN >/dev/null 2>&1 || {
    tail -n 30 "$ANALYTICS_LOG_FILE" 2>/dev/null || true
    fail 'Analytics did not start on port 8001.'
  }

  log 'Analytics service started.'
}

ensure_analytics_health() {
  local analytics_key
  analytics_key="$(extract_env_value "$WAYVIO_MAIN_DIR/.env" 'ANALYTICS_INTERNAL_API_KEY')"

  if [ -z "$analytics_key" ]; then
    analytics_key="$(extract_env_value "$INTERNAL_APIS_DIR/.env" 'ANALYTICS_INTERNAL_API_KEY')"
  fi

  [ -n "$analytics_key" ] || fail 'Missing ANALYTICS_INTERNAL_API_KEY in .env files.'

  start_analytics_if_needed

  local health_code
  health_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 5 -H "X-API-KEY: $analytics_key" "$ANALYTICS_BASE/health" 2>/dev/null || printf '000')"

  if [ "$health_code" = '200' ]; then
    log "Analytics health OK: $ANALYTICS_BASE/health (HTTP 200)"
    return 0
  fi

  if [ "$health_code" = '401' ]; then
    fail 'Analytics health returned 401 (internal API key mismatch).'
  fi

  fail "Analytics health failed: $ANALYTICS_BASE/health (HTTP $health_code)"
}

main() {
  log 'Running Wayvio dev health checks...'
  ensure_web
  ensure_analytics_health
  log 'All checks passed.'
}

main "$@"
