<?php

declare(strict_types=1);

final class ReplicateClient
{
    private const API = 'https://api.replicate.com/v1/predictions';

    /** @var array<string,array{label:string,model:string,max:int,nsfw:bool}> */
    public const VIDEO_ENGINES = [
        'wan22' => ['label' => 'Wan 2.2 Fast — uncensored I2V', 'model' => 'wan-video/wan-2.2-i2v-fast', 'max' => 5, 'nsfw' => true],
        'wan21' => ['label' => 'Wan 2.1 720p — uncensored I2V', 'model' => 'wavespeedai/wan-2.1-i2v-720p', 'max' => 5, 'nsfw' => true],
        'wan27' => ['label' => 'Wan 2.7 I2V — open, low filter', 'model' => 'wan-video/wan-2.7-i2v', 'max' => 15, 'nsfw' => true],
        'wanunc' => ['label' => 'Wan 2.1 Uncensored LoRA', 'model' => 'uncensored-com/wan2.1-uncensored-video-lora', 'max' => 5, 'nsfw' => true],
        'kling3' => ['label' => 'Kling 3.0 (filtered)', 'model' => 'kwaivgi/kling-v3-video', 'max' => 15, 'nsfw' => false],
        'pvideo' => ['label' => 'P-Video 2 Pro (filtered)', 'model' => 'prunaai/p-video-2-pro', 'max' => 15, 'nsfw' => false],
        'seedance' => ['label' => 'Seedance 2.0 (filtered)', 'model' => 'bytedance/seedance-2.0', 'max' => 15, 'nsfw' => false],
        'hailuo' => ['label' => 'Hailuo 02 (filtered)', 'model' => 'minimax/hailuo-02', 'max' => 10, 'nsfw' => false],
        'kling21' => ['label' => 'Kling 2.1 Master (filtered)', 'model' => 'kwaivgi/kling-v2.1-master', 'max' => 10, 'nsfw' => false],
    ];

    public function __construct(private readonly string $token)
    {
    }

    public static function videoEngine(string $id): string
    {
        return isset(self::VIDEO_ENGINES[$id]) ? $id : 'wan22';
    }

    public function upscale(string $imagePath, string $outputPath, int $scale, string $model): string
    {
        $dataUri = $this->toDataUri($imagePath);

        if ($model === 'realesrgan') {
            $slug = 'nightmareai/real-esrgan';
            $input = [
                'image' => $dataUri,
                'scale' => $scale,
                'face_enhance' => true,
            ];
        } else {
            $slug = 'philz1337x/clarity-upscaler';
            $input = [
                'image' => $dataUri,
                'scale_factor' => (float) $scale,
                'dynamic' => 6,
                'creativity' => 0.35,
                'resemblance' => 0.6,
                'fractality' => 0.0,
                'sharpen' => 0,
            ];
        }

        $version = $this->latestVersion($slug);
        $created = $this->request('POST', self::API, [
            'version' => $version,
            'input' => $input,
        ]);
        return $this->awaitFile($created, $outputPath, 240);
    }

