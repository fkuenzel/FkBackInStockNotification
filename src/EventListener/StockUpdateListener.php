<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\EventListener;

use Fkuenzel\FkBackInStockNotification\Service\BackInStockNotificationService;
use Shopware\Core\Content\Product\Events\ProductStockAlteredEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Detects products that became available again and flags matching notifications
 * as pending. Listens to two sources so that both order-driven stock changes
 * (ProductStockAlteredEvent) and manual admin stock edits (product.written) are covered.
 */
class StockUpdateListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly BackInStockNotificationService $notificationService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductStockAlteredEvent::class => 'onStockAltered',
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
        ];
    }

    public function onStockAltered(ProductStockAlteredEvent $event): void
    {
        $this->notificationService->markPendingForProducts($event->getIds(), $event->getContext());
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        $productIds = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();

            // Only react when a stock-related field was actually written.
            if (!\array_key_exists('stock', $payload) && !\array_key_exists('availableStock', $payload)) {
                continue;
            }

            $id = $payload['id'] ?? $writeResult->getPrimaryKey();
            if (\is_string($id)) {
                $productIds[] = $id;
            }
        }

        if ($productIds === []) {
            return;
        }

        $this->notificationService->markPendingForProducts($productIds, $event->getContext());
    }
}
