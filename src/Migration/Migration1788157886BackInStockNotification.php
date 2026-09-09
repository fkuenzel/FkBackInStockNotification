<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1788157886BackInStockNotification extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1788157886;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `fk_back_in_stock_notification` (
                `id`                          BINARY(16)   NOT NULL,
                `product_id`                  BINARY(16)   NOT NULL,
                `product_version_id`          BINARY(16)   NOT NULL,
                `product_variant_id`          BINARY(16)   NULL,
                `product_variant_version_id`  BINARY(16)   NULL,
                `customer_id`                 BINARY(16)   NULL,
                `sales_channel_id`            BINARY(16)   NULL,
                `language_id`                 BINARY(16)   NULL,
                `email`                       VARCHAR(255) NOT NULL,
                `is_active`                   TINYINT(1)   NOT NULL DEFAULT 1,
                `is_pending`                  TINYINT(1)   NOT NULL DEFAULT 1,
                `unsubscribe_token`           VARCHAR(255) NOT NULL,
                `token_expires_at`            DATETIME(3)  NOT NULL,
                `expires_at`                  DATETIME(3)  NOT NULL,
                `last_notified_at`            DATETIME(3)  NULL,
                `ip_address`                  VARCHAR(45)  NOT NULL,
                `user_agent`                  VARCHAR(255) NULL,
                `created_at`                  DATETIME(3)  NOT NULL,
                `updated_at`                  DATETIME(3)  NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.bisn.variant_email` (`product_variant_id`, `email`),
                KEY `idx.bisn.pending` (`is_active`, `is_pending`, `created_at`),
                KEY `idx.bisn.expires` (`expires_at`),
                KEY `idx.bisn.token_expires` (`token_expires_at`),
                KEY `idx.bisn.customer` (`customer_id`),
                KEY `idx.bisn.product` (`product_id`),
                KEY `idx.bisn.unsubscribe_token` (`unsubscribe_token`),
                CONSTRAINT `fk.fk_back_in_stock_notification.product_id` FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.fk_back_in_stock_notification.product_variant_id` FOREIGN KEY (`product_variant_id`, `product_variant_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.fk_back_in_stock_notification.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.fk_back_in_stock_notification.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.fk_back_in_stock_notification.language_id` FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Tables are dropped by the plugin uninstall (FkBackInStockNotification::uninstall).
    }
}
