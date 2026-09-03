<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'back_in_stock_notification.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }
}
