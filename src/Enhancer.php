<?php

declare(strict_types=1);

final class Enhancer
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @return array{ok:bool,engine:string,file:?string,error:?string,url:?string}
     */
    public function processUploaded(array $file): array
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
        $out = $this->config->outputDir . '/' . $id . '-enhanced.jpg';

        if (!move_uploaded_file($tmp, $in)) {
            return $this->fail('Could not save upload');
        }

        try {
            if ($this->config->hasReplicate()) {
                $client = new ReplicateClient($this->config->replicateToken);
                $client->upscale($in, $out, $this->config->scale, $this->config->model);
                $engine = 'replicate:' . $this->config->model;
            } else {
                (new LocalEnhancer())->enhance($in, $out, $this->config->scale);
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
            'url' => '/download.php?f=' . rawurlencode(basename($out)),
            'error' => null,
        ];
    }

    /** @return array{ok:bool,engine:string,file:?string,error:?string,url:?string} */
    private function fail(string $msg): array
    {
        return ['ok' => false, 'engine' => '', 'file' => null, 'url' => null, 'error' => $msg];
    }
}
