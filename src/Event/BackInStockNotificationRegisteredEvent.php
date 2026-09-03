<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Event;

use fKuenzel\BackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched right after a customer or guest registered for a notification.
 * Event name: back-in-stock-notification.registered
 */
class BackInStockNotificationRegisteredEvent extends Event
{
    public const NAME = 'back-in-stock-notification.registered';

    public function __construct(
        private readonly BackInStockNotificationEntity $notification,
        private readonly Context $context
    ) {
    }

    public function getNotification(): BackInStockNotificationEntity
    {
        return $this->notification;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
