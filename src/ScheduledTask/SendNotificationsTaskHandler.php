<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\ScheduledTask;

use fKuenzel\BackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationEntity;
use fKuenzel\BackInStockNotification\Service\BackInStockNotificationService;
use fKuenzel\BackInStockNotification\Service\CronStateService;
use fKuenzel\BackInStockNotification\Service\NotificationMailService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: SendNotificationsTask::class)]
class SendNotificationsTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly BackInStockNotificationService $notificationService,
        private readonly NotificationMailService $mailService,
        private readonly CronStateService $cronState,
        private readonly SystemConfigService $systemConfigService
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $context = Context::createDefaultContext();

        if (!$this->systemConfigService->getBool(BackInStockNotificationService::CONFIG_DOMAIN . 'active')) {
            return;
        }

        if (!$this->isSendTime($context)) {
            return;
        }

        try {
            $sent = $this->process($context);
            $this->cronState->recordSuccess($context);
            $this->exceptionLogger->info('Back in stock availability mails processed.', ['recipients' => $sent]);
        } catch (\Throwable $exception) {
            $this->cronState->recordFailure($exception->getMessage(), $context);
            $this->exceptionLogger->error('Back in stock send cron failed.', ['exception' => $exception->getMessage()]);

            $this->alertAdminIfNeeded($context);
        }
    }

    /**
     * Sends availability mails immediately, bypassing the daily send-time gate.
     * Used by the admin "manual send" action. Returns the number of recipients
     * that received a consolidated mail. Failures are recorded on the cron state
     * so the monitoring widget reflects the manual run too.
     */
    public function runManually(Context $context): int
    {
        if (!$this->systemConfigService->getBool(BackInStockNotificationService::CONFIG_DOMAIN . 'active')) {
            return 0;
        }

        try {
            $sent = $this->process($context);
            $this->cronState->recordSuccess($context);
            $this->exceptionLogger->info('Back in stock availability mails processed (manual trigger).', ['recipients' => $sent]);

            return $sent;
        } catch (\Throwable $exception) {
            $this->cronState->recordFailure($exception->getMessage(), $context);
            $this->exceptionLogger->error('Manual back in stock send failed.', ['exception' => $exception->getMessage()]);
            $this->alertAdminIfNeeded($context);

            throw $exception;
        }
    }

    private function process(Context $context): int
    {
        $grouped = $this->notificationService->getPendingNotificationsGroupedByCustomer($context);
        $sentCount = 0;

        foreach ($grouped as $notifications) {
            $available = [];
            $unavailableIds = [];

            foreach ($notifications as $notification) {
                if ($this->availableStock($notification) > 0) {
                    $available[] = $notification;
                } else {
                    $unavailableIds[] = $notification->getId();
                }
            }

            if ($unavailableIds !== []) {
                $this->notificationService->resetPending($unavailableIds, $context);
            }

            // One consolidated mail per recipient AND sales channel/language, so the mail
            // uses the correct language and shop links even if the same email registered
            // on different channels.
            foreach ($this->groupByChannelAndLanguage($available) as $group) {
                $email = $group[0]->getEmail();

                try {
                    $this->mailService->sendAvailabilityNotification($email, $group, $context);
                    $this->notificationService->markAsNotified(
                        array_map(static fn (BackInStockNotificationEntity $n): string => $n->getId(), $group),
                        new \DateTimeImmutable(),
                        $context
                    );
                    ++$sentCount;
                } catch (\Throwable $exception) {
                    // A single recipient's mail failure must not abort the whole batch;
                    // the notification stays pending and is retried on the next run.
                    $this->exceptionLogger->error('Failed to send availability mail.', [
                        'email' => $email,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return $sentCount;
    }

    private function availableStock(BackInStockNotificationEntity $notification): int
    {
        $product = $notification->getProductVariant() ?? $notification->getProduct();

        return $product !== null ? (int) $product->getAvailableStock() : 0;
    }

    /**
     * @param BackInStockNotificationEntity[] $notifications
     *
     * @return array<string, BackInStockNotificationEntity[]>
     */
    private function groupByChannelAndLanguage(array $notifications): array
    {
        $groups = [];
        foreach ($notifications as $notification) {
            $key = ($notification->getSalesChannelId() ?? '') . '|' . ($notification->getLanguageId() ?? '');
            $groups[$key][] = $notification;
        }

        return $groups;
    }

    /**
     * Runs once per day, at or after the configured send time (shop server timezone).
     */
    private function isSendTime(Context $context): bool
    {
        $sendTime = $this->systemConfigService->getString(BackInStockNotificationService::CONFIG_DOMAIN . 'sendTime');
        if (preg_match('/^\d{1,2}:\d{2}$/', $sendTime) !== 1) {
            $sendTime = '18:00';
        }

        [$hour, $minute] = array_map('intval', explode(':', $sendTime));

        $now = new \DateTimeImmutable();
        $target = $now->setTime($hour, $minute, 0);

        if ($now < $target) {
            return false;
        }

        $lastRun = $this->cronState->getState($context)->getLastRunAt();
        if ($lastRun !== null) {
            $lastRunLocal = \DateTimeImmutable::createFromInterface($lastRun)->setTimezone($now->getTimezone());
            if ($lastRunLocal->format('Y-m-d') === $now->format('Y-m-d')) {
                return false;
            }
        }

        return true;
    }

    private function alertAdminIfNeeded(Context $context): void
    {
        if (!$this->cronState->shouldNotifyAdmin($context)) {
            return;
        }

        try {
            $this->mailService->sendCronFailureAlert($this->cronState->getState($context), $context);
            $this->cronState->markAdminNotified($context);
        } catch (\Throwable $exception) {
            $this->exceptionLogger->error('Failed to send cron failure alert.', ['exception' => $exception->getMessage()]);
        }
    }
}
