import template from './sw-back-in-stock-notification-log.html.twig';
import './sw-back-in-stock-notification-log.scss';

Shopware.Component.register('sw-back-in-stock-notification-log', {
    template,

    inject: ['backInStockNotificationApiService'],

    mixins: ['notification'],

    data() {
        return {
            entries: [],
            level: '',
            term: '',
            isLoading: false,
            autoRefresh: false,
            refreshSeconds: 5,
            refreshTimer: null,
            inFlight: false,
            lastUpdated: null,
            levelOptions: [
                { value: '', label: 'sw-back-in-stock-notification.log.levelAll' },
                { value: 'DEBUG', label: 'sw-back-in-stock-notification.log.levelDebug' },
                { value: 'INFO', label: 'sw-back-in-stock-notification.log.levelInfo' },
                { value: 'WARNING', label: 'sw-back-in-stock-notification.log.levelWarning' },
                { value: 'ERROR', label: 'sw-back-in-stock-notification.log.levelError' },
            ],
            refreshOptions: [
                { value: 5, label: '5s' },
                { value: 10, label: '10s' },
                { value: 30, label: '30s' },
            ],
        };
    },

    computed: {
        queryParams() {
            const params = { lines: 100 };
            if (this.level) params.level = this.level;
            if (this.term) params.query = this.term;
            return params;
        },

        lastUpdatedLabel() {
            return this.lastUpdated ? this.lastUpdated.toLocaleTimeString() : '';
        },
    },

    watch: {
        autoRefresh(active) {
            if (active) {
                this.startAutoRefresh();
            } else {
                this.stopAutoRefresh();
            }
        },

        refreshSeconds() {
            if (this.autoRefresh) {
                this.startAutoRefresh();
            }
        },
    },

    created() {
        this.load();
        document.addEventListener('visibilitychange', this.onVisibilityChange);
    },

    beforeUnmount() {
        this.stopAutoRefresh();
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
    },

    methods: {
        async fetchLogs(silent = false) {
            // Never let auto-refresh stack requests on top of an in-flight load.
            if (this.inFlight) {
                return;
            }
            this.inFlight = true;
            if (!silent) {
                this.isLoading = true;
            }
            try {
                const data = await this.backInStockNotificationApiService.getLogs(this.queryParams);
                this.entries = data.entries || [];
                this.lastUpdated = new Date();
            } catch (error) {
                // A failing background tick must not spam notifications; only the
                // explicit (non-silent) load surfaces an error to the user.
                if (!silent) {
                    this.createNotificationError({
                        message: this.$tc('sw-back-in-stock-notification.log.loadError'),
                    });
                }
            } finally {
                this.inFlight = false;
                if (!silent) {
                    this.isLoading = false;
                }
            }
        },

        load() {
            return this.fetchLogs(false);
        },

        tick() {
            // Pause polling while the tab is hidden - the browser throttles timers
            // there anyway and the admin is not watching.
            if (document.hidden) {
                return;
            }
            this.fetchLogs(true);
        },

        startAutoRefresh() {
            this.stopAutoRefresh();
            this.refreshTimer = window.setInterval(this.tick, this.refreshSeconds * 1000);
        },

        stopAutoRefresh() {
            if (this.refreshTimer) {
                window.clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
        },

        toggleAutoRefresh() {
            this.autoRefresh = !this.autoRefresh;
        },

        onVisibilityChange() {
            // Refresh once immediately when the admin returns to a live-tail view.
            if (!document.hidden && this.autoRefresh) {
                this.fetchLogs(true);
            }
        },

        async onDownload(format) {
            try {
                const blob = await this.backInStockNotificationApiService.downloadLogs({ ...this.queryParams, format });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `back-in-stock-notification-log.${format}`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('sw-back-in-stock-notification.log.downloadError'),
                });
            }
        },

        levelVariant(level) {
            switch (level) {
                case 'ERROR': return 'error';
                case 'WARNING': return 'warning';
                case 'INFO': return 'info';
                default: return 'neutral';
            }
        },
    },
});
