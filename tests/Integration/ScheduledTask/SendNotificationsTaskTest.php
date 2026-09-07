<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Tests\Integration\ScheduledTask;

use fKuenzel\BackInStockNotification\Service\BackInStockNotificationService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Verifies the one-shot send bookkeeping: pending notifications are grouped per
 * recipient and deleted after being marked as notified, leaving an audit entry.
 * Runs against a booted kernel with a database.
 */
class SendNotificationsTaskTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $notificationRepository;
    private EntityRepository $logRepository;
    private BackInStockNotificationService $service;
    private Context $context;

    protected function setUp(): void
    {
        $this->notificationRepository = $this->getContainer()->get('fk_back_in_stock_notification.repository');
        $this->logRepository = $this->getContainer()->get('fk_back_in_stock_notification_log.repository');
        $this->service = $this->getContainer()->get(BackInStockNotificationService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testMarkAsNotifiedDeletesNotificationAndWritesAuditLog(): void
    {
        $productId = $this->createProduct();
        $notificationId = $this->createPendingNotification($productId);

        $grouped = $this->service->getPendingNotificationsGroupedByCustomer($this->context);
        static::assertArrayHasKey('sender@example.com', $grouped);

        $this->service->markAsNotified([$notificationId], new \DateTimeImmutable(), $this->context);

        $remaining = $this->notificationRepository
            ->search(new Criteria([$notificationId]), $this->context)
            ->first();
        static::assertNull($remaining, 'A notified notification must be deleted (one-shot).');

        $logCount = $this->logRepository->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('action', 'notified')),
            $this->context
        )->getTotal();
        static::assertGreaterThan(0, $logCount);
    }

    private function createProduct(): string
    {
        $id = Uuid::randomHex();
        $taxId = $this->getContainer()->get('tax.repository')
            ->searchIds(new Criteria(), $this->context)->firstId();

        $this->getContainer()->get('product.repository')->create([[
            'id' => $id,
            'productNumber' => 'BISN-' . $id,
            'name' => 'Back in stock send test product',
            'stock' => 10,
            'isCloseout' => true,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 9.99,
                'net' => 8.40,
                'linked' => false,
            ]],
        ]], $this->context);

        return $id;
    }

    private function createPendingNotification(string $productId): string
    {
        $id = Uuid::randomHex();
        $future = (new \DateTimeImmutable('+180 days'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->notificationRepository->create([[
            'id' => $id,
            'productId' => $productId,
            'productVersionId' => Defaults::LIVE_VERSION,
            'email' => 'sender@example.com',
            'isActive' => true,
            'isPending' => true,
            'unsubscribeToken' => bin2hex(random_bytes(16)),
            'tokenExpiresAt' => $future,
            'expiresAt' => $future,
            'ipAddress' => '127.0.0.1',
        ]], $this->context);

        return $id;
    }
}
