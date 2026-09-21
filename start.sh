#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
PORT="${PORT:-8080}"
FREE=0
for arg in "$@"; do
  case "$arg" in
    --free|-f|free) FREE=1 ;;
    --port=*) PORT="${arg#--port=}" ;;
    --help|-h)
      echo "Usage: ./start.sh [--free] [--port=8080]"
      echo "  --free   Local GD/Imagick only. Ignores REPLICATE_API_TOKEN."
      exit 0
      ;;
  esac
done
if [[ ! -f .env && -f .env.example ]]; then
  cp .env.example .env
  echo "Created .env from .env.example — add REPLICATE_API_TOKEN for AI quality."
fi
IP="$(ipconfig getifaddr en0 2>/dev/null || true)"
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
exec php -S 0.0.0.0:"${PORT}" -t public
