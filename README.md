# Benachrichtigung bei Wiederverfügbarkeit (Shopware 6.7)

Benachrichtigt Kundinnen und Kunden, sobald ein ausverkauftes Produkt oder eine
ausverkaufte Variante wieder verfügbar ist. Die Anmeldung erfolgt auf der
Produktdetailseite (als Gast oder eingeloggt), gefolgt von einer
Bestätigungsmail; sobald der Artikel wieder auf Lager ist, wird automatisch
benachrichtigt. Eingeloggte Kunden verwalten ihre Benachrichtigungen im
Kundenkonto, das Shop-Team verwaltet alle Anmeldungen in der Administration.

> Englische Version / English version: siehe [readme-en.md](readme-en.md)

- Namespace: `fKuenzel\BackInStockNotification`
- Ziel: Shopware 6.7.x (getestet auf Live-System mit Shopware 6.7.13.1)
- Lizenz: GPL-2.0-only

## Voraussetzungen

- Shopware 6.7.x, PHP 8.2+
- Storefront-Funktionen benötigen das Shopware-Storefront-Bundle. Das Plugin
  bootet auch auf Headless-Installationen ohne Storefront-Bundle: Storefront-
  Services und -Routen werden bedingt geladen (siehe `build()` /
  `configureRoutes()` in der Plugin-Basisklasse), sodass dort nur Administration
  und API aktiv sind.

## Installation

Das Plugin liefert die kompilierten Storefront- und Administration-Assets bereits
mit. Auf dem Zielserver ist daher **keine Node.js-Toolchain** erforderlich.

```bash
# Plugin nach custom/plugins/BackInStockNotification kopieren, dann:
bin/console plugin:refresh
bin/console plugin:install --activate BackInStockNotification
bin/console assets:install
bin/console theme:compile
bin/console cache:clear
```

Die Datenbank-Migrationen laufen automatisch während `plugin:install`.

### Assets bauen (nur für Entwickler)

Nur nötig, wenn das Storefront-JS/SCSS oder das Administration-Modul geändert wird:

```bash
bin/build-administration.sh
bin/build-storefront.sh
bin/console theme:compile
bin/console cache:clear
```

### Qualitätssicherung (nur für Entwickler)

```bash
# statische Analyse (PHPStan Level 8)
vendor/bin/phpstan analyse

# Unit- und Integration-Tests
vendor/bin/phpunit
```

Hinweis: Die Integration-Tests benötigen eine gebootete Shopware-Test-Umgebung
mit eigener Test-Datenbank (`APP_ENV=test`) und gehören in eine Dev-/CI-Umgebung,
nicht auf einen Produktions- oder Demo-Host.

## Konfiguration

Alle Einstellungen liegen in der Plugin-Konfiguration (Domain
`FkBackInStockNotification.config.*`), u. a. active, allowGuests, sendTime,
notificationValidityDays, tokenValidityDays, auditLogRetentionDays,
rateLimitPerIpHour, rateLimitPerCustomerDay, logLevel, pluginLogRetentionDays.

Die Admin-Übersicht zeigt standardmäßig alle aktiven Anmeldungen; per Umschalter
"Nur ausstehende" lässt sich die kurze Versand-Warteschlange filtern.

## Sicherheitshinweise (Betrieb)

- CSRF / SameSite: Shopware hat das eigene CSRF-Token-System mit 6.5 entfernt. Die
  Storefront-POST-Endpunkte (Anmeldung, Konto-Löschen/Alle-Löschen, Abmeldung)
  stützen sich auf SameSite-Session-Cookies (aktueller 6.7-Standard). Die
  Konto-Routen erfordern zusätzlich einen authentifizierten Kunden.
- Vertrauenswürdige Proxys: Das Anmelde-Rate-Limit nutzt die Client-IP
  (`Request::getClientIp()`). Hinter Reverse-Proxy oder Load-Balancer muss
  `TRUSTED_PROXIES` (samt vertrauenswürdiger Header) korrekt gesetzt sein - sonst
  erscheinen alle Requests als vom Proxy kommend (Überdrosselung) oder die
  Client-IP lässt sich über `X-Forwarded-For` fälschen (Unterdrosselung).
- Abmeldung ist zweistufig: Der tokenisierte Link in der Mail ist ein GET, der nur
  eine Bestätigungsseite anzeigt (kein Seiteneffekt); die Entfernung erfolgt erst
  über den POST der Bestätigung. Das verhindert, dass Mail-Prefetcher oder
  Link-Scanner versehentlich abmelden.
