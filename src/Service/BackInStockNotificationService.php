<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Service;

use fKuenzel\BackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationCollection;
use fKuenzel\BackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationEntity;
use fKuenzel\BackInStockNotification\Event\BackInStockNotificationDeletedEvent;
use fKuenzel\BackInStockNotification\Event\BackInStockNotificationExpiredEvent;
use fKuenzel\BackInStockNotification\Event\BackInStockNotificationRegisteredEvent;
use fKuenzel\BackInStockNotification\Event\BackInStockNotificationSentEvent;
use fKuenzel\BackInStockNotification\Exception\DuplicateNotificationException;
use fKuenzel\BackInStockNotification\Exception\InvalidEmailException;
use fKuenzel\BackInStockNotification\Exception\ProductNotEligibleException;
use fKuenzel\BackInStockNotification\Exception\RateLimitExceededException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use fKuenzel\BackInStockNotification\Entity\Log\BackInStockNotificationLogCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BackInStockNotificationService
{
    public const CONFIG_DOMAIN = 'FkBackInStockNotification.config.';

    private const BATCH_SIZE = 100;

    /**
     * @param EntityRepository<BackInStockNotificationCollection> $notificationRepository
     * @param EntityRepository<BackInStockNotificationLogCollection> $logRepository
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $notificationRepository,
        private readonly EntityRepository $logRepository,
        private readonly EntityRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly RateLimitService $rateLimitService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Registers a single-opt-in notification and returns the new notification id.
     *
     * @throws InvalidEmailException       when the email address is not valid
     * @throws RateLimitExceededException  when an IP/customer rate limit is hit
     * @throws ProductNotEligibleException when the product is available or allows backorder
     * @throws DuplicateNotificationException when an active notification already exists
     */
    public function registerNotification(
        string $email,
        string $productId,
        ?string $variantId,
        ?string $customerId,
        string $ipAddress,
        ?string $userAgent,
        ?string $salesChannelId,
        ?string $languageId,
        Context $context
    ): string {
        $email = trim($email);

        if (!$this->cfgBool('active')) {
            throw new ProductNotEligibleException('Back in stock notifications are currently disabled.');
        }

        if (filter_var($email, \FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmailException('The provided email address is invalid.', ['email' => $email]);
        }

        if ($customerId === null && !$this->cfgBool('allowGuests')) {
            throw new ProductNotEligibleException('Guest notifications are disabled.');
        }

        // Count every attempt (not only successful registrations) to throttle abuse and enumeration.
        $this->assertWithinRateLimits($ipAddress, $customerId);
        $this->trackRateLimits($ipAddress, $customerId);

        $product = $this->loadProduct($variantId ?? $productId, $context);
        $this->assertEligibleForNotification($product);

        if ($this->isNotificationDuplicate($variantId, $productId, $email, $context)) {
            throw new DuplicateNotificationException(
                'A notification for this product and email address already exists.',
                ['email' => $email, 'productId' => $productId]
            );
        }

        $id = Uuid::randomHex();
        $now = new \DateTimeImmutable();

        $this->notificationRepository->create([[
            'id' => $id,
            'productId' => $productId,
            'productVersionId' => Defaults::LIVE_VERSION,
            'productVariantId' => $variantId,
            'productVariantVersionId' => $variantId !== null ? Defaults::LIVE_VERSION : null,
            'customerId' => $customerId,
            'salesChannelId' => $salesChannelId,
            'languageId' => $languageId,
            'email' => $email,
            'isActive' => true,
            'isPending' => false,
            'unsubscribeToken' => $this->generateUnsubscribeToken(),
            'tokenExpiresAt' => $now->modify(sprintf('+%d days', $this->cfgInt('tokenValidityDays', 180))),
            'expiresAt' => $now->modify(sprintf('+%d days', $this->cfgInt('notificationValidityDays', 180))),
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
        ]], $context);

        $this->writeLog('created', $email, $productId, null, $ipAddress, null, $context);

        $notification = $this->loadNotification($id, $context);
        if ($notification !== null) {
            $this->eventDispatcher->dispatch(
                new BackInStockNotificationRegisteredEvent($notification, $context),
                BackInStockNotificationRegisteredEvent::NAME
            );
        }

        $this->logger->info('Back in stock notification registered.', [
            'notificationId' => $id,
            'productId' => $productId,
            'variantId' => $variantId,
            'email' => $email,
        ]);

        return $id;
    }

    /**
     * True when an active notification already exists for the given product/variant and email.
     */
    public function isNotificationDuplicate(?string $variantId, string $productId, string $email, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('isActive', true));
        $criteria->addFilter(new EqualsFilter('email', $email));
        $this->applyScope($criteria, $productId, $variantId);

        return $this->notificationRepository->searchIds($criteria, $context)->getTotal() > 0;
    }

    /**
     * True when the given customer already has an active notification for the product/variant.
     */
    public function isCustomerAlreadyNotified(string $customerId, string $productId, ?string $variantId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('isActive', true));
        $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        $this->applyScope($criteria, $productId, $variantId);

        return $this->notificationRepository->searchIds($criteria, $context)->getTotal() > 0;
    }

    /**
     * Marks all active notifications for a product/variant as pending (product is back in stock).
     *
     * @return int number of notifications that were newly marked pending
     */
    public function markAsPending(string $productId, ?string $variantId, Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isActive', true));
        $criteria->addFilter(new EqualsFilter('isPending', false));
        $this->applyScope($criteria, $productId, $variantId);

        $ids = $this->notificationRepository->searchIds($criteria, $context)->getIds();

        if ($ids === []) {
            return 0;
        }

        $payload = array_map(static fn (string $id): array => ['id' => $id, 'isPending' => true], $ids);
        $this->notificationRepository->update($payload, $context);

        return \count($ids);
    }

    /**
     * Marks pending all active notifications whose product/variant is among the given ids
     * and is currently available again (availableStock > 0). Used by the stock update listener.
     *
     * @param string[] $productIds
     *
     * @return int number of notifications newly marked pending
     */
    public function markPendingForProducts(array $productIds, Context $context): int
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds === []) {
            return 0;
        }

        $products = $this->productRepository->search(new Criteria($productIds), $context)->getEntities();

        $availableIds = [];
        foreach ($products as $product) {
            /** @var ProductEntity $product */
            if ((int) $product->getAvailableStock() > 0) {
                $availableIds[] = $product->getId();
            }
        }

        if ($availableIds === []) {
            return 0;
        }

        $criteria = new Criteria();
        $criteria->setLimit(self::BATCH_SIZE);
        $criteria->addFilter(new EqualsFilter('isActive', true));
        $criteria->addFilter(new EqualsFilter('isPending', false));
        $criteria->addFilter(new OrFilter([
            new EqualsAnyFilter('productVariantId', $availableIds),
            new AndFilter([
                new EqualsAnyFilter('productId', $availableIds),
                new EqualsFilter('productVariantId', null),
            ]),
        ]));

        $count = 0;

        // Always read the first page: updated rows leave the (isPending = false) filter,
        // so each fresh page returns the next unprocessed batch without skipping.
        do {
            /** @var BackInStockNotificationCollection $notifications */
            $notifications = $this->notificationRepository->search($criteria, $context)->getEntities();
            $batch = $notifications->count();
            if ($batch === 0) {
                break;
            }

            $payload = [];
            foreach ($notifications as $notification) {
                $payload[] = ['id' => $notification->getId(), 'isPending' => true];
                $this->writeLog('stock_updated', $notification->getEmail(), $notification->getProductId(), null, null, null, $context);
            }
            $this->notificationRepository->update($payload, $context);
            $count += $batch;
        } while ($batch === self::BATCH_SIZE);

        return $count;
    }

    /**
     * Returns all pending notifications grouped by recipient email, product and variant eager-loaded.
     *
     * @return array<string, BackInStockNotificationEntity[]>
     */
    public function getPendingNotificationsGroupedByCustomer(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isActive', true));
        $criteria->addFilter(new EqualsFilter('isPending', true));
        $criteria->addAssociation('product');
        $criteria->addAssociation('productVariant');
        $criteria->addAssociation('customer');

        /** @var BackInStockNotificationCollection $result */
        $result = $this->notificationRepository->search($criteria, $context)->getEntities();

        $grouped = [];
        foreach ($result as $notification) {
            $grouped[$notification->getEmail()][] = $notification;
        }

        return $grouped;
    }

    /**
     * Finalises a one-shot notification: writes the audit log, dispatches the sent event
     * and then deletes the row. Deletion (instead of deactivation) is required so the same
     * email can register again for the product later without hitting the unique constraint.
     *
     * @param string[] $notificationIds
     */
    public function markAsNotified(array $notificationIds, \DateTimeInterface $sentAt, Context $context): void
    {
        if ($notificationIds === []) {
            return;
        }

        $criteria = new Criteria($notificationIds);
        $criteria->addAssociation('product');
        $criteria->addAssociation('productVariant');
        /** @var BackInStockNotificationCollection $notifications */
        $notifications = $this->notificationRepository->search($criteria, $context)->getEntities();

        if ($notifications->count() === 0) {
            return;
        }

        $deleteIds = [];
        foreach ($notifications as $notification) {
            $this->writeLog('notified', $notification->getEmail(), $notification->getProductId(), null, null, null, $context);
            $this->eventDispatcher->dispatch(
                new BackInStockNotificationSentEvent($notification, $sentAt, $context),
                BackInStockNotificationSentEvent::NAME
            );
            $deleteIds[] = ['id' => $notification->getId()];
        }

        $this->notificationRepository->delete($deleteIds, $context);
    }

    /**
     * Resets the pending flag (e.g. when an item became unavailable again before it was sent).
     *
     * @param string[] $notificationIds
     */
    public function resetPending(array $notificationIds, Context $context): void
    {
        if ($notificationIds === []) {
            return;
        }

        $payload = array_map(
            static fn (string $id): array => ['id' => $id, 'isPending' => false],
            $notificationIds
        );
        $this->notificationRepository->update($payload, $context);
    }

    /**
     * Deletes audit log entries older than the given number of days.
     *
     * @return int number of deleted log rows
     */
    public function deleteOldAuditLogs(int $retentionDays, Context $context): int
    {
        $threshold = (new \DateTimeImmutable())->modify(sprintf('-%d days', max(1, $retentionDays)));

        $criteria = new Criteria();
        $criteria->setLimit(self::BATCH_SIZE);
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::LT => $threshold->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        $total = 0;

        do {
            $ids = $this->logRepository->searchIds($criteria, $context)->getIds();
            $batch = \count($ids);
            if ($batch === 0) {
                break;
            }

            $payload = array_map(static fn (string $id): array => ['id' => $id], $ids);
            $this->logRepository->delete($payload, $context);
            $total += $batch;
        } while ($batch === self::BATCH_SIZE);

        return $total;
    }

    /**
     * Deletes a single notification and records the reason.
     */
    public function deleteNotification(string $notificationId, string $reason, Context $context): void
    {
        $notification = $this->loadNotification($notificationId, $context);
        if ($notification === null) {
            return;
        }

        $this->eventDispatcher->dispatch(
            new BackInStockNotificationDeletedEvent($notification, $reason, $context),
            BackInStockNotificationDeletedEvent::NAME
        );

        $this->notificationRepository->delete([['id' => $notificationId]], $context);
        $this->writeLog('deleted', $notification->getEmail(), $notification->getProductId(), $reason, null, $this->resolveUserId($context), $context);
    }

    /**
     * Deletes all notifications whose retention period has expired.
     *
     * @return int number of deleted notifications
     */
    public function deleteExpiredNotifications(Context $context): int
    {
        $now = new \DateTimeImmutable();

        $criteria = new Criteria();
        $criteria->setLimit(self::BATCH_SIZE);
        $criteria->addFilter(new RangeFilter('expiresAt', [
            RangeFilter::LT => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        $total = 0;

        // Deleted rows leave the filter, so re-reading the first page walks the whole set.
        do {
            /** @var BackInStockNotificationCollection $notifications */
            $notifications = $this->notificationRepository->search($criteria, $context)->getEntities();
            $batch = $notifications->count();
            if ($batch === 0) {
                break;
            }

            $ids = [];
            foreach ($notifications as $notification) {
                $this->eventDispatcher->dispatch(
                    new BackInStockNotificationExpiredEvent($notification, $notification->getExpiresAt(), $context),
                    BackInStockNotificationExpiredEvent::NAME
                );
                $this->writeLog('expired', $notification->getEmail(), $notification->getProductId(), 'retention', null, null, $context);
                $ids[] = ['id' => $notification->getId()];
            }

            $this->notificationRepository->delete($ids, $context);
            $total += $batch;
        } while ($batch === self::BATCH_SIZE);

        return $total;
    }

    /**
     * True when the unsubscribe token exists, belongs to an active notification and has not expired.
     */
    public function validateUnsubscribeToken(string $token, Context $context): bool
    {
        return $this->loadByValidToken($token, $context) !== null;
    }

    /**
     * Unsubscribes via a valid token. Returns true when a notification was removed.
     */
    public function unsubscribeByToken(string $token, Context $context): bool
    {
        $notification = $this->loadByValidToken($token, $context);
        if ($notification === null) {
            return false;
        }

        $this->deleteNotification(
            $notification->getId(),
            BackInStockNotificationDeletedEvent::REASON_USER_REQUEST,
            $context
        );

        return true;
    }

    /**
     * Generates a unique, unpredictable unsubscribe token.
     */
    public function generateUnsubscribeToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function loadByValidToken(string $token, Context $context): ?BackInStockNotificationEntity
    {
        if ($token === '') {
            return null;
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('unsubscribeToken', $token));
        $criteria->addFilter(new EqualsFilter('isActive', true));
        $criteria->addFilter(new RangeFilter('tokenExpiresAt', [
            RangeFilter::GTE => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]));

        /** @var BackInStockNotificationCollection $result */
        $result = $this->notificationRepository->search($criteria, $context)->getEntities();

        return $result->first();
    }

    private function loadNotification(string $id, Context $context): ?BackInStockNotificationEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociation('product');
        $criteria->addAssociation('productVariant');

        /** @var BackInStockNotificationCollection $result */
        $result = $this->notificationRepository->search($criteria, $context)->getEntities();

        return $result->first();
    }

    private function loadProduct(string $productId, Context $context): ProductEntity
    {
        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->getEntities()->first();

        if ($product === null) {
            throw new ProductNotEligibleException('The product could not be found.', ['productId' => $productId]);
        }

        return $product;
    }

    private function assertEligibleForNotification(ProductEntity $product): void
    {
        if ((int) $product->getAvailableStock() > 0) {
            throw new ProductNotEligibleException(
                'This product is currently available; a notification is not necessary.',
                ['productId' => $product->getId()]
            );
        }

        if (!$product->getIsCloseout()) {
            throw new ProductNotEligibleException(
                'This product can be purchased at any time; a notification is not necessary.',
                ['productId' => $product->getId()]
            );
        }
    }

    private function assertWithinRateLimits(string $ipAddress, ?string $customerId): void
    {
        $ipHourId = 'ip_hour_' . $ipAddress;
        $customerDayId = 'cust_day_' . ($customerId ?? $ipAddress);

        if ($this->rateLimitService->isLimitExceeded($ipHourId, $this->cfgInt('rateLimitPerIpHour', 100))
            || $this->rateLimitService->isLimitExceeded($customerDayId, $this->cfgInt('rateLimitPerCustomerDay', 5))
        ) {
            throw new RateLimitExceededException('Too many registrations, please try again later.');
        }
    }

    private function trackRateLimits(string $ipAddress, ?string $customerId): void
    {
        $this->rateLimitService->incrementCounter('ip_hour_' . $ipAddress, 3600);
        $this->rateLimitService->incrementCounter('cust_day_' . ($customerId ?? $ipAddress), 86400);
    }

    /**
     * Restricts a criteria to a specific purchasable: a concrete variant when given,
     * otherwise the product itself (with no variant reference).
     */
    private function applyScope(Criteria $criteria, string $productId, ?string $variantId): void
    {
        if ($variantId !== null) {
            $criteria->addFilter(new EqualsFilter('productVariantId', $variantId));

            return;
        }

        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsFilter('productVariantId', null));
    }

    /**
     * Returns the acting admin user id when the delete happens through the Admin
     * API, so the audit log records who removed a notification. Null for
     * storefront, cron and system contexts.
     */
    private function resolveUserId(Context $context): ?string
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource ? $source->getUserId() : null;
    }

    private function writeLog(
        string $action,
        string $email,
        string $productId,
        ?string $reason,
        ?string $ipAddress,
        ?string $userId,
        Context $context
    ): void {
        $this->logRepository->create([[
            'id' => Uuid::randomHex(),
            'action' => $action,
            'userId' => $userId,
            'email' => $email,
            'productId' => $productId,
            'reason' => $reason,
            'ipAddress' => $ipAddress,
        ]], $context);
    }

    private function cfgBool(string $key): bool
    {
        return $this->systemConfigService->getBool(self::CONFIG_DOMAIN . $key);
    }

    private function cfgInt(string $key, int $default): int
    {
        $value = $this->systemConfigService->getInt(self::CONFIG_DOMAIN . $key);

        return $value > 0 ? $value : $default;
    }
}
