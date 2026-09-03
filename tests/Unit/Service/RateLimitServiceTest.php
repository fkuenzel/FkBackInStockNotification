<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Tests\Unit\Service;

use fKuenzel\BackInStockNotification\Service\RateLimitService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(\fKuenzel\BackInStockNotification\Service\RateLimitService::class)]
class RateLimitServiceTest extends TestCase
{
    private RateLimitService $service;

    protected function setUp(): void
    {
        $this->service = new RateLimitService($this->createInMemoryPool());
    }

    public function testUnknownIdentifierIsNotLimitedAndFullyRemaining(): void
    {
        static::assertFalse($this->service->isLimitExceeded('ip', 5));
        static::assertSame(5, $this->service->getRemaining('ip', 5));
    }

    public function testIncrementRaisesCountAndReducesRemaining(): void
    {
        $this->service->incrementCounter('ip', 3600);
        $this->service->incrementCounter('ip', 3600);

        static::assertSame(3, $this->service->getRemaining('ip', 5));
        static::assertFalse($this->service->isLimitExceeded('ip', 5));
    }

    public function testLimitIsExceededOnceCountReachesLimit(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->service->incrementCounter('ip', 3600);
        }

        static::assertTrue($this->service->isLimitExceeded('ip', 5));
        static::assertSame(0, $this->service->getRemaining('ip', 5));
    }

    public function testCountersAreIsolatedPerIdentifier(): void
    {
        $this->service->incrementCounter('ip-a', 3600);

        static::assertSame(1, $this->service->getRemaining('ip-a', 2));
        static::assertSame(2, $this->service->getRemaining('ip-b', 2));
    }

    private function createInMemoryPool(): CacheItemPoolInterface
    {
        return new class() implements CacheItemPoolInterface {
            /** @var array<string, mixed> */
            private array $store = [];

            public function getItem(string $key): CacheItemInterface
            {
                $hit = \array_key_exists($key, $this->store);

                return new class($key, $hit ? $this->store[$key] : null, $hit) implements CacheItemInterface {
                    public function __construct(
                        private readonly string $key,
                        private mixed $value,
                        private readonly bool $hit
                    ) {
                    }

                    public function getKey(): string
                    {
                        return $this->key;
                    }

                    public function get(): mixed
                    {
                        return $this->value;
                    }

                    public function isHit(): bool
                    {
                        return $this->hit;
                    }

                    public function set(mixed $value): static
                    {
                        $this->value = $value;

                        return $this;
                    }

                    public function expiresAt(?\DateTimeInterface $expiration): static
                    {
                        return $this;
                    }

                    public function expiresAfter(\DateInterval|int|null $time): static
                    {
                        return $this;
                    }
                };
            }

            public function save(CacheItemInterface $item): bool
            {
                $this->store[$item->getKey()] = $item->get();

                return true;
            }

            /**
             * @param string[] $keys
             *
             * @return iterable<string, CacheItemInterface>
             */
            public function getItems(array $keys = []): iterable
            {
                $items = [];
                foreach ($keys as $key) {
                    $items[$key] = $this->getItem($key);
                }

                return $items;
            }

            public function hasItem(string $key): bool
            {
                return \array_key_exists($key, $this->store);
            }

            public function clear(): bool
            {
                $this->store = [];

                return true;
            }

            public function deleteItem(string $key): bool
            {
                unset($this->store[$key]);

                return true;
            }

            /**
             * @param string[] $keys
             */
            public function deleteItems(array $keys): bool
            {
                foreach ($keys as $key) {
                    unset($this->store[$key]);
                }

                return true;
            }

            public function saveDeferred(CacheItemInterface $item): bool
            {
                return $this->save($item);
            }

            public function commit(): bool
            {
                return true;
            }
        };
    }
}
