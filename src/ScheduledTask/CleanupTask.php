<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'fk_back_in_stock_notification.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }
}
