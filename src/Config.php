<?php

declare(strict_types=1);

final class Config
{
    public readonly string $root;
    public readonly string $uploadDir;
    public readonly string $outputDir;
    public readonly ?string $replicateToken;
    public readonly int $scale;
    public readonly string $model;
    public readonly int $maxBytes;
    public readonly string $appToken;
    public readonly bool $freeMode;

    public function __construct(string $root)
    {
        $this->root = $root;
        $this->loadEnv($root . '/.env');

        $this->uploadDir = $root . '/uploads';
        $this->outputDir = $root . '/output';
        $this->freeMode = $this->truthy($this->env('ULTRA_FREE')) || $this->truthy($this->env('FREE_MODE'));
        $token = $this->env('REPLICATE_API_TOKEN');
        $this->replicateToken = ($this->freeMode || $token === '') ? null : $token;
        $this->scale = max(2, min(4, (int) ($this->env('UPSCALE_SCALE') ?: 2)));
        $this->model = $this->env('UPSCALE_MODEL') ?: 'clarity';
        $mb = max(1, (int) ($this->env('MAX_UPLOAD_MB') ?: 20));
        $this->maxBytes = $mb * 1024 * 1024;
        $this->appToken = $this->env('APP_TOKEN') ?: '';

        foreach ([$this->uploadDir, $this->outputDir] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }

    public function hasReplicate(): bool
    {
        return is_string($this->replicateToken) && $this->replicateToken !== '';
    }

    private function truthy(string $v): bool
    {
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    private function env(string $key): string
    {
        $v = $_ENV[$key] ?? getenv($key);
        return is_string($v) ? trim($v) : '';
    }

    private function loadEnv(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\"'");
            if ($k === 'ULTRA_FREE' && getenv('ULTRA_FREE')) {
                continue;
            }
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
}
