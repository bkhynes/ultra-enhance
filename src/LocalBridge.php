<?php

declare(strict_types=1);

final class LocalBridge
{
    public function __construct(private readonly Config $config)
    {
        foreach ([$this->inbox(), $this->outbox()] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }

    public function inbox(): string
    {
        return $this->config->root . '/local/inbox';
    }

    public function outbox(): string
    {
        return $this->config->root . '/local/out';
    }

    /**
     * Park a still + prompt for ComfyUI / Draw Things / RunPod.
     * Nothing is sent off-box.
     *
     * @return array{ok:bool,kind:string,engine:string,file:?string,orig:?string,error:?string,url:?string,orig_url:?string,job_id:?string}
     */
    public function queueUpload(array $file, string $prompt): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->fail('Upload failed');
        }
        $tmp = $file['tmp_name'] ?? '';
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            return $this->fail('Use a JPEG, PNG or WebP');
        }

        $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $still = $this->inbox() . '/' . $id . '.' . $allowed[$mime];
        $note = $this->inbox() . '/' . $id . '.txt';
        if (!move_uploaded_file($tmp, $still)) {
            return $this->fail('Could not save into local/inbox');
        }
        $prompt = trim($prompt);
        if ($prompt === '') {
            $prompt = 'Natural motion from the reference still, keep identity and body, realistic skin, cinematic light.';
        }
        file_put_contents($note, $prompt . PHP_EOL);

        $preview = $this->config->outputDir . '/' . $id . '-orig.jpg';
        $data = @file_get_contents($still);
        $im = $data !== false ? @imagecreatefromstring($data) : false;
        if ($im) {
            imagejpeg($im, $preview, 90);
            unset($im);
        }

        return [
            'ok' => true,
            'kind' => 'local',
            'engine' => 'local-inbox',
            'job_id' => $id,
            'file' => null,
            'orig' => is_file($preview) ? basename($preview) : null,
            'url' => null,
            'orig_url' => is_file($preview) ? '/file.php?f=' . rawurlencode(basename($preview)) : null,
            'error' => null,
            'inbox_file' => basename($still),
            'hint' => 'Queued in local/inbox. Point ComfyUI Load Image at that file (or drop it on the Draw Things canvas). Save the MP4 into local/out as ' . $id . '.mp4 then refresh.',
        ];
    }

    /** @return list<array{id:string,video:?string,url:?string}> */
    public function recentOut(): array
    {
        $rows = [];
        foreach (glob($this->outbox() . '/*.{mp4,webm,mov,MP4,WEBM,MOV}', GLOB_BRACE) ?: [] as $path) {
            $base = pathinfo($path, PATHINFO_FILENAME);
            $name = basename($path);
            $copy = $this->config->outputDir . '/' . $base . '-video.mp4';
            if (!is_file($copy) || filemtime($copy) < filemtime($path)) {
                @copy($path, $copy);
            }
            $served = is_file($copy) ? basename($copy) : null;
            $rows[] = [
                'id' => $base,
                'video' => $name,
                'url' => $served ? '/file.php?f=' . rawurlencode($served) : null,
            ];
        }
        usort($rows, fn ($a, $b) => strcmp($b['id'], $a['id']));
        return array_slice($rows, 0, 12);
    }

    /** @return array{ok:bool,kind:string,engine:string,file:?string,orig:?string,error:?string,url:?string,orig_url:?string,job_id:?string} */
    private function fail(string $msg): array
    {
        return [
            'ok' => false,
            'kind' => 'local',
            'engine' => '',
            'job_id' => null,
            'file' => null,
            'orig' => null,
            'url' => null,
            'orig_url' => null,
            'error' => $msg,
        ];
    }
}
