# Ultra Enhance

PHP web app that upscales and enhances photos toward an ultra-realistic look.

- **Mac:** run PHP locally, drop images in the browser.
- **iPhone:** open the same site in Safari on your home network (or via a tunnel).

True “AI ultra-real” quality uses [Replicate](https://replicate.com) (Clarity Upscaler / Real-ESRGAN).  
If no API key is set, it still sharpens, lifts contrast and upscales with Imagick or GD so the app works offline.

## Quick start (Mac)

```bash
git clone https://github.com/bkhynes/ultra-enhance.git
cd ultra-enhance
cp .env.example .env
# optional: paste your Replicate token into .env
php -S 0.0.0.0:8080 -t public
```

Then:

- Mac: http://localhost:8080
- iPhone (same Wi-Fi): http://YOUR-MAC-LAN-IP:8080  
  Find the IP: `ipconfig getifaddr en0`

Keep the Mac awake while you enhance from the phone.

### Replicate (recommended)

1. Create an account at https://replicate.com  
2. Copy an API token  
3. Put it in `.env`:

```
REPLICATE_API_TOKEN=r8_xxxxxxxx
UPSCALE_SCALE=2
```

Without a token the app uses local Imagick/GD enhancement only.

## iPhone tips

- Use Safari (best file picker for Photos).
- For access off the home network, run `ngrok http 8080` on the Mac and open the ngrok URL on the phone.
- Large originals: scale 2× is safer than 4× on a laptop.

## What it does

1. Accepts JPEG / PNG / WebP (max 20 MB).
2. If Replicate is configured, sends the image to Clarity Upscaler (photorealistic, good on skin and hair).
3. Otherwise runs a local pipeline: resize → unsharp mask → contrast / saturation lift → JPEG output.
4. Lets you download the result.

## Project layout

```
public/          web root (index + assets)
src/             enhancer + Replicate client
uploads/         incoming (gitignored)
output/          processed (gitignored)
```

PHP 8.1+ recommended. Imagick is optional but better than GD.
