<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Entity\BackInStockNotification;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BackInStockNotificationEntity>
 */
class BackInStockNotificationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'fk_back_in_stock_notification_collection';
    }

    protected function getExpectedClass(): string
    {
        return BackInStockNotificationEntity::class;
    }
}
