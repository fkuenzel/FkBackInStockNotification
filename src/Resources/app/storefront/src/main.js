import BackInStockNotificationPlugin from './fk-back-in-stock-notification/fk-back-in-stock-notification.plugin';
import BackInStockNotificationConfirmPlugin from './fk-back-in-stock-notification/fk-back-in-stock-notification-confirm.plugin';

const PluginManager = window.PluginManager;

PluginManager.register(
    'FkBackInStockNotification',
    BackInStockNotificationPlugin,
    '[data-fk-back-in-stock-notification]'
);

PluginManager.register(
    'FkBackInStockNotificationConfirm',
    BackInStockNotificationConfirmPlugin,
    '[data-bisn-confirm]'
);
