<?php

declare(strict_types=1);

namespace PhpUssd\Session;

use PhpUssd\Exceptions\SessionException;

/**
 * Stores session data as JSON files on disk.
 *
 * Good for development and low-traffic deployments.
 * Use RedisSessionManager for production at scale.
 */
class FileSessionManager extends AbstractSessionManager
{
    private string $storagePath;
    private int    $ttl;

    public function __construct(string $storagePath, int $ttl = 300)
    {
        $this->storagePath = rtrim($storagePath, DIRECTORY_SEPARATOR);
        $this->ttl         = $ttl;

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function destroy(): void
    {
        $file = $this->filePath($this->sessionId);
        if (file_exists($file)) {
            unlink($file);
        }
        $this->data    = [];
        $this->loaded  = false;
        $this->dirty   = false;
    }

    protected function read(string $sessionId): array
    {
        $file = $this->filePath($sessionId);

        if (!file_exists($file)) {
            return [];
        }

        // Expire stale sessions
        if (filemtime($file) < (time() - $this->ttl)) {
            unlink($file);
            return [];
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            throw SessionException::failedToRead($sessionId, 'file_get_contents returned false');
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw SessionException::failedToRead($sessionId, 'JSON decode error: ' . json_last_error_msg());
        }

        return $decoded ?? [];
    }

    protected function write(string $sessionId, array $data): void
    {
        $file    = $this->filePath($sessionId);
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw SessionException::failedToWrite($sessionId, 'JSON encode error: ' . json_last_error_msg());
        }

        $result = file_put_contents($file, $encoded, LOCK_EX);
        if ($result === false) {
            throw SessionException::failedToWrite($sessionId, "Could not write to {$file}");
        }
    }

    private function filePath(string $sessionId): string
    {
        // Sanitise sessionId so it is always a safe filename
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $sessionId);
        return $this->storagePath . DIRECTORY_SEPARATOR . $safe . '.json';
    }
}
