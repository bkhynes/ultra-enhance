#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
PORT="${PORT:-8080}"
if [[ ! -f .env && -f .env.example ]]; then
  cp .env.example .env
  echo "Created .env from .env.example — add REPLICATE_API_TOKEN for AI quality."
fi
IP="$(ipconfig getifaddr en0 2>/dev/null || true)"
echo "Mac:     http://127.0.0.1:${PORT}"
if [[ -n "${IP}" ]]; then
  echo "iPhone:  http://${IP}:${PORT}  (same Wi-Fi)"
fi
exec php -S 0.0.0.0:"${PORT}" -t public