    public function imageToVideo(string $imagePath, string $outputPath, string $prompt, int $duration = 15, string $engine = 'wan22'): string
    {
        $engine = self::videoEngine($engine);
        $meta = self::VIDEO_ENGINES[$engine];
        $dataUri = $this->toDataUri($imagePath);
        $duration = max(2, min($meta['max'], $duration));
        $prompt = trim($prompt);
        if ($prompt === '') {
            $prompt = 'Natural body motion from the reference photo, realistic skin, keep identity and pose, cinematic light.';
        }

        $input = match ($engine) {
            'wan22' => [
                'image' => $dataUri,
                'prompt' => $prompt,
                'go_fast' => false,
                'disable_safety_checker' => true,
            ],
            'wan21' => [
                'image' => $dataUri,
                'prompt' => $prompt,
                'disable_safety_checker' => true,
            ],
            'wan27' => [
                'image' => $dataUri,
                'prompt' => $prompt,
                'duration' => $duration,
                'disable_safety_checker' => true,
            ],
            'wanunc' => [
                'image' => $dataUri,
                'prompt' => 'unai, ' . $prompt,
                'disable_safety_checker' => true,
            ],
            'pvideo' => [
                'prompt' => $prompt,
                'image' => $dataUri,
                'duration' => $duration,
                'mode' => 'quality',
                'resolution' => '768p',
            ],
            'seedance' => [
                'prompt' => $prompt,
                'duration' => $duration,
                'resolution' => '720p',
                'generate_audio' => true,
                'reference_images' => [$dataUri],
            ],
            'hailuo' => [
                'prompt' => $prompt,
                'first_frame_image' => $dataUri,
                'duration' => min(10, $duration),
            ],
            'kling21' => [
                'prompt' => $prompt,
                'start_image' => $dataUri,
                'duration' => $duration >= 10 ? 10 : 5,
            ],
            default => [
                'prompt' => $prompt,
                'start_image' => $dataUri,
                'duration' => $duration,
                'mode' => 'pro',
                'generate_audio' => true,
            ],
        };

        $created = $this->request(
            'POST',
            'https://api.replicate.com/v1/models/' . $meta['model'] . '/predictions',
            ['input' => $input]
        );

        return $this->awaitFile($created, $outputPath, 600);
    }

    public function toDataUri(string $path): string
    {
        $mime = mime_content_type($path) ?: 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    private function latestVersion(string $slug): string
    {
        $model = $this->request('GET', 'https://api.replicate.com/v1/models/' . $slug);
        $id = $model['latest_version']['id'] ?? '';
        if (!is_string($id) || $id === '') {
            throw new RuntimeException('No latest version for ' . $slug);
        }
        return $id;
    }

    /** @param array<string,mixed> $created */
    private function awaitFile(array $created, string $outputPath, int $timeoutSec): string
    {
        $url = $created['urls']['get'] ?? null;
        if (!$url) {
            throw new RuntimeException('Replicate did not return a prediction URL');
        }

        $deadline = time() + $timeoutSec;
        $prediction = $created;
        while (time() < $deadline) {
            $status = $prediction['status'] ?? '';
            if ($status === 'succeeded') {
                $fileUrl = $this->firstUrl($prediction['output'] ?? null);
                if ($fileUrl === null) {
                    throw new RuntimeException('Replicate succeeded but returned no file');
                }
                $this->download($fileUrl, $outputPath);
                return $outputPath;
            }
            if (in_array($status, ['failed', 'canceled'], true)) {
                throw new RuntimeException('Replicate failed: ' . ($prediction['error'] ?? $status));
            }
            usleep(2000000);
            $prediction = $this->request('GET', $url);
        }

        throw new RuntimeException('Replicate timed out after ' . $timeoutSec . 's');
    }

    private function firstUrl(mixed $out): ?string
    {
        if (is_string($out) && str_starts_with($out, 'http')) {
            return $out;
        }
        if (is_array($out)) {
            foreach ($out as $item) {
                if (is_string($item) && str_starts_with($item, 'http')) {
                    return $item;
                }
                if (is_array($item) && isset($item['url']) && is_string($item['url'])) {
                    return $item['url'];
                }
            }
        }
        return null;
    }

    /** @return array<string,mixed> */
    private function request(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
            'Prefer: wait=60',
        ];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 90,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        unset($ch);

        if ($raw === false) {
            throw new RuntimeException('Replicate network error: ' . $err);
        }
        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            throw new RuntimeException('Replicate returned invalid JSON (HTTP ' . $code . ')');
        }
        if ($code >= 400) {
            $msg = $json['detail'] ?? $json['title'] ?? ('HTTP ' . $code);
            if (is_array($msg)) {
                $msg = json_encode($msg);
            }
            throw new RuntimeException('Replicate API: ' . $msg);
        }
        return $json;
    }

    private function download(string $url, string $dest): void
    {
        $data = file_get_contents($url);
        if ($data === false || $data === '') {
            throw new RuntimeException('Could not download result');
        }
        if (file_put_contents($dest, $data) === false) {
            throw new RuntimeException('Could not write output file');
        }
    }
}
