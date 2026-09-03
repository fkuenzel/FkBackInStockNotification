<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Entity\CronState;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BackInStockNotificationCronStateDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'back_in_stock_notification_cron_state';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BackInStockNotificationCronStateEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BackInStockNotificationCronStateCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new DateTimeField('last_run_at', 'lastRunAt'),
            (new IntField('consecutive_failures', 'consecutiveFailures'))->addFlags(new Required()),
            new StringField('last_error_message', 'lastErrorMessage'),
            new DateTimeField('last_error_at', 'lastErrorAt'),
            new DateTimeField('admin_notified_at', 'adminNotifiedAt'),
            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
