<?php

declare(strict_types=1);

final class ReplicateClient
{
    private const API = 'https://api.replicate.com/v1/predictions';

    /** @var array<string,array{label:string,model:string,max:int}> */
    public const VIDEO_ENGINES = [
        'kling3' => ['label' => 'Kling 3.0 (15s, audio)', 'model' => 'kwaivgi/kling-v3-video', 'max' => 15],
        'pvideo' => ['label' => 'P-Video 2 Pro (15s)', 'model' => 'prunaai/p-video-2-pro', 'max' => 15],
        'seedance' => ['label' => 'Seedance 2.0 (15s, audio)', 'model' => 'bytedance/seedance-2.0', 'max' => 15],
        'hailuo' => ['label' => 'MiniMax Hailuo 02', 'model' => 'minimax/hailuo-02', 'max' => 10],
        'kling21' => ['label' => 'Kling 2.1 Master (10s)', 'model' => 'kwaivgi/kling-v2.1-master', 'max' => 10],
    ];

    public function __construct(private readonly string $token)
    {
    }

    public static function videoEngine(string $id): string
    {
        return isset(self::VIDEO_ENGINES[$id]) ? $id : 'kling3';
    }

    public function upscale(string $imagePath, string $outputPath, int $scale, string $model): string
    {
        $dataUri = $this->toDataUri($imagePath);

        if ($model === 'realesrgan') {
            $version = 'f121d640bd400d416bfeb162a130f753b0f9c5b184c4d3c29472cb7161b3a5d';
            $input = [
                'image' => $dataUri,
                'scale' => $scale,
                'face_enhance' => true,
            ];
        } else {
            $version = 'dfad41775bf40bd70aa79aacb1ea9ea19084dba5d1215a528805f323fa6c94fb';
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

        $created = $this->request('POST', self::API, [
            'version' => $version,
            'input' => $input,
        ]);
        return $this->awaitFile($created, $outputPath, 240);
    }

    public function imageToVideo(string $imagePath, string $outputPath, string $prompt, int $duration = 15, string $engine = 'kling3'): string
    {
        $engine = self::videoEngine($engine);
        $meta = self::VIDEO_ENGINES[$engine];
        $dataUri = $this->toDataUri($imagePath);
        $duration = max(5, min($meta['max'], $duration));
        $prompt = trim($prompt);
        if ($prompt === '') {
            $prompt = 'Subtle natural motion, cinematic camera, ultra realistic, keep the subject and scene from the reference image.';
        }

        $input = match ($engine) {
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
        curl_close($ch);

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
