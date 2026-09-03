<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when a product is not eligible for a back-in-stock notification,
 * i.e. it is currently available or backorder is allowed (isCloseout = false).
 */
class ProductNotEligibleException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'BACK_IN_STOCK_NOTIFICATION__PRODUCT_NOT_ELIGIBLE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
