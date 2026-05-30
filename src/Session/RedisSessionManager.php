<?php

declare(strict_types=1);

namespace PhpUssd\Session;

use PhpUssd\Exceptions\SessionException;
use Redis;

/**
 * Redis-backed session driver.
 *
 * Requires the phpredis extension (pecl install redis).
 * Each session is stored as a single JSON-encoded Redis string
 * with an automatic TTL so stale sessions expire without cron.
 */
class RedisSessionManager extends AbstractSessionManager
{
    private Redis  $redis;
    private int    $ttl;
    private string $prefix;

    public function __construct(Redis $redis, int $ttl = 300, string $prefix = 'ussd:session:')
    {
        $this->redis  = $redis;
        $this->ttl    = $ttl;
        $this->prefix = $prefix;
    }

    public function destroy(): void
    {
        $this->redis->del($this->redisKey($this->sessionId));
        $this->data   = [];
        $this->loaded = false;
        $this->dirty  = false;
    }

    protected function read(string $sessionId): array
    {
        $raw = $this->redis->get($this->redisKey($sessionId));

        if ($raw === false || $raw === null) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw SessionException::failedToRead($sessionId, 'JSON decode: ' . json_last_error_msg());
        }

        return $decoded ?? [];
    }

    protected function write(string $sessionId, array $data): void
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            throw SessionException::failedToWrite($sessionId, 'JSON encode: ' . json_last_error_msg());
        }

        $this->redis->setex($this->redisKey($sessionId), $this->ttl, $encoded);
    }

    private function redisKey(string $sessionId): string
    {
        return $this->prefix . $sessionId;
    }
}
