<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1788157888BackInStockNotificationCronState extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788157888;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `fk_back_in_stock_notification_cron_state` (
                `id`                    BINARY(16)     NOT NULL,
                `last_run_at`           DATETIME(3)    NULL,
                `consecutive_failures`  INT            NOT NULL DEFAULT 0,
                `last_error_message`    VARCHAR(1000)  NULL,
                `last_error_at`         DATETIME(3)    NULL,
                `admin_notified_at`     DATETIME(3)    NULL,
                `created_at`            DATETIME(3)    NOT NULL,
                `updated_at`            DATETIME(3)    NULL,
                PRIMARY KEY (`id`),
                KEY `idx.bisn_cron.consecutive_failures` (`consecutive_failures`),
                KEY `idx.bisn_cron.last_error` (`last_error_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Tables are dropped by the plugin uninstall (FkBackInStockNotification::uninstall).
    }
}
