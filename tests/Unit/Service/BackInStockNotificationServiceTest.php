<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Tests\Unit\Service;

use Fkuenzel\FkBackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationCollection;
use Fkuenzel\FkBackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationEntity;
use Fkuenzel\FkBackInStockNotification\Event\BackInStockNotificationRegisteredEvent;
use Fkuenzel\FkBackInStockNotification\Exception\DuplicateNotificationException;
use Fkuenzel\FkBackInStockNotification\Exception\InvalidEmailException;
use Fkuenzel\FkBackInStockNotification\Exception\ProductNotEligibleException;
use Fkuenzel\FkBackInStockNotification\Exception\RateLimitExceededException;
use Fkuenzel\FkBackInStockNotification\Service\BackInStockNotificationService;
use Fkuenzel\FkBackInStockNotification\Service\RateLimitService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(\Fkuenzel\FkBackInStockNotification\Service\BackInStockNotificationService::class)]
class BackInStockNotificationServiceTest extends TestCase
{
    private EntityRepository&MockObject $notificationRepository;
    private EntityRepository&MockObject $logRepository;
    private EntityRepository&MockObject $productRepository;
    private SystemConfigService&MockObject $systemConfigService;
    private RateLimitService&MockObject $rateLimitService;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private BackInStockNotificationService $service;
    private Context $context;

    protected function setUp(): void
    {
        $this->notificationRepository = $this->createMock(EntityRepository::class);
        $this->logRepository = $this->createMock(EntityRepository::class);
        $this->productRepository = $this->createMock(EntityRepository::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->rateLimitService = $this->createMock(RateLimitService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->context = Context::createDefaultContext();

        $this->systemConfigService->method('getBool')->willReturn(true);
        $this->systemConfigService->method('getInt')->willReturnCallback(
            static function (string $key): int {
                if (str_contains($key, 'rateLimitPerIpHour')) {
                    return 100;
                }
                if (str_contains($key, 'rateLimitPerCustomerDay')) {
                    return 5;
                }

                return 180;
            }
        );
        $this->rateLimitService->method('isLimitExceeded')->willReturn(false);

        $this->service = new BackInStockNotificationService(
            $this->notificationRepository,
            $this->logRepository,
            $this->productRepository,
            $this->systemConfigService,
            $this->rateLimitService,
            $this->eventDispatcher,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testRejectsInvalidEmail(): void
    {
        $this->expectException(InvalidEmailException::class);
        $this->register('not-an-email', 'cust');
    }

    public function testRejectsGuestWhenGuestsDisabled(): void
    {
        $config = $this->createMock(SystemConfigService::class);
        $config->method('getBool')->willReturn(false);
        $config->method('getInt')->willReturn(100);

        $service = new BackInStockNotificationService(
            $this->notificationRepository,
            $this->logRepository,
            $this->productRepository,
            $config,
            $this->rateLimitService,
            $this->eventDispatcher,
            $this->createMock(LoggerInterface::class)
        );

        $this->expectException(ProductNotEligibleException::class);
        $service->registerNotification('guest@example.com', Uuid::randomHex(), null, null, '127.0.0.1', null, null, null, $this->context);
    }

    public function testRejectsWhenRateLimited(): void
    {
        $rateLimit = $this->createMock(RateLimitService::class);
        $rateLimit->method('isLimitExceeded')->willReturn(true);

        $service = new BackInStockNotificationService(
            $this->notificationRepository,
            $this->logRepository,
            $this->productRepository,
            $this->systemConfigService,
            $rateLimit,
            $this->eventDispatcher,
            $this->createMock(LoggerInterface::class)
        );

        $this->expectException(RateLimitExceededException::class);
        $service->registerNotification('user@example.com', Uuid::randomHex(), null, 'cust', '127.0.0.1', null, null, null, $this->context);
    }

    public function testRejectsAvailableProduct(): void
    {
        $this->mockProduct(availableStock: 12, isCloseout: true);

        $this->expectException(ProductNotEligibleException::class);
        $this->register('user@example.com', 'cust');
    }

    public function testRejectsProductWithBackorderAllowed(): void
    {
        $this->mockProduct(availableStock: 0, isCloseout: false);

        $this->expectException(ProductNotEligibleException::class);
        $this->register('user@example.com', 'cust');
    }

    public function testRejectsDuplicateNotification(): void
    {
        $this->mockProduct(availableStock: 0, isCloseout: true);
        $this->notificationRepository->method('searchIds')->willReturn($this->idResult(1));

        $this->expectException(DuplicateNotificationException::class);
        $this->register('user@example.com', 'cust');
    }

    public function testRegistersSuccessfully(): void
    {
        $this->mockProduct(availableStock: 0, isCloseout: true);
        $this->notificationRepository->method('searchIds')->willReturn($this->idResult(0));

        $created = new BackInStockNotificationEntity();
        $created->setId(Uuid::randomHex());
        $created->setEmail('user@example.com');
        $created->setProductId(Uuid::randomHex());

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(new BackInStockNotificationCollection([$created]));
        $this->notificationRepository->method('search')->willReturn($searchResult);

        $this->notificationRepository->expects(static::once())->method('create');
        $this->eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(BackInStockNotificationRegisteredEvent::class), BackInStockNotificationRegisteredEvent::NAME)
            ->willReturnArgument(0);

        $id = $this->register('user@example.com', 'cust');

        static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $id);
    }

    public function testGenerateUnsubscribeTokenIsHexAndUnique(): void
    {
        $first = $this->service->generateUnsubscribeToken();
        $second = $this->service->generateUnsubscribeToken();

        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
        static::assertNotSame($first, $second);
    }

    private function register(string $email, ?string $customerId): string
    {
        return $this->service->registerNotification(
            $email,
            Uuid::randomHex(),
            null,
            $customerId,
            '127.0.0.1',
            'phpunit',
            null,
            null,
            $this->context
        );
    }

    private function mockProduct(int $availableStock, bool $isCloseout): void
    {
        $product = new ProductEntity();
        $product->setId(Uuid::randomHex());
        $product->setAvailableStock($availableStock);
        $product->setIsCloseout($isCloseout);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ProductCollection([$product]));
        $this->productRepository->method('search')->willReturn($result);
    }

    private function idResult(int $total): IdSearchResult
    {
        $data = [];
        for ($i = 0; $i < $total; ++$i) {
            $data[] = ['primaryKey' => Uuid::randomHex(), 'data' => []];
        }

        return new IdSearchResult($total, $data, new Criteria(), $this->context);
    }
}
