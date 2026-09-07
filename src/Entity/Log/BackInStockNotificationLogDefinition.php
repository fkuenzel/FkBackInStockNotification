<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Entity\Log;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BackInStockNotificationLogDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'fk_back_in_stock_notification_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BackInStockNotificationLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BackInStockNotificationLogCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('action', 'action'))->addFlags(new Required()),
            new IdField('user_id', 'userId'),
            (new EmailField('email', 'email'))->addFlags(new Required()),
            (new IdField('product_id', 'productId'))->addFlags(new Required()),
            new StringField('reason', 'reason'),
            new RemoteAddressField('ip_address', 'ipAddress'),
            new CreatedAtField(),
        ]);
    }
}
