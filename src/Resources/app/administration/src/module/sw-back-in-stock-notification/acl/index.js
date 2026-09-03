/**
 * Maps the DAL-generated privileges (read/create/update/delete on the
 * back_in_stock_notification entity) plus the custom admin API actions onto the
 * viewer/editor/deleter roles shown in the Shopware role editor.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'back_in_stock_notification',
    roles: {
        viewer: {
            privileges: [
                'back_in_stock_notification:read',
                'product:read',
                'customer:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'back_in_stock_notification:update',
            ],
            dependencies: [
                'back_in_stock_notification.viewer',
            ],
        },
        deleter: {
            privileges: [
                'back_in_stock_notification:delete',
            ],
            dependencies: [
                'back_in_stock_notification.viewer',
            ],
        },
    },
});
