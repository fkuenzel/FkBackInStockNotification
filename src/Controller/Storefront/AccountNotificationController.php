<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Controller\Storefront;

use fKuenzel\BackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationCollection;
use fKuenzel\BackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationEntity;
use fKuenzel\BackInStockNotification\Service\BackInStockNotificationService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Customer account area: lists the logged-in customer's active back-in-stock
 * notifications and lets them remove single entries or all at once.
 *
 * All routes require an authenticated customer (_loginRequired). Every mutating
 * action verifies that the targeted notification actually belongs to the current
 * customer before deleting, so IDs cannot be tampered with.
 */
#[Route(defaults: [
    PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID],
    PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
])]
class AccountNotificationController extends StorefrontController
{
    /**
     * @param EntityRepository<BackInStockNotificationCollection> $notificationRepository
     */
    public function __construct(
        private readonly BackInStockNotificationService $notificationService,
        private readonly EntityRepository $notificationRepository,
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/account/fk-back-in-stock-notifications',
        name: 'frontend.account.fk-back-in-stock-notification.list',
        methods: ['GET']
    )]
    public function list(Request $request, SalesChannelContext $context): Response
    {
        $customer = $this->requireCustomer($context);
        $page = $this->genericPageLoader->load($request, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));
        $criteria->addFilter(new EqualsFilter('isActive', true));
        $criteria->addAssociation('product');
        $criteria->addAssociation('productVariant');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        /** @var BackInStockNotificationCollection $notifications */
        $notifications = $this->notificationRepository->search($criteria, $context->getContext())->getEntities();

        return $this->renderStorefront('@BackInStockNotification/storefront/page/account/fk-back-in-stock-notifications.html.twig', [
            'page' => $page,
            'notifications' => $notifications,
        ]);
    }

    #[Route(
        path: '/account/fk-back-in-stock-notifications/{id}/delete',
        name: 'frontend.account.fk-back-in-stock-notification.delete',
        methods: ['POST']
    )]
    public function delete(string $id, SalesChannelContext $context): Response
    {
        $customer = $this->requireCustomer($context);

        $notification = $this->loadOwnedNotification($id, $customer, $context);
        if ($notification === null) {
            $this->addFlash(self::DANGER, $this->trans('backInStockNotification.account.deleteError'));

            return $this->redirectToRoute('frontend.account.fk-back-in-stock-notification.list');
        }

        try {
            $this->notificationService->deleteNotification($id, 'user_request', $context->getContext());
            $this->addFlash(self::SUCCESS, $this->trans('backInStockNotification.account.deleteSuccess'));
        } catch (\Throwable $exception) {
            $this->logger->error('Account notification delete failed.', ['exception' => $exception->getMessage()]);
            $this->addFlash(self::DANGER, $this->trans('backInStockNotification.account.deleteError'));
        }

        return $this->redirectToRoute('frontend.account.fk-back-in-stock-notification.list');
    }

    #[Route(
        path: '/account/fk-back-in-stock-notifications/delete-all',
        name: 'frontend.account.fk-back-in-stock-notification.delete-all',
        methods: ['POST']
    )]
    public function deleteAll(SalesChannelContext $context): Response
    {
        $customer = $this->requireCustomer($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));
        $criteria->addFilter(new EqualsFilter('isActive', true));

        $ids = $this->notificationRepository->searchIds($criteria, $context->getContext())->getIds();

        $deleted = 0;
        foreach ($ids as $notificationId) {
            try {
                $this->notificationService->deleteNotification((string) $notificationId, 'user_request', $context->getContext());
                ++$deleted;
            } catch (\Throwable $exception) {
                $this->logger->error('Account notification bulk delete failed.', [
                    'id' => $notificationId,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        if ($deleted > 0) {
            $this->addFlash(self::SUCCESS, $this->trans('backInStockNotification.account.deleteAllSuccess', ['%count%' => $deleted]));
        } else {
            $this->addFlash(self::INFO, $this->trans('backInStockNotification.account.empty'));
        }

        return $this->redirectToRoute('frontend.account.fk-back-in-stock-notification.list');
    }

    #[Route(
        path: '/fk-back-in-stock-notification/remove',
        name: 'frontend.fk-back-in-stock-notification.remove',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function removeByProduct(Request $request, SalesChannelContext $context): JsonResponse
    {
        $customer = $this->requireCustomer($context);

        $productId = (string) $request->request->get('productId');
        $variantId = $request->request->get('variantId');
        $variantId = is_string($variantId) && $variantId !== '' ? $variantId : null;

        if ($productId === '') {
            return new JsonResponse(['success' => false], Response::HTTP_BAD_REQUEST);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsFilter('productVariantId', $variantId));
        $criteria->addFilter(new EqualsFilter('isActive', true));

        $ids = $this->notificationRepository->searchIds($criteria, $context->getContext())->getIds();

        foreach ($ids as $notificationId) {
            try {
                $this->notificationService->deleteNotification((string) $notificationId, 'user_request', $context->getContext());
            } catch (\Throwable $exception) {
                $this->logger->error('Product-page unsubscribe failed.', [
                    'id' => $notificationId,
                    'exception' => $exception->getMessage(),
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => $this->trans('backInStockNotification.account.deleteError'),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return new JsonResponse([
            'success' => true,
            'message' => $this->trans('backInStockNotification.widget.unsubscribed'),
        ]);
    }

    private function loadOwnedNotification(string $id, CustomerEntity $customer, SalesChannelContext $context): ?BackInStockNotificationEntity
    {
        /** @var BackInStockNotificationEntity|null $notification */
        $notification = $this->notificationRepository->search(new Criteria([$id]), $context->getContext())->getEntities()->first();

        if ($notification === null || $notification->getCustomerId() !== $customer->getId()) {
            return null;
        }

        return $notification;
    }

    private function requireCustomer(SalesChannelContext $context): CustomerEntity
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            // _loginRequired guarantees a customer; this guard keeps static analysis honest.
            throw $this->createAccessDeniedException();
        }

        return $customer;
    }
}
