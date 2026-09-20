#!/usr/bin/env bash
# Fresh ComfyUI pod on RunPod (official templates).
#   export RUNPOD_API_KEY=rpa_...
#   ./bin/runpod-comfy.sh            # 4090 + CUDA 12.8
#   GPU=5090 ./bin/runpod-comfy.sh   # 5090 + CUDA 13
set -euo pipefail

if [[ -z "${RUNPOD_API_KEY:-}" ]]; then
  echo "Set RUNPOD_API_KEY first (RunPod console → Settings → API Keys)."
  exit 1
fi

GPU="${GPU:-4090}"
if [[ "$GPU" == "5090" ]]; then
  TEMPLATE="2lv7ev3wfp"   # ComfyUI - CUDA 13 / Blackwell
  GPU_ID="NVIDIA GeForce RTX 5090"
else
  TEMPLATE="cw3nka7d08"   # ComfyUI - CUDA 12.8
  GPU_ID="NVIDIA GeForce RTX 4090"
fi

NAME="ultra-enhance-wan-$(date +%y%m%d-%H%M)"

echo "Creating $NAME on $GPU_ID (template $TEMPLATE)..."
RESP="$(curl -sS -X POST https://rest.runpod.io/v1/pods \
  -H "Authorization: Bearer $RUNPOD_API_KEY" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"$NAME\",
    \"templateId\": \"$TEMPLATE\",
    \"gpuTypeIds\": [\"$GPU_ID\"],
    \"gpuCount\": 1,
    \"cloudType\": \"SECURE\",
    \"gpuCount\": 1,
    \"volumeInGb\": 80,
    \"containerDiskInGb\": 40,
    \"ports\": [\"8188/http\", \"8080/http\", \"8888/http\", \"22/tcp\"],
    \"startSsh\": true
  }")"

echo "$RESP" | python3 -c '
import json,sys
j=json.load(sys.stdin)
pid=j.get("id") or j.get("podId") or ""
print(json.dumps(j, indent=2)[:2000])
if pid:
    print("\nPod:", pid)
    print("Comfy (once green): https://%s-8188.proxy.runpod.net" % pid)
    print("Stop later: curl -X POST https://rest.runpod.io/v1/pods/%s/stop -H \"Authorization: Bearer $RUNPOD_API_KEY\"" % pid)
'
