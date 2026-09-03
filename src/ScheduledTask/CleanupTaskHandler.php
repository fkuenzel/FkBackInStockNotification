<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\ScheduledTask;

use fKuenzel\BackInStockNotification\Service\BackInStockNotificationService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CleanupTask::class)]
class CleanupTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly BackInStockNotificationService $notificationService,
        private readonly SystemConfigService $systemConfigService
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();

        $expired = $this->notificationService->deleteExpiredNotifications($context);

        $retention = $this->systemConfigService->getInt(BackInStockNotificationService::CONFIG_DOMAIN . 'auditLogRetentionDays');
        $retention = $retention > 0 ? $retention : 90;
        $purged = $this->notificationService->deleteOldAuditLogs($retention, $context);

        $this->exceptionLogger->info('Back in stock cleanup finished.', [
            'expiredNotifications' => $expired,
            'purgedAuditLogs' => $purged,
        ]);
    }
}
