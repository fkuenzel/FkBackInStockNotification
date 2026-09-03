import template from './sw-back-in-stock-notification-cron.html.twig';
import './sw-back-in-stock-notification-cron.scss';

Shopware.Component.register('sw-back-in-stock-notification-cron', {
    template,

    inject: ['backInStockNotificationApiService', 'acl'],

    mixins: ['notification'],

    data() {
        return {
            state: null,
            isLoading: false,
            isSending: false,
        };
    },

    computed: {
        healthy() {
            return this.state ? this.state.healthy : true;
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.isLoading = true;
            try {
                this.state = await this.backInStockNotificationApiService.getCronState();
            } catch (error) {
                // Monitoring is non-critical; leave the card empty on error.
            } finally {
                this.isLoading = false;
            }
        },

        async onSendNow() {
            this.isSending = true;
            try {
                const result = await this.backInStockNotificationApiService.sendNow();
                const count = result.sent || 0;
                this.createNotificationSuccess({
                    message: this.$tc('sw-back-in-stock-notification.cron.sendSuccess', count, { count }),
                });
                await this.load();
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('sw-back-in-stock-notification.cron.sendError'),
                });
            } finally {
                this.isSending = false;
            }
        },
    },
});
