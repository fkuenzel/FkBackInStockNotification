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
 * Dispatched right before a notification is deleted.
 * Reasons: user_request, expired, admin_delete
 * Event name: fk-back-in-stock-notification.deleted
 */
class BackInStockNotificationDeletedEvent extends Event
{
    public const NAME = 'fk-back-in-stock-notification.deleted';

    public const REASON_USER_REQUEST = 'user_request';
    public const REASON_EXPIRED = 'expired';
    public const REASON_ADMIN_DELETE = 'admin_delete';

    public function __construct(
        private readonly BackInStockNotificationEntity $notification,
        private readonly string $reason,
        private readonly Context $context
    ) {
    }

    public function getNotification(): BackInStockNotificationEntity
    {
        return $this->notification;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
