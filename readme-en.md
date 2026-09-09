# Back in Stock Notification (Shopware 6.7)

Notify customers when a sold-out product or variant becomes available again.
Customers register on the product detail page (guests or logged-in), receive a
confirmation mail, and are notified once the item is back in stock. Registered
customers can manage their notifications in their account; shop staff manage all
registrations in the administration.

- Namespace: `fKuenzel\BackInStockNotification`
- Target: Shopware 6.7.x (tested on a live system with Shopware 6.7.13.1)
- License: MIT

## Requirements

- Shopware 6.7.x, PHP 8.2+
- Storefront features require the Shopware Storefront bundle. The plugin also
  boots on headless installations without the Storefront bundle: the storefront
  services and routes are loaded conditionally (see `build()` /
  `configureRoutes()` in the plugin base class), so only the administration and
  API parts are active there.

## Installation

The plugin ships with precompiled storefront and administration assets, so no
Node.js toolchain is required on the target server.

```bash
# copy the plugin to custom/plugins/BackInStockNotification, then:
bin/console plugin:refresh
bin/console plugin:install --activate BackInStockNotification
bin/console assets:install
bin/console theme:compile
bin/console cache:clear
```

Migrations run automatically during `plugin:install`.

### Building assets (developers only)

Only required when you change the storefront JS/SCSS or the administration module:

```bash
bin/build-administration.sh
bin/build-storefront.sh
bin/console theme:compile
bin/console cache:clear
```

## Configuration

All settings live under the plugin configuration (domain
`FkBackInStockNotification.config.*`), e.g. active, allowGuests, sendTime,
notificationValidityDays, tokenValidityDays, auditLogRetentionDays,
rateLimitPerIpHour, rateLimitPerCustomerDay, logLevel, pluginLogRetentionDays.

The administration overview shows all active registrations by default, with a
"only pending" toggle for the short send queue.

## Security notes (operations)

- CSRF / SameSite: Shopware removed its dedicated CSRF token system in 6.5. The
  storefront POST endpoints (register, account delete/delete-all, unsubscribe)
  rely on same-site session cookies, which is the current 6.7 standard. The
  account routes additionally require an authenticated customer.
- Trusted proxies: the registration rate limit keys on the client IP
  (`Request::getClientIp()`). Behind a reverse proxy or load balancer, configure
  `TRUSTED_PROXIES` (and the trusted headers) correctly - otherwise all requests
  appear to come from the proxy (over-throttling) or the client IP can be spoofed
  via `X-Forwarded-For` (under-throttling).
- Unsubscribe is a two-step flow: the tokenised link in the mail is a GET that
  only shows a confirmation page (no side effect); the removal happens on the
  POST the confirmation submits. This prevents mail prefetchers and link scanners
  from unsubscribing accidentally.
- Enumeration: when an email is already registered for a product, the storefront
  returns a distinct "already registered" message. This is a deliberate UX
  choice (the customer should know they are already subscribed); the risk of
  email/registration enumeration is mitigated by the per-IP and per-customer rate
  limits, which count every attempt. If your threat model requires it, switch the
  duplicate case to the same generic success message as a new registration.
- Log export: the plugin log CSV export neutralises CSV/formula injection
  (leading `= + - @` are escaped).

## Developer integration (events)

Other plugins can subscribe to these events (see section 4.6 of the spec):

- `fk-back-in-stock-notification.registered`
- `fk-back-in-stock-notification.sent`
- `fk-back-in-stock-notification.deleted`
- `fk-back-in-stock-notification.expired`

```php
#[AsEventListener(event: 'fk-back-in-stock-notification.registered')]
public function onRegistered(BackInStockNotificationRegisteredEvent $event): void
{
    // your code
}
```

## Uninstall

On uninstall the plugin removes its tables and mail templates unless "keep user
data" is chosen. Removing rows from core tables never aborts the uninstall.

## Troubleshooting

- Component not shown on the product page: it only appears when the product is
  genuinely sold out (available stock 0) AND back-orders are disabled
  (`isCloseout = true`).
- No mails sent: the send job runs once per day at the configured send time; the
  administration cron widget shows the last run and any consecutive failures, and
  offers a manual send. Mail delivery uses the Shopware mail queue.
- Admin build: after changes to the administration module, run
  `bin/build-administration.sh` and clear the cache.

## FAQ

**Is this a newsletter or marketing feature?**
No. Back-in-stock notifications are transactional, product-specific messages and
are fully independent from Shopware's newsletter system - there is no coupling in
either direction. A customer can subscribe to stock notifications without being a
newsletter recipient, and vice versa.

**Do customers need a double opt-in?**
No. The plugin uses single opt-in: submitting the form stores the registration
immediately and sends a confirmation mail. This is legally sufficient because the
messages are transactional (not advertising). Every mail still contains a
one-click unsubscribe link.

**What counts as "in stock"?**
Only physical stock (`stock > 0`). The registration component is shown only when
the product/variant is genuinely sold out (available stock 0) AND back-orders are
disabled (`isCloseout = true`).

**Are product variants supported?**
Yes. Registrations and notifications work at the variant level - each variant has
its own stock status and triggers independently.

**When and how are mails sent?**
Once per day at the configured send time (default 18:00, shop timezone) via the
Shopware mail queue. If several of a customer's watched items become available on
the same day, they receive a single consolidated mail listing all of them.
Items that are still not available stay pending for the next run.

**Can guests register, not only logged-in customers?**
Yes. Guests register with their email address; logged-in customers get the field
prefilled and can manage their registrations in their account area.

**Does uninstalling delete customer data?**
Only if you do not choose "keep user data" during uninstall. When kept, the
tables remain; otherwise the plugin tables are dropped. Rows in core tables are
never a reason to abort the uninstall.