- Enumeration: Ist eine E-Mail für ein Produkt bereits angemeldet, liefert die
  Storefront eine eigene "bereits angemeldet"-Meldung. Das ist eine bewusste
  UX-Entscheidung; das Enumerations-Risiko wird durch die Rate-Limits pro IP und
  pro Kunde abgefedert, die jeden Versuch zählen. Falls das Bedrohungsmodell es
  erfordert, kann der Duplikat-Fall auf dieselbe generische Erfolgsmeldung wie
  eine Neuanmeldung umgestellt werden.
- Log-Export: Der CSV-Export des Plugin-Logs neutralisiert CSV-/Formel-Injection
  (führende `= + - @` werden escaped).

## Entwickler-Integration (Events)

Andere Plugins können diese Events abonnieren:

- `fk-back-in-stock-notification.registered`
- `fk-back-in-stock-notification.sent`
- `fk-back-in-stock-notification.deleted`
- `fk-back-in-stock-notification.expired`

```php
#[AsEventListener(event: 'fk-back-in-stock-notification.registered')]
public function onRegistered(BackInStockNotificationRegisteredEvent $event): void
{
    // eigener Code
}
```

## Deinstallation

Bei der Deinstallation entfernt das Plugin seine Tabellen und Mail-Templates -
außer es wird "Benutzerdaten behalten" gewählt. Das Entfernen von Zeilen aus
Core-Tabellen bricht die Deinstallation nie ab.

## Fehlerbehebung

- Komponente wird auf der Produktseite nicht angezeigt: Sie erscheint nur, wenn
  das Produkt wirklich ausverkauft ist (verfügbarer Bestand 0) UND
  Nachbestellungen deaktiviert sind (`isCloseout = true`).
- Es werden keine Mails versendet: Der Versand-Job läuft einmal täglich zur
  konfigurierten Uhrzeit; das Cron-Widget in der Administration zeigt den letzten
  Lauf und aufeinanderfolgende Fehler und bietet einen manuellen Versand. Der
  Mail-Versand nutzt die Shopware-Mail-Queue.
- Anmeldung landet auf einer JSON-Seite statt inline: Das Storefront-JS ist nicht
  im Theme aktiv. Auf dem Server `bin/console assets:install`,
  `bin/console theme:compile` und `bin/console cache:clear` ausführen (das Plugin
  liefert das kompilierte JS bereits mit).
- Admin-Änderungen nicht sichtbar: nach Änderungen am Administration-Modul
  `bin/build-administration.sh` ausführen und den Cache leeren.

## FAQ

**Ist das eine Newsletter- oder Marketing-Funktion?**
Nein. Wiederverfügbarkeits-Benachrichtigungen sind transaktionale,
produktspezifische Nachrichten und vollständig unabhängig vom Newsletter-System
von Shopware - es gibt keine Kopplung in beide Richtungen. Ein Kunde kann
Bestandsbenachrichtigungen abonnieren, ohne Newsletter-Empfänger zu sein, und
umgekehrt.

**Brauchen Kunden ein Double-Opt-In?**
Nein. Das Plugin nutzt Single-Opt-In: Das Absenden des Formulars speichert die
Anmeldung sofort und sendet eine Bestätigungsmail. Das ist rechtlich ausreichend,
da die Nachrichten transaktional sind (keine Werbung). Jede Mail enthält dennoch
einen Ein-Klick-Abmeldelink.

**Was zählt als "auf Lager"?**
Nur physischer Bestand (`stock > 0`). Die Anmelde-Komponente erscheint nur, wenn
das Produkt/die Variante wirklich ausverkauft ist (verfügbarer Bestand 0) UND
Nachbestellungen deaktiviert sind (`isCloseout = true`).

**Werden Produktvarianten unterstützt?**
Ja. Anmeldungen und Benachrichtigungen funktionieren auf Varianten-Ebene - jede
Variante hat ihren eigenen Bestandsstatus und löst unabhängig aus.

**Wann und wie werden Mails versendet?**
Einmal täglich zur konfigurierten Uhrzeit (Standard 18:00, Shop-Zeitzone) über die
Shopware-Mail-Queue. Werden mehrere beobachtete Artikel eines Kunden am selben Tag
verfügbar, erhält er eine einzige konsolidierte Mail mit allen Artikeln. Noch
nicht verfügbare Artikel bleiben für den nächsten Lauf offen.

**Können sich auch Gäste anmelden, nicht nur eingeloggte Kunden?**
Ja. Gäste melden sich mit ihrer E-Mail-Adresse an; eingeloggte Kunden bekommen das
Feld vorausgefüllt und können ihre Anmeldungen im Kundenkonto verwalten.

**Löscht die Deinstallation Kundendaten?**
Nur, wenn bei der Deinstallation nicht "Benutzerdaten behalten" gewählt wird. Beim
Behalten bleiben die Tabellen; andernfalls werden die Plugin-Tabellen entfernt.
Zeilen in Core-Tabellen sind nie ein Grund, die Deinstallation abzubrechen.
