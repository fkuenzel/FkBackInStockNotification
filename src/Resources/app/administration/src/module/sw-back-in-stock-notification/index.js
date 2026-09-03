import '../../core/service/api/back-in-stock-notification.api.service';
import './acl';
import './page/sw-back-in-stock-notification-list';
import './component/sw-back-in-stock-notification-stats';
import './component/sw-back-in-stock-notification-cron';
import './component/sw-back-in-stock-notification-log';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-back-in-stock-notification', {
    type: 'plugin',
    name: 'sw-back-in-stock-notification',
    title: 'sw-back-in-stock-notification.general.mainMenuItemGeneral',
    description: 'sw-back-in-stock-notification.general.description',
    color: '#57D9A3',
    icon: 'regular-bell',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        list: {
            component: 'sw-back-in-stock-notification-list',
            path: 'list',
            meta: {
                privilege: 'back_in_stock_notification.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'sw-back-in-stock-notification',
            label: 'sw-back-in-stock-notification.general.mainMenuItemGeneral',
            color: '#57D9A3',
            icon: 'regular-bell',
            path: 'sw.back.in.stock.notification.list',
            position: 100,
            parent: 'sw-catalogue',
            privilege: 'back_in_stock_notification.viewer',
        },
    ],
});
