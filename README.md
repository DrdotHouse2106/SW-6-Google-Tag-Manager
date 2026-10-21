# Nova Tag Manager für Shopware 6

🇬🇧 [English version](README.en.md)

Schlanke Google-Tag-Manager-Integration für Shopware 6.7 mit **GA4-E-Commerce-Datenlayer**, **Google-Ads-Remarketing** und **Consent Mode v2** – einstellbar pro Verkaufskanal.

## Funktionen

- Google Tag Manager pro Verkaufskanal (eigene Container-ID je Shop)
- Vollständiger GA4-E-Commerce-Datenlayer (siehe Tabelle unten)
- `google_tag_params` für dynamisches Remarketing in Google Ads
- **Consent Mode v2** mit Anbindung an den Shopware-Cookie-Banner (Einträge „Statistik“ und „Marketing“) oder an ein externes Consent-Tool
- Drei Ladestrategien: sofort, nach dem Seitenaufbau (schneller) oder erst nach Zustimmung
- Optional serverseitiges Tagging über eigene Domain
- Kauf wird auch beim Neuladen der Abschlussseite nur **einmal** gemeldet
- Kompatibel mit HTTP-Cache: keine personenbezogenen Daten in gecachten Seiten
- Klein: ca. 1 KB Inline-Skript + 3,4 KB nachgeladenes JavaScript

## Datenlayer

| Seite / Aktion | Event | Inhalt |
|---|---|---|
| Jede Seite | `user` | `id` (Kunden-ID nur im Checkout/Konto, sonst `null`) |
| Startseite, Kategorie | `view_item_list` | Produkte des Listings, `item_list_id` = `category_<ID>`, `item_list_name` = `Category: <Name>` |
| Suche | `search`, `view_item_list` | `search_term`, Treffer mit `item_list_id` = `search` |
| Produktseite | `view_item` | Produkt inkl. Hersteller, Kategorie, Variante |
| Klick auf Produkt | `select_item` | Produkt mit Listenzuordnung |
| Merkzettel | `add_to_wishlist` | Produkt |
| In den Warenkorb | `add_to_cart` | Produkt(e) mit Menge und Wert |
| Aus dem Warenkorb | `remove_from_cart` | Position mit Menge und Wert |
| Warenkorb | `view_cart` | Positionen, Gesamtwert |
| Bestellung prüfen | `begin_checkout`, `add_shipping_info`, `add_payment_info` | Positionen, Versand- und Zahlungsart |
| Bestellabschluss | `purchase` | `transaction_id`, `value`, `tax`, `shipping`, `coupon`, `affiliation`, Positionen |
| Login / Registrierung | `login`, `sign_up` | `method` |
| Consent-Änderung | `consent_update` | – |

Zusätzlich auf jeder Seite: `google_tag_params` mit `ecomm_pagetype`, `ecomm_prodid`, `ecomm_category`, `ecomm_totalvalue`.

Vor jedem E-Commerce-Event wird – wie von Google empfohlen – `{ ecommerce: null }` gesendet. Als Kategorie wird die **SEO-Hauptkategorie** des Produkts verwendet (im Listing ersatzweise die aktuelle Kategorie).

## Installation

```bash
cd <shopware-root>/custom/plugins
git clone https://github.com/DrdotHouse2106/SW-6-Google-Tag-Manager.git NovaTagManager

cd <shopware-root>
bin/console plugin:refresh
bin/console plugin:install --activate NovaTagManager
bin/console cache:clear
```

## Einrichtung

**Erweiterungen → Meine Erweiterungen → Nova Tag Manager → Konfigurieren**, oben den Verkaufskanal wählen:

| Einstellung | Bedeutung |
|---|---|
| Container-ID | `GTM-XXXXXXX` – leer lassen = in diesem Shop deaktiviert |
| Container laden | **Sofort** (genaueste Daten), **nach dem Seitenaufbau** (bessere Ladezeit) oder **erst nach Zustimmung** |
| URL für serverseitiges Tagging | Optional, z. B. `https://sst.meinshop.de` |
| Einwilligung verwalten über | **Shopware-Cookie-Banner** oder **externes Consent-Tool** |
| Google-Ads-Remarketing | `google_tag_params` senden |
| Klicks & Warenkorb erfassen | `select_item`, `add_to_cart`, `remove_from_cart`, `add_to_wishlist` |
| Umsatz netto | Standard ist brutto inkl. Versand |
| Debug | Gibt alle Datenlayer-Einträge in der Browser-Konsole aus |

## Umstieg von einer anderen Tag-Manager-Erweiterung

Der Datenlayer folgt dem GA4-Standard. Bestehende GTM-Container, die auf die GA4-Events und `google_tag_params` reagieren, funktionieren in der Regel ohne Änderung weiter.

1. Plugin installieren, Container-ID **zunächst nur in einem Verkaufskanal** eintragen
2. Alte Erweiterung in diesem Verkaufskanal deaktivieren (sonst wird doppelt gezählt)
3. Im GTM **Vorschau-Modus** einen Testkauf durchspielen und alle Events prüfen
4. Danach die übrigen Verkaufskanäle umstellen und die alte Erweiterung deinstallieren

> **Hinweis zu Doppelzählungen:** Ist unter *Verkaufskanal → Analytics* zusätzlich Shopwares eigenes Google Analytics aktiv **und** enthält der GTM-Container einen GA4-Tag, werden Seitenaufrufe doppelt erfasst. Dann eines von beiden deaktivieren.

> **Hinweis zum Cookie-Banner:** Die Einwilligungen werden in eigenen Cookies (`nova-gtm-statistics`, `nova-gtm-marketing`) gespeichert. Besucher, die bereits zugestimmt hatten, werden von Shopware erneut gefragt, sobald sich die Cookie-Einträge ändern.

## Entwicklung

Innerhalb von Shopware: `bin/build-storefront.sh`. Ohne Shopware (nur das Storefront-JavaScript):

```bash
cd src/Resources/app/storefront
npm install
npm run build
```

## Kompatibilität

Shopware 6.7.3 bis 6.7.x (geprüft gegen 6.7.15).

## Lizenz

MIT
