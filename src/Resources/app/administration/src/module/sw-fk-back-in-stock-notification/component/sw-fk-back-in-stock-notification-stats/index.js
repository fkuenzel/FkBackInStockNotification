import template from './sw-fk-back-in-stock-notification-stats.html.twig';
import './sw-fk-back-in-stock-notification-stats.scss';

Shopware.Component.register('sw-fk-back-in-stock-notification-stats', {
    template,

    inject: ['backInStockNotificationApiService'],

    mixins: ['notification'],

    data() {
        return {
            stats: { total: 0, today: 0, topProducts: [] },
            isLoading: false,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.isLoading = true;
            try {
                this.stats = await this.backInStockNotificationApiService.getStatistics();
            } catch (error) {
                // Statistics are non-critical; the card simply stays at its defaults.
            } finally {
                this.isLoading = false;
            }
        },
    },
});
