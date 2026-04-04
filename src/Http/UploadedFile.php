<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\Http;

use RuntimeException;

class UploadedFile
{
    private string $tmpPath;
    private string $originalName;
    private int $size;
    private int $error;

    public function __construct(array $fileData)
    {
        $this->tmpPath = $fileData['tmp_name'] ?? '';
        $this->originalName = $fileData['name'] ?? '';
        $this->size = (int) ($fileData['size'] ?? 0);
        $this->error = (int) ($fileData['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    public function originalName(): string
    {
        return $this->sanitizeName($this->originalName);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function mimeType(): string
    {
        if (!file_exists($this->tmpPath)) {
            return '';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        return $finfo->file($this->tmpPath) ?: '';
    }

    public function size(): int
    {
        return $this->size;
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && file_exists($this->tmpPath);
    }

    public function validateType(array $allowedMimes): bool
    {
        return in_array($this->mimeType(), $allowedMimes, true);
    }

    public function validateMaxSize(int $maxBytes): bool
    {
        return $this->size <= $maxBytes;
    }

    public function moveTo(string $targetPath): void
    {
        if (!$this->isValid()) {
            throw new RuntimeException('Cannot move invalid upload.');
        }

        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (is_uploaded_file($this->tmpPath)) {
            move_uploaded_file($this->tmpPath, $targetPath);
        } else {
            rename($this->tmpPath, $targetPath);
        }
    }

    private function sanitizeName(string $name): string
    {
        // Remove path traversal
        $name = basename($name);
        // Remove null bytes
        $name = str_replace("\0", '', $name);
        // Replace dangerous characters
        $name = preg_replace('/[^\w\.\-]/', '_', $name);

        return $name;
    }
}
