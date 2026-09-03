<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Subscriber;

use fKuenzel\BackInStockNotification\Event\BackInStockNotificationRegisteredEvent;
use fKuenzel\BackInStockNotification\Service\NotificationMailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends the registration confirmation email when a notification was registered.
 * Failures are logged but never bubble up, so a mail problem cannot break the
 * (already persisted) registration.
 */
class RegistrationMailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationMailService $mailService,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BackInStockNotificationRegisteredEvent::NAME => 'onRegistered',
        ];
    }

    public function onRegistered(BackInStockNotificationRegisteredEvent $event): void
    {
        try {
            $this->mailService->sendRegistrationConfirmation($event->getNotification(), $event->getContext());
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send back in stock registration confirmation.', [
                'notificationId' => $event->getNotification()->getId(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
