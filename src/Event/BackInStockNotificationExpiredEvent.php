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
 * Dispatched when a notification is removed because it expired.
 * Event name: fk-back-in-stock-notification.expired
 */
class BackInStockNotificationExpiredEvent extends Event
{
    public const NAME = 'fk-back-in-stock-notification.expired';

    public function __construct(
        private readonly BackInStockNotificationEntity $notification,
        private readonly \DateTimeInterface $expirationDate,
        private readonly Context $context
    ) {
    }

    public function getNotification(): BackInStockNotificationEntity
    {
        return $this->notification;
    }

    public function getExpirationDate(): \DateTimeInterface
    {
        return $this->expirationDate;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
