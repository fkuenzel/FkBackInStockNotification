<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Tests\Unit\Service;

use fKuenzel\BackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationEntity;
use fKuenzel\BackInStockNotification\Service\NotificationMailService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(\fKuenzel\BackInStockNotification\Service\NotificationMailService::class)]
class NotificationMailServiceTest extends TestCase
{
    public function testEmptyAvailabilityListSendsNoMail(): void
    {
        $mailService = $this->createMock(AbstractMailService::class);
        $mailService->expects(static::never())->method('send');

        $service = new NotificationMailService(
            $mailService,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(LoggerInterface::class)
        );

        $service->sendAvailabilityNotification('user@example.com', [], Context::createDefaultContext());
    }

    public function testMissingMailTemplateThrows(): void
    {
        $mailTemplateRepository = $this->createMock(EntityRepository::class);
        $emptyResult = $this->createMock(EntitySearchResult::class);
        $emptyResult->method('first')->willReturn(null);
        $mailTemplateRepository->method('search')->willReturn($emptyResult);

        $service = new NotificationMailService(
            $this->createMock(AbstractMailService::class),
            $mailTemplateRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(LoggerInterface::class)
        );

        $notification = new BackInStockNotificationEntity();
        $notification->setId(Uuid::randomHex());
        $notification->setEmail('user@example.com');
        $notification->setUnsubscribeToken('token');

        $this->expectException(\RuntimeException::class);
        $service->sendRegistrationConfirmation($notification, Context::createDefaultContext());
    }
}
