import '../../core/service/api/fk-back-in-stock-notification.api.service';
import './acl';
import './page/sw-fk-back-in-stock-notification-list';
import './component/sw-fk-back-in-stock-notification-stats';
import './component/sw-fk-back-in-stock-notification-cron';
import './component/sw-fk-back-in-stock-notification-log';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('sw-fk-back-in-stock-notification', {
    type: 'plugin',
    name: 'sw-fk-back-in-stock-notification',
    title: 'sw-fk-back-in-stock-notification.general.mainMenuItemGeneral',
    description: 'sw-fk-back-in-stock-notification.general.description',
    color: '#57D9A3',
    icon: 'regular-bell',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        list: {
            component: 'sw-fk-back-in-stock-notification-list',
            path: 'list',
            meta: {
                privilege: 'fk_back_in_stock_notification.viewer',
            },
        },
    },

    navigation: [
        {
            id: 'sw-fk-back-in-stock-notification',
            label: 'sw-fk-back-in-stock-notification.general.mainMenuItemGeneral',
            color: '#57D9A3',
            icon: 'regular-bell',
            path: 'sw.back.in.stock.notification.list',
            position: 100,
            parent: 'sw-catalogue',
            privilege: 'fk_back_in_stock_notification.viewer',
        },
    ],
});
