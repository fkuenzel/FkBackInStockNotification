/**
 * Maps the DAL-generated privileges (read/create/update/delete on the
 * fk_back_in_stock_notification entity) plus the custom admin API actions onto the
 * viewer/editor/deleter roles shown in the Shopware role editor.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'fk_back_in_stock_notification',
    roles: {
        viewer: {
            privileges: [
                'fk_back_in_stock_notification:read',
                'product:read',
                'customer:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'fk_back_in_stock_notification:update',
            ],
            dependencies: [
                'fk_back_in_stock_notification.viewer',
            ],
        },
        deleter: {
            privileges: [
                'fk_back_in_stock_notification:delete',
            ],
            dependencies: [
                'fk_back_in_stock_notification.viewer',
            ],
        },
    },
});
