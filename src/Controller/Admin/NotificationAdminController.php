<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Controller\Admin;

use fKuenzel\BackInStockNotification\ScheduledTask\SendNotificationsTaskHandler;
use fKuenzel\BackInStockNotification\Service\BackInStockNotificationService;
use fKuenzel\BackInStockNotification\Service\CronStateService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use fKuenzel\BackInStockNotification\Entity\BackInStockNotification\BackInStockNotificationCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin API actions backing the administration module: statistics, cron
 * monitoring, manual send trigger, plugin log viewer/download and an audited
 * bulk delete. The plain notification grid is served by the auto-generated DAL
 * API (back_in_stock_notification) and is not duplicated here.
 *
 * Every route is protected by ACL privileges and scoped to the api context.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class NotificationAdminController
{
    private const LOG_SUBDIR = 'back-in-stock-notification';
    private const MAX_LINES = 100;

    /**
     * @param EntityRepository<BackInStockNotificationCollection> $notificationRepository
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $notificationRepository,
        private readonly EntityRepository $productRepository,
        private readonly CronStateService $cronStateService,
        private readonly SendNotificationsTaskHandler $sendHandler,
        private readonly BackInStockNotificationService $notificationService,
        private readonly string $logsDir,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route(
        path: '/api/_action/back-in-stock-notification/statistics',
        name: 'api.action.back-in-stock-notification.statistics',
        defaults: ['_acl' => ['back_in_stock_notification:read']],
        methods: ['GET']
    )]
    public function statistics(Context $context): JsonResponse
    {
        $total = $this->countActive(null, $context);

        $startOfDay = (new \DateTimeImmutable('today'))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $today = $this->countActive($startOfDay, $context);

        return new JsonResponse([
            'total' => $total,
            'today' => $today,
            'topProducts' => $this->topProducts($context),
        ]);
    }

    #[Route(
        path: '/api/_action/back-in-stock-notification/cron-state',
        name: 'api.action.back-in-stock-notification.cron-state',
        defaults: ['_acl' => ['back_in_stock_notification:read']],
        methods: ['GET']
    )]
    public function cronState(Context $context): JsonResponse
    {
        $state = $this->cronStateService->getState($context);

        return new JsonResponse([
            'lastRunAt' => $state->getLastRunAt()?->format(\DateTimeInterface::ATOM),
            'consecutiveFailures' => $state->getConsecutiveFailures(),
            'lastErrorMessage' => $state->getLastErrorMessage(),
            'lastErrorAt' => $state->getLastErrorAt()?->format(\DateTimeInterface::ATOM),
            'healthy' => $state->getConsecutiveFailures() === 0,
        ]);
    }

    #[Route(
        path: '/api/_action/back-in-stock-notification/send-now',
        name: 'api.action.back-in-stock-notification.send-now',
        defaults: ['_acl' => ['back_in_stock_notification:update']],
        methods: ['POST']
    )]
    public function sendNow(Context $context): JsonResponse
    {
        try {
            $sent = $this->sendHandler->runManually($context);
        } catch (\Throwable $exception) {
            $this->logger->error('Manual send trigger failed.', ['exception' => $exception->getMessage()]);

            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['success' => true, 'sent' => $sent]);
    }

    #[Route(
        path: '/api/_action/back-in-stock-notification/logs',
        name: 'api.action.back-in-stock-notification.logs',
        defaults: ['_acl' => ['back_in_stock_notification:read']],
        methods: ['GET']
    )]
    public function logs(Request $request, Context $context): JsonResponse
    {
        $lines = min((int) $request->query->get('lines', self::MAX_LINES), self::MAX_LINES);
        $level = strtoupper(trim((string) $request->query->get('level', '')));
        $search = trim((string) $request->query->get('query', ''));

        $entries = $this->readLog($lines, $level, $search);

        return new JsonResponse(['entries' => $entries, 'count' => \count($entries)]);
    }

    #[Route(
        path: '/api/_action/back-in-stock-notification/logs/download',
        name: 'api.action.back-in-stock-notification.logs.download',
        defaults: ['_acl' => ['back_in_stock_notification:read']],
        methods: ['GET']
    )]
    public function downloadLogs(Request $request, Context $context): Response
    {
        $level = strtoupper(trim((string) $request->query->get('level', '')));
        $search = trim((string) $request->query->get('query', ''));
        $format = strtolower((string) $request->query->get('format', 'txt')) === 'csv' ? 'csv' : 'txt';

        $entries = $this->readLog(self::MAX_LINES, $level, $search);

        if ($format === 'csv') {
            $body = "datetime,level,message\n";
            foreach ($entries as $entry) {
                $body .= sprintf(
                    "%s,%s,%s\n",
                    $this->csvCell($entry['datetime']),
                    $this->csvCell($entry['level']),
                    $this->csvCell($entry['message'])
                );
            }
            $contentType = 'text/csv';
            $filename = 'back-in-stock-notification-log.csv';
        } else {
            $body = implode("\n", array_map(static fn (array $e): string => $e['raw'], $entries)) . "\n";
            $contentType = 'text/plain';
            $filename = 'back-in-stock-notification-log.txt';
        }

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => $contentType . '; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route(
        path: '/api/_action/back-in-stock-notification/delete',
        name: 'api.action.back-in-stock-notification.delete',
        defaults: ['_acl' => ['back_in_stock_notification:delete']],
        methods: ['POST']
    )]
    public function bulkDelete(Request $request, Context $context): JsonResponse
    {
        /** @var array<int, mixed> $ids */
        $ids = (array) $request->request->all('ids');
        $ids = array_values(array_filter(array_map('strval', $ids), static fn (string $id): bool => $id !== ''));

        if ($ids === []) {
            return new JsonResponse(['success' => false, 'deleted' => 0], Response::HTTP_BAD_REQUEST);
        }

        $deleted = 0;
        foreach ($ids as $id) {
            try {
                // Uses the service so the deletion is audited (with the acting admin
                // user) and the deleted-event is dispatched for third-party plugins.
                $this->notificationService->deleteNotification($id, 'admin_delete', $context);
                ++$deleted;
            } catch (\Throwable $exception) {
                $this->logger->error('Admin bulk delete failed for one notification.', [
                    'id' => $id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return new JsonResponse(['success' => true, 'deleted' => $deleted]);
    }

    private function countActive(?string $createdSince, Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isActive', true));
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        if ($createdSince !== null) {
            $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::GTE => $createdSince]));
        }

        return $this->notificationRepository->search($criteria, $context)->getTotal();
    }

    /**
     * @return array<int, array{productId: string, name: string, count: int}>
     */
    private function topProducts(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isActive', true));
        $criteria->setLimit(1);
        $criteria->addAggregation(new TermsAggregation('perProduct', 'productId'));

        $result = $this->notificationRepository->search($criteria, $context)->getAggregations()->get('perProduct');

        if (!$result instanceof TermsResult) {
            return [];
        }

        $counts = [];
        foreach ($result->getBuckets() as $bucket) {
            $key = $bucket->getKey();
            if ($key !== null) {
                $counts[$key] = $bucket->getCount();
            }
        }

        arsort($counts);
        $counts = \array_slice($counts, 0, 5, true);

        if ($counts === []) {
            return [];
        }

        $names = $this->productNames(array_keys($counts), $context);

        $top = [];
        foreach ($counts as $productId => $count) {
            $top[] = [
                'productId' => (string) $productId,
                'name' => $names[$productId] ?? '',
                'count' => $count,
            ];
        }

        return $top;
    }

    /**
     * @param array<int, string> $productIds
     *
     * @return array<string, string>
     */
    private function productNames(array $productIds, Context $context): array
    {
        if ($productIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', array_values($productIds)));

        $names = [];
        foreach ($this->productRepository->search($criteria, $context)->getEntities() as $product) {
            $names[$product->getId()] = (string) ($product->getTranslation('name') ?? $product->getName());
        }

        return $names;
    }

    /**
     * Reads the tail of the most recent plugin log file, newest line first,
     * optionally filtered by Monolog level and a free-text search.
     *
     * @return array<int, array{raw: string, datetime: string, level: string, message: string}>
     */
    private function readLog(int $lines, string $level, string $search): array
    {
        $file = $this->latestLogFile();
        if ($file === null) {
            return [];
        }

        // Only the tail of the (rotating) log is read - never the whole file - so a
        // large log under heavy traffic does not load into memory. Spec: last 100 lines.
        $content = array_reverse($this->tailLines($file, max($lines, self::MAX_LINES)));
        $entries = [];

        foreach ($content as $raw) {
            $parsed = $this->parseLine($raw);

            if ($level !== '' && $parsed['level'] !== $level) {
                continue;
            }
            if ($search !== '' && stripos($raw, $search) === false) {
                continue;
            }

            $entries[] = $parsed;
            if (\count($entries) >= $lines) {
                break;
            }
        }

        return $entries;
    }

    /**
     * Reads at most the last $maxLines lines of a file by seeking backwards in
     * chunks, so the whole file never needs to be loaded into memory.
     *
     * @return array<int, string> lines in file order (oldest first)
     */
    private function tailLines(string $file, int $maxLines): array
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }

        $chunkSize = 8192;
        $buffer = '';
        $lines = [];

        fseek($handle, 0, \SEEK_END);
        $position = ftell($handle);

        while ($position > 0 && \count($lines) <= $maxLines) {
            $read = (int) min($chunkSize, $position);
            $position -= $read;
            fseek($handle, $position, \SEEK_SET);
            $buffer = fread($handle, $read) . $buffer;
            $lines = explode("\n", $buffer);
        }

        fclose($handle);

        $lines = array_values(array_filter(
            array_map('rtrim', $lines),
            static fn (string $line): bool => $line !== ''
        ));

        return \array_slice($lines, -$maxLines);
    }

    private function latestLogFile(): ?string
    {
        $dir = rtrim($this->logsDir, '/') . '/' . self::LOG_SUBDIR;
        if (!is_dir($dir)) {
            return null;
        }

        $files = glob($dir . '/*.log') ?: [];
        if ($files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    /**
     * @return array{raw: string, datetime: string, level: string, message: string}
     */
    private function parseLine(string $raw): array
    {
        // Monolog default line: [2026-09-01T10:00:00+00:00] channel.LEVEL: message ...
        if (preg_match('/^\[(?<datetime>[^\]]+)\]\s+\S+\.(?<level>[A-Z]+):\s?(?<message>.*)$/', $raw, $m) === 1) {
            return [
                'raw' => $raw,
                'datetime' => $m['datetime'],
                'level' => $m['level'],
                'message' => $m['message'],
            ];
        }

        return ['raw' => $raw, 'datetime' => '', 'level' => '', 'message' => $raw];
    }

    /**
     * Quotes a CSV cell and neutralises CSV/formula injection: a leading
     * = + - @ (or tab/CR) would otherwise be interpreted as a formula by
     * spreadsheet applications, so it is prefixed with a single quote.
     */
    private function csvCell(string $value): string
    {
        if ($value !== '' && \in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'" . $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
