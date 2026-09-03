<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class DuplicateNotificationException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'BACK_IN_STOCK_NOTIFICATION__DUPLICATE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
