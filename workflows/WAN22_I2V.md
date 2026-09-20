# Wan 2.2 I2V — local / RunPod

Official ComfyUI graph (drag into Comfy):

https://raw.githubusercontent.com/comfyanonymous/ComfyUI_examples/master/wan22/image_to_video_wan22_14B.json

5B (lighter, 8–12GB):

https://raw.githubusercontent.com/comfyanonymous/ComfyUI_examples/master/wan22/image_to_video_wan22_5B.json

## Weights

Put these on the GPU box (RunPod 4090/5090 is the play on a 2021 MBP).

**14B I2V (quality)** — `ComfyUI/models/diffusion_models/`

- `wan2.2_i2v_high_noise_14B_fp8_scaled.safetensors`
- `wan2.2_i2v_low_noise_14B_fp8_scaled.safetensors`

**Shared**

- `ComfyUI/models/text_encoders/umt5_xxl_fp8_e4m3fn_scaled.safetensors`
- `ComfyUI/models/vae/wan_2.1_vae.safetensors`

**Optional speed** — LightX2V 4-step LoRAs in `models/loras/`

**5B fallback** — `wan2.2_ti2v_5B_fp16.safetensors` + `wan2.2_vae.safetensors`

Hugging Face org: https://huggingface.co/Wan-AI

## RunPod

1. Template: ComfyUI (Kijai or official).
2. 4090 24GB for 14B fp8 + LightX2V. 5090 if you want 720p without sweating.
3. Drop the official 14B JSON on the canvas.
4. Load Image = the still from `ultra-enhance/local/inbox/`.
5. Prompt = motion only. Identity lives in the still.
6. Export MP4 into `ultra-enhance/local/out/` using the same job id as the inbox file.

No safety checker in this graph. Keep it on your pod / disk.

## Draw Things (Mac / iPhone)

1. Install Draw Things.
2. Download Wan 2.1/2.2 I2V (8-bit if the 2021 MBP wheezes).
3. Still on the canvas, prompt = motion.
4. Export clip into `local/out/`.

## Folder contract used by the PHP Local tab

```
local/inbox/<job-id>.jpg   reference still
local/inbox/<job-id>.txt   motion prompt
local/out/<job-id>.mp4     finished clip
```
