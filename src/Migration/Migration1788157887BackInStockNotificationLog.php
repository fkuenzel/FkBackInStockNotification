<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1788157887BackInStockNotificationLog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788157887;
    }

    public function update(Connection $connection): void
    {
        // Audit trail: product_id is intentionally NOT a foreign key so that the
        // history survives a product deletion (an audit log must not be cascade-wiped).
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `back_in_stock_notification_log` (
                `id`          BINARY(16)    NOT NULL,
                `action`      VARCHAR(50)   NOT NULL,
                `user_id`     BINARY(16)    NULL,
                `email`       VARCHAR(255)  NOT NULL,
                `product_id`  BINARY(16)    NOT NULL,
                `reason`      VARCHAR(100)  NULL,
                `ip_address`  VARCHAR(45)   NULL,
                `created_at`  DATETIME(3)   NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.bisn_log.action` (`action`),
                KEY `idx.bisn_log.created` (`created_at`),
                KEY `idx.bisn_log.product` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Tables are dropped by the plugin uninstall (BackInStockNotification::uninstall).
    }
}
