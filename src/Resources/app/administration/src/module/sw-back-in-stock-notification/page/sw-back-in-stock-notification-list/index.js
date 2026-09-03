import template from './sw-back-in-stock-notification-list.html.twig';
import './sw-back-in-stock-notification-list.scss';

const { Criteria, EntityCollection } = Shopware.Data;

// Criteria total-count mode: 1 = exact total (needed for the pagination "x of y").
const TOTAL_COUNT_MODE_EXACT = 1;

Shopware.Component.register('sw-back-in-stock-notification-list', {
    template,

    inject: ['repositoryFactory', 'backInStockNotificationApiService', 'acl'],

    mixins: ['notification'],

    data() {
        return {
            notifications: null,
            total: 0,
            isLoading: false,
            page: 1,
            limit: 25,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            term: '',
            dateFrom: null,
            dateTo: null,
            onlyPending: false,
            productIds: [],
            productCollection: null,
            showFilters: false,
            selection: {},
            deleteId: null,
            showBulkDeleteModal: false,
            isDeleting: false,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('back_in_stock_notification');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        selectionCount() {
            return Object.keys(this.selection).length;
        },

        activeFilterCount() {
            let count = 0;
            if (this.onlyPending) count += 1;
            if (this.dateFrom) count += 1;
            if (this.dateTo) count += 1;
            if (this.productIds.length) count += 1;
            return count;
        },

        listCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.setTotalCountMode(TOTAL_COUNT_MODE_EXACT);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            // Default view: all active registrations (decision with Fabian).
            criteria.addFilter(Criteria.equals('isActive', true));

            if (this.onlyPending) {
                criteria.addFilter(Criteria.equals('isPending', true));
            }
            if (this.term) {
                criteria.addFilter(Criteria.contains('email', this.term));
            }
            if (this.dateFrom) {
                criteria.addFilter(Criteria.range('createdAt', { gte: this.dateFrom }));
            }
            if (this.dateTo) {
                criteria.addFilter(Criteria.range('createdAt', { lte: this.dateTo }));
            }
            if (this.productIds.length) {
                criteria.addFilter(Criteria.equalsAny('productId', this.productIds));
            }

            criteria.addAssociation('product');
            criteria.addAssociation('productVariant');
            criteria.addAssociation('customer');

            return criteria;
        },

        columns() {
            return [
                { property: 'email', label: 'sw-back-in-stock-notification.list.columnEmail', allowResize: true, primary: true, sortable: true },
                { property: 'customerId', label: 'sw-back-in-stock-notification.list.columnCustomer', allowResize: true, sortable: false },
                { property: 'productId', label: 'sw-back-in-stock-notification.list.columnProduct', allowResize: true, sortable: false },
                { property: 'isPending', label: 'sw-back-in-stock-notification.list.columnStatus', allowResize: true, sortable: true },
                { property: 'createdAt', label: 'sw-back-in-stock-notification.list.columnCreatedAt', allowResize: true, sortable: true },
            ];
        },
    },

    created() {
        this.productCollection = new EntityCollection('/product', 'product', Shopware.Context.api, new Criteria());
        this.getList();
    },

    watch: {
        productCollection: {
            handler(collection) {
                this.productIds = collection ? collection.getIds() : [];
            },
            deep: true,
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;
            try {
                const result = await this.repository.search(this.listCriteria);
                this.notifications = result;
                this.total = result.total;
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('sw-back-in-stock-notification.list.loadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onRefresh() {
            this.getList();
        },

        onSearch(term) {
            this.term = term;
            this.page = 1;
            this.getList();
        },

        onSortColumn(column) {
            if (this.sortBy === column.property) {
                this.sortDirection = this.sortDirection === 'ASC' ? 'DESC' : 'ASC';
            } else {
                this.sortBy = column.property;
                this.sortDirection = 'ASC';
            }
            this.getList();
        },

        onPageChange({ page = 1, limit = 25 }) {
            this.page = page;
            this.limit = limit;
            this.getList();
        },

        onToggleFilters() {
            this.showFilters = !this.showFilters;
        },

        onApplyFilters() {
            this.page = 1;
            this.getList();
        },

        onResetFilters() {
            this.onlyPending = false;
            this.dateFrom = null;
            this.dateTo = null;
            this.productIds = [];
            this.term = '';
            this.page = 1;
            this.getList();
        },

        onSelectionChange(selection) {
            this.selection = selection;
        },

        productLabel(item) {
            const display = item.productVariant || item.product;
            if (!display) {
                return this.$tc('sw-back-in-stock-notification.list.productRemoved');
            }
            return display.translated ? (display.translated.name || display.name) : display.name;
        },

        productLink(item) {
            const id = item.productVariantId || item.productId;
            return { name: 'sw.product.detail', params: { id } };
        },

        customerLink(item) {
            if (!item.customerId) {
                return null;
            }
            return { name: 'sw.customer.detail', params: { id: item.customerId } };
        },

        onDeleteItem(id) {
            this.deleteId = id;
        },

        onCloseDeleteModal() {
            this.deleteId = null;
        },

        onConfirmDelete() {
            const id = this.deleteId;
            this.deleteId = null;
            this.runDelete([id]);
        },

        onBulkDelete() {
            this.showBulkDeleteModal = true;
        },

        onCloseBulkDeleteModal() {
            this.showBulkDeleteModal = false;
        },

        onConfirmBulkDelete() {
            this.showBulkDeleteModal = false;
            this.runDelete(Object.keys(this.selection));
        },

        async runDelete(ids) {
            if (!ids || !ids.length) {
                return;
            }
            this.isDeleting = true;
            try {
                const result = await this.backInStockNotificationApiService.bulkDelete(ids);
                this.selection = {};
                const count = result.deleted || ids.length;
                this.createNotificationSuccess({
                    message: this.$tc('sw-back-in-stock-notification.list.deleteSuccess', count, { count }),
                });
                await this.getList();
            } catch (error) {
                this.createNotificationError({
                    message: this.$tc('sw-back-in-stock-notification.list.deleteError'),
                });
            } finally {
                this.isDeleting = false;
            }
        },
    },
});
