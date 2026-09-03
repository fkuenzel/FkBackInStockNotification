import BackInStockNotificationPlugin from './back-in-stock-notification/back-in-stock-notification.plugin';
import BackInStockNotificationConfirmPlugin from './back-in-stock-notification/back-in-stock-notification-confirm.plugin';

const PluginManager = window.PluginManager;

PluginManager.register(
    'BackInStockNotification',
    BackInStockNotificationPlugin,
    '[data-back-in-stock-notification]'
);

PluginManager.register(
    'BackInStockNotificationConfirm',
    BackInStockNotificationConfirmPlugin,
    '[data-bisn-confirm]'
);
