<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class RateLimitExceededException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'BACK_IN_STOCK_NOTIFICATION__RATE_LIMIT_EXCEEDED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }
}
