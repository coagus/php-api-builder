<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http\Middleware;

final class RateLimitStore
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? sys_get_temp_dir() . '/php-api-builder-ratelimit';
    }

    public function hit(string $key, int $windowSeconds): array
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $file = $this->storagePath . '/' . md5($key);
        $handle = fopen($file, 'c+');
        flock($handle, LOCK_EX);

        $content = stream_get_contents($handle);
        $data = $content !== '' ? json_decode($content, true) : null;

        $now = time();

        if ($data === null || $data['reset_at'] <= $now) {
            $data = ['count' => 1, 'reset_at' => $now + $windowSeconds];
        } else {
            $data['count']++;
        }

        fseek($handle, 0);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($data));
        flock($handle, LOCK_UN);
        fclose($handle);

        return $data;
    }

    public function clear(string $key): void
    {
        $file = $this->storagePath . '/' . md5($key);

        if (file_exists($file)) {
            unlink($file);
        }
    }

    public function flush(): void
    {
        if (!is_dir($this->storagePath)) {
            return;
        }

        $files = glob($this->storagePath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
