<?php

declare(strict_types=1);

final class VideoGenerator
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return array{ok:bool,engine:string,file:?string,orig:?string,error:?string,url:?string,orig_url:?string,kind:string}
     */
    public function fromUpload(array $file, string $prompt, int $duration = 15, string $engine = 'kling3'): array
    {
        if (!$this->config->hasReplicate()) {
            return $this->fail('Video needs a REPLICATE_API_TOKEN in .env');
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->fail('Upload failed');
        }
        if (($file['size'] ?? 0) > $this->config->maxBytes) {
            return $this->fail('File is over the size limit');
        }

        $tmp = $file['tmp_name'] ?? '';
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            return $this->fail('Use a JPEG, PNG or WebP as the reference frame');
        }

        $engine = ReplicateClient::videoEngine($engine);
        $id = bin2hex(random_bytes(8));
        $in = $this->config->uploadDir . '/' . $id . '.' . $allowed[$mime];
        $orig = $this->config->outputDir . '/' . $id . '-orig.jpg';
        $out = $this->config->outputDir . '/' . $id . '-video.mp4';

        if (!move_uploaded_file($tmp, $in)) {
            return $this->fail('Could not save upload');
        }

        $data = @file_get_contents($in);
        $im = $data !== false ? @imagecreatefromstring($data) : false;
        if ($im) {
            imagejpeg($im, $orig, 90);
            unset($im);
        } else {
            copy($in, $orig);
        }

        try {
            set_time_limit(0);
            (new ReplicateClient($this->config->replicateToken))->imageToVideo($in, $out, $prompt, $duration, $engine);
        } catch (Throwable $e) {
            return $this->fail($e->getMessage());
        }

        if (!is_file($out) || filesize($out) < 1000) {
            return $this->fail('Video job produced no file');
        }

        return [
            'ok' => true,
            'kind' => 'video',
            'engine' => 'replicate:' . $engine,
            'file' => basename($out),
            'orig' => basename($orig),
            'url' => '/file.php?f=' . rawurlencode(basename($out)),
            'orig_url' => '/file.php?f=' . rawurlencode(basename($orig)),
            'error' => null,
        ];
    }

    /** @return array{ok:bool,engine:string,file:?string,orig:?string,error:?string,url:?string,orig_url:?string,kind:string} */
    private function fail(string $msg): array
    {
        return [
            'ok' => false,
            'kind' => 'video',
            'engine' => '',
            'file' => null,
            'orig' => null,
            'url' => null,
            'orig_url' => null,
            'error' => $msg,
        ];
    }
}
