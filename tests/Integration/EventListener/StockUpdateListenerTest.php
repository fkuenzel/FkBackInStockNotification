<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Tests\Integration\EventListener;

use Fkuenzel\FkBackInStockNotification\Service\BackInStockNotificationService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Verifies that notifications for an available product are flagged pending.
 * Runs against a booted kernel with a database.
 */
class StockUpdateListenerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $notificationRepository;
    private EntityRepository $productRepository;
    private BackInStockNotificationService $service;
    private Context $context;

    protected function setUp(): void
    {
        $this->notificationRepository = $this->getContainer()->get('fk_back_in_stock_notification.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->service = $this->getContainer()->get(BackInStockNotificationService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testMarkPendingForAvailableProductFlagsNotification(): void
    {
        $productId = $this->createProduct(stock: 7, closeout: true);
        $notificationId = $this->createNotification($productId, isPending: false);

        $flagged = $this->service->markPendingForProducts([$productId], $this->context);

        static::assertSame(1, $flagged);

        $notification = $this->notificationRepository
            ->search(new Criteria([$notificationId]), $this->context)
            ->first();

        static::assertNotNull($notification);
        static::assertTrue($notification->isPending());
    }

    private function createProduct(int $stock, bool $closeout): string
    {
        $id = Uuid::randomHex();
        $taxId = $this->getContainer()->get('tax.repository')
            ->searchIds(new Criteria(), $this->context)->firstId();

        $this->productRepository->create([[
            'id' => $id,
            'productNumber' => 'BISN-' . $id,
            'name' => 'Back in stock test product',
            'stock' => $stock,
            'isCloseout' => $closeout,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 19.99,
                'net' => 16.80,
                'linked' => false,
            ]],
        ]], $this->context);

        return $id;
    }

    private function createNotification(string $productId, bool $isPending): string
    {
        $id = Uuid::randomHex();
        $future = (new \DateTimeImmutable('+180 days'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->notificationRepository->create([[
            'id' => $id,
            'productId' => $productId,
            'productVersionId' => Defaults::LIVE_VERSION,
            'email' => 'listener@example.com',
            'isActive' => true,
            'isPending' => $isPending,
            'unsubscribeToken' => bin2hex(random_bytes(16)),
            'tokenExpiresAt' => $future,
            'expiresAt' => $future,
            'ipAddress' => '127.0.0.1',
        ]], $this->context);

        return $id;
    }
}
