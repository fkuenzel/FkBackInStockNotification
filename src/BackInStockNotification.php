<?php declare(strict_types=1);

/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * This plugin is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * @license GPL-2.0-only
 */

namespace fKuenzel\BackInStockNotification;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class BackInStockNotification extends Plugin
{
    private const MAIL_TEMPLATE_TYPES = [
        'back_in_stock_notification_register',
        'back_in_stock_notification_available',
    ];

    /**
     * Loads the storefront-only service definitions, but only when the Storefront
     * bundle is installed. This keeps the plugin bootable on headless setups where
     * the storefront controllers and their dependencies (e.g. the generic page
     * loader) do not exist.
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        if (!class_exists(\Shopware\Storefront\Storefront::class)) {
            return;
        }

        $loader = new XmlFileLoader($container, new FileLocator($this->getPath() . '/Resources/config'));
        $loader->load('services_storefront.xml');
    }

    /**
     * Registers the storefront routes only when the plugin is active and the
     * Storefront bundle is present, so the attribute route loader never reflects
     * on the storefront controllers on a headless installation.
     */
    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        parent::configureRoutes($routes, $environment);

        if (!$this->isActive() || !class_exists(\Shopware\Storefront\Storefront::class)) {
            return;
        }

        $routes->import($this->getPath() . '/Resources/config/routes_storefront.xml');
    }

    /**
     * Removes all plugin data on uninstall, unless the admin chose to keep it.
     * Drops the plugin tables and removes the mail templates that were added to
     * the Shopware core tables.
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $container = $this->container;
        if ($container === null) {
            return;
        }

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        // Removing rows from core tables must never abort the uninstall (e.g. when a
        // flow still references a template); the plugin tables are dropped regardless.
        try {
            $this->removeMailTemplates($connection);
        } catch (\Throwable) {
            // Intentionally ignored: leftover mail templates are harmless after uninstall.
        }

        $connection->executeStatement('DROP TABLE IF EXISTS `back_in_stock_notification_log`');
        $connection->executeStatement('DROP TABLE IF EXISTS `back_in_stock_notification_cron_state`');
        $connection->executeStatement('DROP TABLE IF EXISTS `back_in_stock_notification`');
    }

    private function removeMailTemplates(Connection $connection): void
    {
        $typeIds = $connection->fetchFirstColumn(
            'SELECT id FROM mail_template_type WHERE technical_name IN (:names)',
            ['names' => self::MAIL_TEMPLATE_TYPES],
            ['names' => ArrayParameterType::STRING]
        );

        if ($typeIds === []) {
            return;
        }

        // Translations are removed via their ON DELETE CASCADE foreign keys.
        $connection->executeStatement(
            'DELETE FROM mail_template WHERE mail_template_type_id IN (:ids)',
            ['ids' => $typeIds],
            ['ids' => ArrayParameterType::BINARY]
        );
        $connection->executeStatement(
            'DELETE FROM mail_template_type WHERE id IN (:ids)',
            ['ids' => $typeIds],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
