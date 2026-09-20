# Ultra Enhance

PHP app for ultra-real stills and Imagine-style 15s clips from a reference photo.

- Mac runs PHP. iPhone uses Safari on the same Wi-Fi.
- Enhance: Clarity or Real-ESRGAN.
- Video: Kling 3.0 image-to-video on Replicate, 5 / 10 / 15 seconds + audio.

## Quick start

```bash
cd ultra-enhance && git pull
./start.sh
```

Add to `.env`:

```
REPLICATE_API_TOKEN=r8_xxxxxxxx
```

Video will not run without that token.

## 15s video

1. Switch the tab to **15s video**.
2. Upload a still (first frame).
3. Write a motion prompt like Imagine: camera, body movement, light.
4. Pick 5 / 10 / 15s. Wait. Download the MP4.

Jobs can take a few minutes. Keep the Mac awake and the PHP server running.
