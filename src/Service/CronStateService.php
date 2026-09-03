<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Service;

use fKuenzel\BackInStockNotification\Entity\CronState\BackInStockNotificationCronStateCollection;
use fKuenzel\BackInStockNotification\Entity\CronState\BackInStockNotificationCronStateEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Persists the state of the daily send cron for fault tolerance and monitoring:
 * last successful run, consecutive failures and whether the admin was already alerted.
 */
class CronStateService
{
    private const ADMIN_ALERT_THRESHOLD = 3;
    private const MAX_ERROR_LENGTH = 1000;

    /**
     * @param EntityRepository<BackInStockNotificationCronStateCollection> $cronStateRepository
     */
    public function __construct(
        private readonly EntityRepository $cronStateRepository
    ) {
    }

    public function getState(Context $context): BackInStockNotificationCronStateEntity
    {
        return $this->loadOrCreate($context);
    }

    public function recordSuccess(Context $context): void
    {
        $state = $this->loadOrCreate($context);

        $this->cronStateRepository->update([[
            'id' => $state->getId(),
            'lastRunAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'consecutiveFailures' => 0,
            'lastErrorMessage' => null,
            'adminNotifiedAt' => null,
        ]], $context);
    }

    public function recordFailure(string $message, Context $context): void
    {
        $state = $this->loadOrCreate($context);

        $this->cronStateRepository->update([[
            'id' => $state->getId(),
            'consecutiveFailures' => $state->getConsecutiveFailures() + 1,
            'lastErrorMessage' => mb_substr($message, 0, self::MAX_ERROR_LENGTH),
            'lastErrorAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $context);
    }

    /**
     * The admin is alerted exactly once per failure streak, at the configured threshold.
     */
    public function shouldNotifyAdmin(Context $context): bool
    {
        $state = $this->loadOrCreate($context);

        return $state->getConsecutiveFailures() >= self::ADMIN_ALERT_THRESHOLD
            && $state->getAdminNotifiedAt() === null;
    }

    public function markAdminNotified(Context $context): void
    {
        $state = $this->loadOrCreate($context);

        $this->cronStateRepository->update([[
            'id' => $state->getId(),
            'adminNotifiedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]], $context);
    }

    private function loadOrCreate(Context $context): BackInStockNotificationCronStateEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        /** @var BackInStockNotificationCronStateCollection $result */
        $result = $this->cronStateRepository->search($criteria, $context)->getEntities();
        $state = $result->first();

        if ($state instanceof BackInStockNotificationCronStateEntity) {
            return $state;
        }

        $id = Uuid::randomHex();
        $this->cronStateRepository->create([[
            'id' => $id,
            'consecutiveFailures' => 0,
        ]], $context);

        /** @var BackInStockNotificationCronStateCollection $created */
        $created = $this->cronStateRepository->search(new Criteria([$id]), $context)->getEntities();
        $state = $created->first();

        if ($state === null) {
            throw new \RuntimeException('Failed to initialise the back in stock cron state.');
        }

        return $state;
    }
}
