<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Controller\Storefront;

use fKuenzel\BackInStockNotification\Exception\DuplicateNotificationException;
use fKuenzel\BackInStockNotification\Exception\InvalidEmailException;
use fKuenzel\BackInStockNotification\Exception\ProductNotEligibleException;
use fKuenzel\BackInStockNotification\Exception\RateLimitExceededException;
use fKuenzel\BackInStockNotification\Service\BackInStockNotificationService;
use Psr\Log\LoggerInterface;
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
 * Storefront endpoints for registering a back-in-stock notification and for
 * unsubscribing via the tokenised link contained in every mail.
 *
 * Unsubscribe is a two-step flow: the link in the mail is a GET that only shows
 * a confirmation page (no side effect, so mail prefetchers or link scanners
 * cannot unsubscribe by accident); the actual removal happens on the POST that
 * the confirmation form submits.
 *
 * Note on CSRF: Shopware removed its dedicated CSRF token system in 6.5; the
 * storefront now relies on same-site session cookies, so no explicit CSRF field
 * is generated here. This is the current 6.7 standard.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class NotificationController extends StorefrontController
{
    public function __construct(
        private readonly BackInStockNotificationService $notificationService,
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/fk-back-in-stock-notification/register',
        name: 'frontend.fk-back-in-stock-notification.register',
        defaults: ['XmlHttpRequest' => true],
        methods: ['POST']
    )]
    public function register(Request $request, SalesChannelContext $context): JsonResponse
    {
        $email = trim((string) $request->request->get('email'));
        $productId = (string) $request->request->get('productId');
        $variantId = $request->request->get('variantId');
        $variantId = is_string($variantId) && $variantId !== '' ? $variantId : null;

        $customer = $context->getCustomer();
        if ($customer !== null && $email === '') {
            $email = (string) $customer->getEmail();
        }

        if ($productId === '') {
            return $this->error('backInStockNotification.register.error', Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->notificationService->registerNotification(
                $email,
                $productId,
                $variantId,
                $customer?->getId(),
                (string) ($request->getClientIp() ?? ''),
                $request->headers->get('User-Agent'),
                $context->getSalesChannelId(),
                $context->getContext()->getLanguageId(),
                $context->getContext()
            );
        } catch (InvalidEmailException) {
            return $this->error('backInStockNotification.register.invalidEmail', Response::HTTP_BAD_REQUEST);
        } catch (DuplicateNotificationException) {
            // Variant A: never reveal that this email/customer is already
            // registered. Returning the same neutral success response as a fresh
            // registration prevents registration enumeration (an attacker probing
            // arbitrary addresses for a given product) and matches the intended
            // behaviour: only the interested party themselves sees a distinct
            // "already registered" state, and that is rendered server-side in the
            // widget (ProductPageSubscriber::alreadyRegistered), not here.
            return $this->success();
        } catch (RateLimitExceededException) {
            return $this->error('backInStockNotification.register.rateLimited', Response::HTTP_TOO_MANY_REQUESTS);
        } catch (ProductNotEligibleException) {
            return $this->error('backInStockNotification.register.notEligible', Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            $this->logger->error('Back in stock registration failed.', ['exception' => $exception->getMessage()]);

            return $this->error('backInStockNotification.register.error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->success();
    }

    /**
     * Landing page for the unsubscribe link in the mail. GET has no side effect;
     * it only validates the token and shows a confirmation form.
     */
    #[Route(
        path: '/fk-back-in-stock-notification/unsubscribe/{token}',
        name: 'frontend.fk-back-in-stock-notification.unsubscribe',
        methods: ['GET']
    )]
    public function unsubscribeConfirm(string $token, Request $request, SalesChannelContext $context): Response
    {
        $valid = false;

        try {
            $valid = $this->notificationService->validateUnsubscribeToken($token, $context->getContext());
        } catch (\Throwable $exception) {
            $this->logger->error('Back in stock unsubscribe token validation failed.', ['exception' => $exception->getMessage()]);
        }

        return $this->renderUnsubscribe($valid ? 'confirm' : 'error', $token, $request, $context);
    }

    /**
     * Performs the actual unsubscribe after the confirmation form is submitted.
     */
    #[Route(
        path: '/fk-back-in-stock-notification/unsubscribe/{token}',
        name: 'frontend.fk-back-in-stock-notification.unsubscribe.confirm',
        methods: ['POST']
    )]
    public function unsubscribe(string $token, Request $request, SalesChannelContext $context): Response
    {
        $success = false;

        try {
            $success = $this->notificationService->unsubscribeByToken($token, $context->getContext());
        } catch (\Throwable $exception) {
            $this->logger->error('Back in stock unsubscribe failed.', ['exception' => $exception->getMessage()]);
        }

        return $this->renderUnsubscribe($success ? 'success' : 'error', $token, $request, $context);
    }

    private function renderUnsubscribe(string $mode, string $token, Request $request, SalesChannelContext $context): Response
    {
        $page = $this->genericPageLoader->load($request, $context);

        return $this->renderStorefront('@BackInStockNotification/storefront/page/fk-back-in-stock-notification/unsubscribe.html.twig', [
            'page' => $page,
            'unsubscribeMode' => $mode,
            'unsubscribeToken' => $token,
        ]);
    }

    private function success(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $this->trans('backInStockNotification.register.success'),
        ]);
    }

    private function error(string $snippet, int $status): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->trans($snippet),
        ], $status);
    }
}
