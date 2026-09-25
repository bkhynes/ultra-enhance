# Ultra Enhance

PHP app for ultra-real stills and Imagine-style clips from a reference photo.

- Mac runs PHP. iPhone uses Safari on the same Wi-Fi.
- Enhance: Clarity or Real-ESRGAN (Replicate) or local GD (`./start.sh --free`).
- Video: Replicate engines, or queue stills for ComfyUI / RunPod via the Local tab.

## Quick start

```bash
cd ultra-enhance && git pull
./start.sh --free
```

Add to `.env` for AI quality / hosted video:

```
REPLICATE_API_TOKEN=r8_xxxxxxxx
```

## Publish the GitHub repo (source only)

The repo is **private**. I cannot flip that from here. You can:

1. Open https://github.com/bkhynes/ultra-enhance/settings
2. Danger Zone → **Change visibility** → Public
3. Confirm

`.env`, `uploads/`, `output/`, and `local/` media stay gitignored. Public = **code**, not your photos — as long as you never `git add` those folders.

Do **not** put this on a public URL without a password. `file.php` serves anything in `output/`. Anyone with the link can see nudes you generated.

## Optional: Docker (Railway / Fly / a VPS)

```bash
docker build -t ultra-enhance .
docker run --rm -p 8080:8080 -e REPLICATE_API_TOKEN=r8_xxx ultra-enhance
```

Set `REPLICATE_API_TOKEN` as a host secret. Do not bake the token into the image.

For iPhone-from-anywhere you want a **private** tunnel (Cloudflare Tunnel, Tailscale Funnel) to the Mac, not an open website.
