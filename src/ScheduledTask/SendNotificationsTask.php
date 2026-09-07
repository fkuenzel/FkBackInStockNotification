<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SendNotificationsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'fk_back_in_stock_notification.send';
    }

    public static function getDefaultInterval(): int
    {
        // Runs every 15 minutes; the handler gates the actual send to the configured time of day.
        return 900;
    }
}
