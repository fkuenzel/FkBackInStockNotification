/**
 * Back in Stock Notification Plugin for Shopware 6
 *
 * Storefront plugin for the product-detail notification widget. Handles the
 * AJAX registration form and the "unsubscribe" action shown to already
 * registered customers, giving immediate inline feedback without a page reload.
 *
 * @license GPL-2.0-only
 */
export default class BackInStockNotificationPlugin extends window.PluginBaseClass {
    static options = {
        registerUrl: '',
        removeUrl: '',
        loadingClass: 'is-loading',
    };

    init() {
        this._feedback = this.el.querySelector('.back-in-stock-notification-feedback');
        this._registerForm = this.el.querySelector('.js-bisn-register-form');
        this._removeForm = this.el.querySelector('.js-bisn-remove-form');

        if (this._registerForm) {
            this._registerForm.addEventListener('submit', this._onRegister.bind(this));
        }

        if (this._removeForm) {
            this._removeForm.addEventListener('submit', this._onRemove.bind(this));
        }
    }

    _onRegister(event) {
        event.preventDefault();
        this._submit(this._registerForm, this.options.registerUrl);
    }

    _onRemove(event) {
        event.preventDefault();

        const confirmText = this._removeForm.getAttribute('data-bisn-confirm');
        if (confirmText && !window.confirm(confirmText)) {
            return;
        }

        this._submit(this._removeForm, this.options.removeUrl);
    }

    async _submit(form, url) {
        const button = form.querySelector('button[type="submit"]');
        const spinner = form.querySelector('.back-in-stock-notification-spinner');

        this._setLoading(button, spinner, true);
        this._hideFeedback();

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });
            const data = await response.json();

            if (response.ok && data.success) {
                this._showFeedback(data.message, 'success');
                // On success the form no longer applies; hide it so the state is clear.
                form.setAttribute('hidden', 'hidden');
            } else {
                this._showFeedback(data.message || '', 'danger');
            }
        } catch (error) {
            this._showFeedback('', 'danger');
        } finally {
            this._setLoading(button, spinner, false);
        }
    }

    _setLoading(button, spinner, loading) {
        if (button) {
            button.disabled = loading;
            button.classList.toggle(this.options.loadingClass, loading);
        }
        if (spinner) {
            spinner.hidden = !loading;
        }
    }

    _showFeedback(message, type) {
        if (!this._feedback) {
            return;
        }
        this._feedback.classList.remove('alert-success', 'alert-danger');
        this._feedback.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
        this._feedback.textContent = message;
        this._feedback.hidden = false;
    }

    _hideFeedback() {
        if (this._feedback) {
            this._feedback.hidden = true;
        }
    }
}
