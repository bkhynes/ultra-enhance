#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
PORT="${PORT:-8080}"
SSL_PORT="${SSL_PORT:-8443}"
FREE=0
HTTPS=0
for arg in "$@"; do
  case "$arg" in
    --free|-f|free) FREE=1 ;;
    --https|--ssl) HTTPS=1 ;;
    --port=*) PORT="${arg#--port=}" ;;
    --ssl-port=*) SSL_PORT="${arg#--ssl-port=}" ;;
    --help|-h)
      echo "Usage: ./start.sh [--free] [--https] [--port=8080] [--ssl-port=8443]"
      echo "  --free    Local GD/Imagick only. Ignores REPLICATE_API_TOKEN."
      echo "  --https   Also serve TLS on 8443 (needs caddy: brew install caddy)"
      exit 0
      ;;
  esac
done
if [[ ! -f .env && -f .env.example ]]; then
  cp .env.example .env
  echo "Created .env from .env.example — add REPLICATE_API_TOKEN for AI quality."
fi
IP="$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || true)"
echo "Mac:     http://127.0.0.1:${PORT}"
if [[ -n "${IP}" ]]; then
  echo "iPhone:  http://${IP}:${PORT}  (same Wi-Fi)"
fi
if [[ "$FREE" -eq 1 ]]; then
  export ULTRA_FREE=1
  echo "Mode:    FREE (local enhance, no Replicate)"
else
  echo "Mode:    Replicate if REPLICATE_API_TOKEN is set"
fi

if [[ "$HTTPS" -eq 1 ]]; then
  if ! command -v caddy >/dev/null 2>&1; then
    echo "--https needs Caddy. Install with: brew install caddy"
    exit 1
  fi
  echo "HTTPS:   https://127.0.0.1:${SSL_PORT}"
  if [[ -n "${IP}" ]]; then
    echo "iPhone:  https://${IP}:${SSL_PORT}"
    echo "Safari will warn on the cert — tap Advanced → proceed."
  fi
  php -S 127.0.0.1:"${PORT}" -t public &
  PHP_PID=$!
  trap 'kill ${PHP_PID} 2>/dev/null || true' EXIT INT TERM
  exec caddy reverse-proxy --from ":${SSL_PORT}" --to "127.0.0.1:${PORT}"
fi

exec php -S 0.0.0.0:"${PORT}" -t public
