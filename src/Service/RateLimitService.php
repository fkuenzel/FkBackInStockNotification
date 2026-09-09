<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Service;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Simple fixed-window rate limiter backed by the configured cache pool
 * (Redis when configured, filesystem cache otherwise). Each counter stores
 * its own reset timestamp so the window stays stable across increments.
 */
class RateLimitService
{
    private const KEY_PREFIX = 'bisn_rate_limit_';

    public function __construct(
        private readonly CacheItemPoolInterface $cache
    ) {
    }

    /**
     * Returns true when the identifier has reached or exceeded the given limit
     * within the current window.
     */
    public function isLimitExceeded(string $identifier, int $limit): bool
    {
        return $this->currentCount($identifier) >= $limit;
    }

    /**
     * Increments the counter for the identifier, starting a new window of
     * $windowSeconds when none is active yet.
     */
    public function incrementCounter(string $identifier, int $windowSeconds): void
    {
        $item = $this->cache->getItem($this->key($identifier));
        $now = time();

        /** @var array{count: int, reset: int}|null $data */
        $data = $item->isHit() ? $item->get() : null;

        if ($data === null || $data['reset'] <= $now) {
            $data = ['count' => 1, 'reset' => $now + $windowSeconds];
            $item->expiresAfter($windowSeconds);
        } else {
            $data['count']++;
            $item->expiresAfter(max(1, $data['reset'] - $now));
        }

        $item->set($data);
        $this->cache->save($item);
    }

    /**
     * Returns how many requests are still allowed for the identifier before the
     * limit is reached (never negative).
     */
    public function getRemaining(string $identifier, int $limit): int
    {
        return max(0, $limit - $this->currentCount($identifier));
    }

    private function currentCount(string $identifier): int
    {
        $item = $this->cache->getItem($this->key($identifier));

        if (!$item->isHit()) {
            return 0;
        }

        /** @var array{count: int, reset: int} $data */
        $data = $item->get();

        if ($data['reset'] <= time()) {
            return 0;
        }

        return $data['count'];
    }

    private function key(string $identifier): string
    {
        return self::KEY_PREFIX . sha1($identifier);
    }
}
