<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Entity\BackInStockNotification;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BackInStockNotificationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'fk_back_in_stock_notification';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BackInStockNotificationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BackInStockNotificationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),

            new FkField('product_variant_id', 'productVariantId', ProductDefinition::class),
            new ReferenceVersionField(ProductDefinition::class, 'product_variant_version_id'),
            new ManyToOneAssociationField('productVariant', 'product_variant_id', ProductDefinition::class, 'id', false),

            new FkField('customer_id', 'customerId', CustomerDefinition::class),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),

            new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),

            new FkField('language_id', 'languageId', LanguageDefinition::class),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),

            (new EmailField('email', 'email'))->addFlags(new Required()),

            new BoolField('is_active', 'isActive'),
            new BoolField('is_pending', 'isPending'),

            (new StringField('unsubscribe_token', 'unsubscribeToken'))->addFlags(new Required()),
            (new DateTimeField('token_expires_at', 'tokenExpiresAt'))->addFlags(new Required()),
            (new DateTimeField('expires_at', 'expiresAt'))->addFlags(new Required()),
            new DateTimeField('last_notified_at', 'lastNotifiedAt'),

            (new RemoteAddressField('ip_address', 'ipAddress'))->addFlags(new Required()),
            new StringField('user_agent', 'userAgent'),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}
