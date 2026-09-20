<?php

declare(strict_types=1);

final class ReplicateClient
{
    private const API = 'https://api.replicate.com/v1/predictions';

    public function __construct(private readonly string $token)
    {
    }

    /**
     * @return string local path of downloaded enhanced image
     */
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
            // philz1337x/clarity-upscaler — strong on skin, hair, photo realism
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

        $url = $created['urls']['get'] ?? null;
        if (!$url) {
            throw new RuntimeException('Replicate did not return a prediction URL');
        }

        $deadline = time() + 240;
        $prediction = $created;
        while (time() < $deadline) {
            $status = $prediction['status'] ?? '';
            if ($status === 'succeeded') {
                $out = $prediction['output'] ?? null;
                $fileUrl = is_array($out) ? ($out[0] ?? null) : $out;
                if (!is_string($fileUrl) || $fileUrl === '') {
                    throw new RuntimeException('Replicate succeeded but returned no file');
                }
                $this->download($fileUrl, $outputPath);
                return $outputPath;
            }
            if (in_array($status, ['failed', 'canceled'], true)) {
                $err = $prediction['error'] ?? $status;
                throw new RuntimeException('Replicate failed: ' . $err);
            }
            usleep(1500000);
            $prediction = $this->request('GET', $url);
        }

        throw new RuntimeException('Replicate timed out after 4 minutes');
    }

    private function toDataUri(string $path): string
    {
        $mime = mime_content_type($path) ?: 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
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
            throw new RuntimeException('Could not download enhanced image');
        }
        if (file_put_contents($dest, $data) === false) {
            throw new RuntimeException('Could not write output file');
        }
    }
}
