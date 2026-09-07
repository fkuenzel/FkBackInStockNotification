<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification\Service\Log;

use fKuenzel\BackInStockNotification\Service\BackInStockNotificationService;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Builds the plugin's own daily-rotating file logger.
 *
 * The log level (Full = debug, Dev = warning, Error = error) and the retention
 * (number of daily files kept) come from the plugin configuration. The retention
 * doubles as automatic file purging via RotatingFileHandler::$maxFiles. The level
 * is read when the logger is instantiated; a change applies to new processes
 * (web requests immediately, long-running workers after their next restart).
 */
class PluginLoggerFactory
{
    private const CHANNEL = 'fk_back_in_stock_notification';
    private const DEFAULT_RETENTION = 30;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly string $logsDir
    ) {
    }

    public function create(): LoggerInterface
    {
        $levelKey = $this->systemConfigService->getString(
            BackInStockNotificationService::CONFIG_DOMAIN . 'logLevel'
        );

        $level = match ($levelKey) {
            'debug' => Level::Debug,
            'error' => Level::Error,
            default => Level::Warning,
        };

        $retention = $this->systemConfigService->getInt(
            BackInStockNotificationService::CONFIG_DOMAIN . 'pluginLogRetentionDays'
        );
        $maxFiles = $retention > 0 ? $retention : self::DEFAULT_RETENTION;

        $file = rtrim($this->logsDir, '/') . '/' . self::CHANNEL . '/' . self::CHANNEL . '.log';

        $handler = new RotatingFileHandler($file, $maxFiles, $level);

        $logger = new Logger(self::CHANNEL);
        $logger->pushHandler($handler);

        return $logger;
    }
}
