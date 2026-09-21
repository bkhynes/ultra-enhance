<?php

declare(strict_types=1);

final class Enhancer
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return array{ok:bool,engine:string,file:?string,orig:?string,error:?string,url:?string,orig_url:?string}
     */
    public function processUploaded(array $file, ?string $modelOverride = null, ?int $scaleOverride = null): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->fail('Upload failed (code ' . ($file['error'] ?? '?') . ')');
        }
        if (($file['size'] ?? 0) > $this->config->maxBytes) {
            return $this->fail('File is over the size limit');
        }

        $tmp = $file['tmp_name'] ?? '';
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            return $this->fail('Use a JPEG, PNG or WebP');
        }

        $id = bin2hex(random_bytes(8));
        $in = $this->config->uploadDir . '/' . $id . '.' . $allowed[$mime];
        $orig = $this->config->outputDir . '/' . $id . '-orig.jpg';
        $out = $this->config->outputDir . '/' . $id . '-enhanced.jpg';

        if (!move_uploaded_file($tmp, $in)) {
            return $this->fail('Could not save upload');
        }

        $this->saveJpegCopy($in, $orig);

        $model = $this->normaliseModel($modelOverride ?? $this->config->model);
        $scale = $scaleOverride ? max(2, min(4, $scaleOverride)) : $this->config->scale;

        try {
            if ($this->config->hasReplicate()) {
                $client = new ReplicateClient($this->config->replicateToken);
                $client->upscale($in, $out, $scale, $model);
                $engine = 'replicate:' . $model;
            } else {
                (new LocalEnhancer())->enhance($in, $out, $scale);
                $engine = extension_loaded('imagick') ? 'imagick' : 'gd';
            }
        } catch (Throwable $e) {
            return $this->fail($e->getMessage());
        }

        if (!is_file($out)) {
            return $this->fail('Enhancer produced no file');
        }

        return [
            'ok' => true,
            'engine' => $engine,
            'file' => basename($out),
            'orig' => basename($orig),
            'url' => '/file.php?f=' . rawurlencode(basename($out)),
            'orig_url' => '/file.php?f=' . rawurlencode(basename($orig)),
            'error' => null,
        ];
    }

    public function processPath(string $src, string $destDir, ?string $modelOverride = null, ?int $scaleOverride = null): string
    {
        if (!is_file($src)) {
            throw new RuntimeException('Missing file: ' . $src);
        }
        if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new RuntimeException('Cannot create ' . $destDir);
        }

        $base = pathinfo($src, PATHINFO_FILENAME);
        $out = rtrim($destDir, '/') . '/' . $base . '-enhanced.jpg';
        $model = $this->normaliseModel($modelOverride ?? $this->config->model);
        $scale = $scaleOverride ? max(2, min(4, $scaleOverride)) : $this->config->scale;

        if ($this->config->hasReplicate()) {
            (new ReplicateClient($this->config->replicateToken))->upscale($src, $out, $scale, $model);
        } else {
            (new LocalEnhancer())->enhance($src, $out, $scale);
        }
        return $out;
    }

    public function normaliseModel(string $model): string
    {
        $model = strtolower(trim($model));
        return in_array($model, ['clarity', 'realesrgan'], true) ? $model : 'clarity';
    }

    private function saveJpegCopy(string $src, string $dest): void
    {
        $data = @file_get_contents($src);
        if ($data === false) {
            throw new RuntimeException('Could not read upload for preview');
        }
        $im = @imagecreatefromstring($data);
        if ($im === false) {
            copy($src, $dest);
            return;
        }
        imagejpeg($im, $dest, 90);
        unset($im);
    }

    /** @return array{ok:bool,engine:string,file:?string,orig:?string,error:?string,url:?string,orig_url:?string} */
    private function fail(string $msg): array
    {
        return ['ok' => false, 'engine' => '', 'file' => null, 'orig' => null, 'url' => null, 'orig_url' => null, 'error' => $msg];
    }
}
