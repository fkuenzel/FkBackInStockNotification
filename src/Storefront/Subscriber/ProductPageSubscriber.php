<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Storefront\Subscriber;

use fKuenzel\BackInStockNotification\Service\BackInStockNotificationService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Decorates the product detail page with everything the notification widget
 * needs, computed server-side so the template renders the correct state without
 * an extra AJAX round trip:
 *
 *  - eligible:          stock is 0 AND back-orders are disabled (isCloseout)
 *  - productId/variantId: split into parent product + variant for the data model
 *  - alreadyRegistered: true when a logged-in customer already has an active
 *                       notification for exactly this product/variant
 *
 * Exposed to Twig as page.extensions.backInStockNotification.
 */
class ProductPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BackInStockNotificationService $notificationService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $product = $event->getPage()->getProduct();

        $eligible = $this->isEligible($product);

        $parentId = $product->getParentId();
        $productId = $parentId ?? $product->getId();
        $variantId = $parentId !== null ? $product->getId() : null;

        $alreadyRegistered = false;
        $customer = $event->getSalesChannelContext()->getCustomer();

        if ($eligible && $customer !== null) {
            $alreadyRegistered = $this->notificationService->isCustomerAlreadyNotified(
                $customer->getId(),
                $productId,
                $variantId,
                $event->getContext()
            );
        }

        $event->getPage()->addExtension('backInStockNotification', new ArrayStruct([
            'eligible' => $eligible,
            'productId' => $productId,
            'variantId' => $variantId,
            'alreadyRegistered' => $alreadyRegistered,
            'customerEmail' => $customer?->getEmail(),
        ]));
    }

    /**
     * Component shows only for genuinely sold-out products where a back-order is
     * not possible (Shopware isCloseout = true). When back-orders are allowed the
     * customer can simply buy, so no notification is offered.
     */
    private function isEligible(ProductEntity $product): bool
    {
        return (int) $product->getAvailableStock() <= 0 && $product->getIsCloseout() === true;
    }
}
