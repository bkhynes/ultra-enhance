# Ultra Enhance

PHP web app that upscales photos toward an ultra-realistic look.

- Mac runs PHP.
- iPhone uses Safari on the same Wi-Fi.
- Clarity or Real-ESRGAN via Replicate.
- Before/after slider after each run.
- Folder batch for overnight jobs.

## Quick start (Mac)

```bash
git clone https://github.com/bkhynes/ultra-enhance.git
cd ultra-enhance
chmod +x start.sh
./start.sh
```

- Mac: http://127.0.0.1:8080
- iPhone: http://YOUR-LAN-IP:8080 (`ipconfig getifaddr en0`)

Pull latest after this update:

```bash
cd ultra-enhance && git pull
```

## Replicate (the real quality)

```
REPLICATE_API_TOKEN=r8_xxxxxxxx
UPSCALE_SCALE=2
UPSCALE_MODEL=clarity
```

Models in the UI:

- **Clarity** — skin, hair, photo realism
- **Real-ESRGAN** — cleaner hard upscale + face enhance

## Overnight batch

Drop JPEGs into `inbox/` then:

```bash
php bin/batch.php --watch
```

Or one-shot a folder:

```bash
php bin/batch.php ~/Pictures/van-shots ~/Pictures/van-shots-enhanced
```

Processed files get a `.done` sidecar so reruns skip them. Output lands in `output/` unless you pass a second path.
