<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Event;

use Fkuenzel\FkBackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched right after a customer or guest registered for a notification.
 * Event name: fk-back-in-stock-notification.registered
 */
class BackInStockNotificationRegisteredEvent extends Event
{
    public const NAME = 'fk-back-in-stock-notification.registered';

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
