<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license MIT
 */

namespace Fkuenzel\FkBackInStockNotification\Tests\Unit\Exception;

use Fkuenzel\FkBackInStockNotification\Exception\DuplicateNotificationException;
use Fkuenzel\FkBackInStockNotification\Exception\InvalidEmailException;
use Fkuenzel\FkBackInStockNotification\Exception\ProductNotEligibleException;
use Fkuenzel\FkBackInStockNotification\Exception\RateLimitExceededException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ExceptionHandlingTest extends TestCase
{
    public function testInvalidEmailException(): void
    {
        $exception = new InvalidEmailException('invalid');
        static::assertSame('BACK_IN_STOCK_NOTIFICATION__INVALID_EMAIL', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testDuplicateNotificationException(): void
    {
        $exception = new DuplicateNotificationException('duplicate');
        static::assertSame('BACK_IN_STOCK_NOTIFICATION__DUPLICATE', $exception->getErrorCode());
        static::assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
    }

    public function testRateLimitExceededException(): void
    {
        $exception = new RateLimitExceededException('too many');
        static::assertSame('BACK_IN_STOCK_NOTIFICATION__RATE_LIMIT_EXCEEDED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $exception->getStatusCode());
    }

    public function testProductNotEligibleException(): void
    {
        $exception = new ProductNotEligibleException('not eligible');
        static::assertSame('BACK_IN_STOCK_NOTIFICATION__PRODUCT_NOT_ELIGIBLE', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }
}
