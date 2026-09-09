# Changelog

Alle nennenswerten Änderungen an diesem Plugin werden in dieser Datei dokumentiert.

Das Format orientiert sich an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/),
und das Projekt folgt [Semantic Versioning](https://semver.org/lang/de/).

## [Unveröffentlicht]

## [1.0.1] - 2026-09-09

### Geändert
- Lizenz von GPL-2.0-only auf MIT gewechselt.
- Technischen Namen und internen Prefix durchgängig auf `Fk`/`fk` vereinheitlicht: Plugin-Ordner `FkBackInStockNotification`, PHP-Namespace `Fkuenzel\FkBackInStockNotification`.
- Store-Metadaten in `composer.json` ergänzt (`de-DE` für `manufacturerLink`/`supportLink`).

### Entfernt
- Verwaiste alte Mail-Template-Ordner (`back_in_stock_notification_*`) und leeres Storefront-`dist`-Verzeichnis aus der Distribution.
- Source-Maps aus dem Distributions-Archiv.

## [1.0.0] - 2026-09-03

### Hinzugefügt
- Erstveröffentlichung.
- Storefront-Anmeldung auf der Produktdetailseite für Gäste und eingeloggte Kunden, inklusive Bestätigungsmail (Single-Opt-In).
- Automatische Benachrichtigung bei Wiederverfügbarkeit über die Shopware-Mail-Queue; mehrere am selben Tag verfügbare Artikel eines Kunden werden zu einer konsolidierten Mail zusammengefasst.
- Varianten-Support: Anmeldung und Benachrichtigung auf Varianten-Ebene mit eigenem Bestandsstatus.
- Kundenkonto-Bereich zur Verwaltung und Abmeldung eigener Benachrichtigungen; zweistufige Abmeldung über tokenisierten Link.
- Administration: Übersicht mit Filter und Pagination (25/50/100), Statistik, Cron-Überwachung mit manuellem Versand sowie Log-Viewer mit Live-Tail und CSV-/TXT-Export.
- Anmelde-Widget nur bei echtem Ausverkauf (Bestand 0 und deaktivierte Nachbestellung).
- Rate-Limiting pro IP und pro Kunde, konfigurierbar.
- Vier Events für Erweiterungen: registered, sent, deleted, expired.
- Konfigurierbare Versand-Uhrzeit (Standard 18:00, Shop-Zeitzone), Gültigkeitsdauer, Token-Ablauf und Log-Level.
- Deinstallation mit Option „Benutzerdaten behalten".
- Mehrsprachige Mail-Templates und Snippets (de-DE, en-GB).

### Technisch
- Vorkompilierte Storefront- und Administration-Assets werden mitgeliefert; auf dem Zielserver ist kein Node.js-Build erforderlich.
- Geprüft: PHPStan Level 8 (0 Fehler), Unit-Tests grün (PHP 8.2/8.3/8.4).
- Kompatibilität: Shopware 6.7.x, PHP 8.2+. Abnahme auf 6.7.12.1, Live-Betrieb auf 6.7.13.1.

[Unveröffentlicht]: https://github.com/fkuenzel/FkBackInStockNotification/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/fkuenzel/FkBackInStockNotification/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/fkuenzel/FkBackInStockNotification/releases/tag/v1.0.0
