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
 * Dispatched for each notification that was successfully queued/sent.
 * Event name: fk-back-in-stock-notification.sent
 */
class BackInStockNotificationSentEvent extends Event
{
    public const NAME = 'fk-back-in-stock-notification.sent';

    public function __construct(
        private readonly BackInStockNotificationEntity $notification,
        private readonly \DateTimeInterface $sentAt,
        private readonly Context $context
    ) {
    }

    public function getNotification(): BackInStockNotificationEntity
    {
        return $this->notification;
    }

    public function getSentAt(): \DateTimeInterface
    {
        return $this->sentAt;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
