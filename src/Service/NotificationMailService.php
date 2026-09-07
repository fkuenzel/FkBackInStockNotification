<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Service;

use fKuenzel\BackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationEntity;
use fKuenzel\BackInStockNotification\Entity\CronState\BackInStockNotificationCronStateEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * Sends all plugin emails exclusively through the Shopware MailService (never mail()),
 * which routes them through the configured mail queue for asynchronous delivery.
 */
class NotificationMailService
{
    public const TYPE_REGISTER = 'fk_back_in_stock_notification_register';
    public const TYPE_AVAILABLE = 'fk_back_in_stock_notification_available';

    private const UNSUBSCRIBE_PATH = '/fk-back-in-stock-notification/unsubscribe/';

    /**
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly EntityRepository $salesChannelDomainRepository,
        private readonly \Shopware\Core\System\SystemConfig\SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Sends the immediate single-opt-in registration confirmation for one notification.
     */
    public function sendRegistrationConfirmation(BackInStockNotificationEntity $notification, Context $context): void
    {
        $languageContext = $this->languageContext($notification->getLanguageId(), $context);
        $mailTemplate = $this->loadMailTemplate(self::TYPE_REGISTER, $languageContext);

        $baseUrl = $this->resolveBaseUrl($notification->getSalesChannelId(), $notification->getLanguageId(), $context);
        $product = $notification->getProductVariant() ?? $notification->getProduct();

        $this->dispatch(
            $mailTemplate,
            $notification->getEmail(),
            $notification->getSalesChannelId(),
            $languageContext,
            [
                'notification' => $notification,
                'product' => $product,
                'shopName' => $this->shopName($notification->getSalesChannelId()),
                'productUrl' => $product !== null ? $baseUrl . '/detail/' . $product->getId() : $baseUrl,
                'unsubscribeUrl' => $baseUrl . self::UNSUBSCRIBE_PATH . $notification->getUnsubscribeToken(),
            ]
        );
    }

    /**
     * Sends one consolidated availability email listing all products for a recipient.
     *
     * @param BackInStockNotificationEntity[] $notifications
     */
    public function sendAvailabilityNotification(string $email, array $notifications, Context $context): void
    {
        if ($notifications === []) {
            return;
        }

        $first = $notifications[0];
        $languageContext = $this->languageContext($first->getLanguageId(), $context);
        $mailTemplate = $this->loadMailTemplate(self::TYPE_AVAILABLE, $languageContext);

        $baseUrl = $this->resolveBaseUrl($first->getSalesChannelId(), $first->getLanguageId(), $context);

        $items = [];
        foreach ($notifications as $notification) {
            $product = $notification->getProductVariant() ?? $notification->getProduct();
            $items[] = [
                'product' => $product,
                'productUrl' => $product !== null ? $baseUrl . '/detail/' . $product->getId() : $baseUrl,
                'unsubscribeUrl' => $baseUrl . self::UNSUBSCRIBE_PATH . $notification->getUnsubscribeToken(),
            ];
        }

        $this->dispatch(
            $mailTemplate,
            $email,
            $first->getSalesChannelId(),
            $languageContext,
            [
                'email' => $email,
                'notifications' => $notifications,
                'items' => $items,
                'shopName' => $this->shopName($first->getSalesChannelId()),
                'count' => \count($items),
            ]
        );
    }

    /**
     * Sends a plain administrative alert when the daily send cron failed repeatedly.
     */
    public function sendCronFailureAlert(BackInStockNotificationCronStateEntity $state, Context $context): void
    {
        $adminEmail = $this->systemConfigService->getString('core.basicInformation.email');
        if ($adminEmail === '') {
            $this->logger->error('Cannot send cron failure alert: no shop email configured.');

            return;
        }

        $lastErrorAt = $state->getLastErrorAt()?->format('Y-m-d H:i') ?? 'unknown';
        $adminUrl = rtrim((string) (getenv('APP_URL') ?: ''), '/') . '/admin';

        $plain = sprintf(
            "The daily back-in-stock notification cron has failed %d times in a row.\n\n"
            . "Last error at: %s\nLast error: %s\n\nPlease check the plugin logs in the administration: %s",
            $state->getConsecutiveFailures(),
            $lastErrorAt,
            (string) $state->getLastErrorMessage(),
            $adminUrl
        );

        $data = [
            'recipients' => [$adminEmail => $adminEmail],
            'senderName' => $this->shopName(null),
            'salesChannelId' => null,
            'subject' => sprintf('[Back in Stock] Cron failed %d times', $state->getConsecutiveFailures()),
            'contentHtml' => nl2br(htmlspecialchars($plain, \ENT_QUOTES)),
            'contentPlain' => $plain,
        ];

        $this->mailService->send($data, $context, []);
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function dispatch(
        MailTemplateEntity $mailTemplate,
        string $email,
        ?string $salesChannelId,
        Context $context,
        array $templateData
    ): void {
        $data = [
            'recipients' => [$email => $email],
            'senderName' => $mailTemplate->getSenderName() ?: $this->shopName($salesChannelId),
            'salesChannelId' => $salesChannelId,
            'subject' => (string) $mailTemplate->getSubject(),
            'contentHtml' => (string) $mailTemplate->getContentHtml(),
            'contentPlain' => (string) $mailTemplate->getContentPlain(),
        ];

        $this->mailService->send($data, $context, $templateData);
    }

    private function loadMailTemplate(string $technicalName, Context $context): MailTemplateEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', $technicalName));

        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->getEntities()->first();

        if (!$mailTemplate instanceof MailTemplateEntity) {
            $this->logger->error('Back in stock mail template is missing.', ['technicalName' => $technicalName]);

            throw new \RuntimeException(sprintf('Mail template "%s" is not available.', $technicalName));
        }

        return $mailTemplate;
    }

    private function resolveBaseUrl(?string $salesChannelId, ?string $languageId, Context $context): string
    {
        if ($salesChannelId !== null) {
            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
            if ($languageId !== null) {
                $criteria->addFilter(new EqualsFilter('languageId', $languageId));
            }

            $domain = $this->salesChannelDomainRepository->search($criteria, $context)->getEntities()->first();
            if ($domain !== null) {
                return rtrim($domain->getUrl(), '/');
            }
        }

        return rtrim((string) (getenv('APP_URL') ?: ''), '/');
    }

    private function shopName(?string $salesChannelId): string
    {
        return (string) $this->systemConfigService->getString('core.basicInformation.shopName', $salesChannelId);
    }

    private function languageContext(?string $languageId, Context $context): Context
    {
        if ($languageId === null || $languageId === Defaults::LANGUAGE_SYSTEM) {
            return $context;
        }

        return new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            [$languageId, Defaults::LANGUAGE_SYSTEM]
        );
    }
}
