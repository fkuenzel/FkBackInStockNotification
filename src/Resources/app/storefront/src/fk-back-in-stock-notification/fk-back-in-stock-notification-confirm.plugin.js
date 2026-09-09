/**
 * Adds a confirmation dialog before a delete form is submitted in the customer
 * account area (single delete and delete-all). These forms perform a normal
 * full-page POST; this plugin only guards the submit.
 *
 * @license MIT
 */
export default class BackInStockNotificationConfirmPlugin extends window.PluginBaseClass {
    init() {
        this.el.addEventListener('submit', this._onSubmit.bind(this));
    }

    _onSubmit(event) {
        const message = this.el.getAttribute('data-bisn-confirm');
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    }
}
