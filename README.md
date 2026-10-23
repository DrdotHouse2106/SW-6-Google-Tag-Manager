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
- Klein: ca. 1 KB Inline-Skript + 9,4 KB nachgeladenes JavaScript

## Datenlayer

| Seite / Aktion | Event | Inhalt |
|---|---|---|
| Jede Seite | `user` | `id` (Kunden-ID nur im Checkout/Konto, sonst `null`) |
| Startseite, Kategorie | `view_item_list` | Produkte des Listings, `item_list_id` = `category_<ID>`, `item_list_name` = `Category: <Name>` |
| Listing gefiltert / nachgeladen | `view_item_list` | Nur neu angezeigte Produkte, gleiche Liste wie oben |
| Produkt-Slider (Erlebniswelten) | `view_item_list` | `product_slider_<n>`, `Slider: <Titel>` – nur tatsächlich sichtbare Slides |
| Cross-Selling (Produktseite) | `view_item_list` | `cross_selling_<ID>`, `Cross-Selling: <Tab-Titel>` – je Tab, sobald er sichtbar ist |
| Eigene Listen, z. B. „Zuletzt angesehen“, Suchfenster | `view_item_list` | `item_list_id` / `item_list_name` aus `data-nova-list-id` / `data-nova-list-name` |
| Suche (Ergebnisseite) | `search`, `view_item_list` | `search_term`, Treffer mit `item_list_id` = `search` |
| Suchfenster | `search` | `search_term`, `search_results` (Trefferzahl) – erst nach 1 s Tipp-Pause, je Begriff einmal pro Fensteröffnung |
| Produktseite | `view_item` | Produkt inkl. Hersteller, Kategorie, Variante |
| Klick auf Produkt (jede Liste) | `select_item` | Produkt mit Liste und Position (`index`) |
| Merkzettel | `add_to_wishlist` | Produkt (mit Liste, falls aus einer Liste) |
| In den Warenkorb | `add_to_cart` | Produkt(e) mit Menge, Wert und – aus Listen – Listenbezug |
| Menge im Warenkorb erhöht / verringert | `add_to_cart` / `remove_from_cart` | Differenzmenge; schnelle Klicks auf +/− werden zusammengefasst |
| Aus dem Warenkorb | `remove_from_cart` | Position mit Menge und Wert |
| Warenkorb | `view_cart` | Positionen, Gesamtwert |
| Bestellung prüfen | `begin_checkout`, `add_shipping_info`, `add_payment_info` | Positionen, Versand- und Zahlungsart |
| Bestellabschluss | `purchase` | `transaction_id`, `value`, `tax`, `shipping`, `coupon`, `affiliation`, Positionen |
| Login / Registrierung | `login`, `sign_up` | `method` |
| Consent-Änderung | `consent_update` | – |

**So werden Listen erfasst:** Jedes Element mit `data-product-information` (Shopware-Produktboxen, Karten des Suchfensters, „Zuletzt angesehen“ …) wird gemeldet, sobald es zu mindestens 50 % sichtbar ist – gebündelt (spätestens nach 1 s bzw. bei 10 Produkten) und pro Liste und Seitenaufruf nur einmal. Auch nachträglich eingefügte Inhalte werden erkannt. Doppelte Slides des Endlos-Sliders werden ignoriert.

Zusätzlich auf jeder Seite: `google_tag_params` mit `ecomm_pagetype`, `ecomm_prodid`, `ecomm_category`, `ecomm_totalvalue`.

Vor jedem E-Commerce-Event wird – wie von Google empfohlen – `{ ecommerce: null }` gesendet. Als Kategorie wird die **SEO-Hauptkategorie** des Produkts verwendet (im Listing ersatzweise die aktuelle Kategorie).

## Schnittstelle für Theme und Suche

Andere Erweiterungen (z. B. Nova Theme, Nova Suche) können eigene Produktlisten und Suchen melden, ohne den Tag Manager zu kennen:

| Was | Wie |
|---|---|
| Eigene Produktliste | Container mit `data-nova-list-id="recently_viewed"` und `data-nova-list-name="Zuletzt angesehen"`; jedes Produkt mit `data-product-information='{"id":"…","name":"…","brand":"…","price":9.9,"sku":"…"}'` |
| Inhalte nachgeladen | optional `document.dispatchEvent(new CustomEvent('nova:products-rendered', { detail: { container } }))` – wird zusätzlich automatisch erkannt |
| Suche im Suchfenster | `document.dispatchEvent(new CustomEvent('nova:search', { detail: { term, total } }))` nach jeder Ergebnis-Anzeige |
| Suchfenster geöffnet / geschlossen | `nova:search-open` / `nova:search-close` auf `document` (setzt die Doppelmeldungs-Sperre zurück bzw. sendet eine ausstehende Suche sofort) |

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
| Klicks & Warenkorb erfassen | Produktlisten (Slider, Cross-Selling, eigene Listen), `select_item`, `add_to_cart`, `remove_from_cart` (auch Mengenänderungen), `add_to_wishlist`, `search` im Suchfenster |
| Umsatz netto | Standard ist brutto inkl. Versand |
| Debug | Gibt alle Datenlayer-Einträge in der Browser-Konsole aus |

## GTM-Vorlage: GA4-Ereignisse für alle Shops

Der Nova Tag Manager schreibt die Shop-Ereignisse in den Datenlayer – GA4 bekommt sie aber erst, wenn im GTM-Container ein GA4-Ereignis-Tag darauf reagiert. Dafür liegt eine Vorlage bei: [`vorlagen/gtm-ga4-shop-ereignisse.json`](vorlagen/gtm-ga4-shop-ereignisse.json) (Datei herunterladen: auf GitHub öffnen → „Download raw file“).

Für **jeden** Container (jeder Shop hat seinen eigenen):

1. Google Tag Manager → Container öffnen → **Verwaltung → Container importieren**
2. Datei auswählen, Arbeitsbereich **„Vorhanden“** (Standard-Arbeitsbereich), Importoption **„Zusammenführen“ → „In Konflikt stehende Tags, Trigger und Variablen umbenennen“** → Bestätigen
3. **Variablen → „Nova – GA4 Mess-ID“** öffnen und `G-XXXXXXXXXX` durch die Mess-ID dieses Shops ersetzen (steht im Google-Tag des Containers bzw. in GA4 unter *Verwaltung → Datenstreams*)
4. Vorhandenen **Google-Ads-Conversion-Tag für den Kauf** öffnen und ergänzen: Conversion-Wert `{{ecommerce.value}}`, Transaktions-ID `{{ecommerce.transaction_id}}`, Währungscode `{{Nova – ecommerce.currency}}` (Variablen ggf. als Datenschicht-Variable anlegen, falls im Container nicht vorhanden)
5. **In Vorschau ansehen** → Testbestellung: bei `purchase` je genau einmal „Nova – GA4 Shop-Ereignisse“ und der Kauf-Conversion-Tag
6. **Senden** → Version benennen → Veröffentlichen

Die Vorlage enthält: Trigger „Nova – GA4 Shop-Ereignisse“ (view_item, view_item_list, select_item, add_to_cart, remove_from_cart, add_to_wishlist, view_cart, begin_checkout, add_shipping_info, add_payment_info, purchase, search), Tag „Nova – GA4 Shop-Ereignisse“ (GA4-Ereignis, Ereignisname = `{{Event}}`, E-Commerce-Daten aus der Datenschicht, Parameter `search_term`) und die Variablen „Nova – GA4 Mess-ID“, „Nova – ecommerce.currency“, „Nova – search_term“.

**Wichtig:** Shopwares eingebautes Google Analytics (*Verkaufskanäle → Analyse*) muss in diesen Shops **aus** sein – sonst werden Käufe und Seitenaufrufe doppelt gezählt (siehe „Häufige Probleme“).

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

## Häufige Probleme und Lösungen

Hier sammeln wir Hürden, die bei Installation und Betrieb tatsächlich aufgetreten sind. **Neue Probleme bitte ergänzen** (oder als Issue melden), damit andere nicht lange suchen müssen.

### Im GTM-Vorschaumodus kommen keine Events an

**Ursache:** Container-ID fehlt im **richtigen Verkaufskanal**

**Lösung:** *Erweiterungen → Nova Tag Manager → Konfigurieren* → oben den Verkaufskanal wählen → Container-ID eintragen

### Container lädt erst nach dem Cookie-Banner

**Ursache:** Ladestrategie „Erst nach Zustimmung“ oder Consent noch nicht erteilt

**Lösung:** Gewollt. Für den Test im Cookie-Banner „Alle akzeptieren“ wählen. Ladestrategie in der Konfiguration prüfen

### Seitenaufrufe/Käufe werden **doppelt** gezählt

**Ursache:** Altes GTM-Plugin und Nova Tag Manager laufen gleichzeitig, oder Shopwares eigenes Google Analytics (*Verkaufskanal → Analytics*) ist zusätzlich aktiv, während der Container einen GA4-Tag hat

**Lösung:** Pro Verkaufskanal nur **ein** Tracking: altes Plugin deaktivieren bzw. Shopware-Analytics abschalten

### Google meldet trotz „Alle akzeptieren“ keine Einwilligung (`gcs=G100`, `npa=1`)

**Ursache:** Die Cookies `nova-gtm-statistics`/`nova-gtm-marketing` fehlen. Bis 1.0.0 konnte das passieren, wenn im Cookie-Dialog die Gruppe „Statistik“ oder „Marketing“ fehlte (z. B. weil andere Plugins noch den alten Cookie-Provider nutzen)

**Lösung:** Auf 1.0.1 aktualisieren, Cache löschen, im Browser die Cookies der Seite löschen und erneut „Alle akzeptieren“. Prüfen: F12 → Anwendung → Cookies → beide Cookies mit Wert `1`; im Tag Assistant steht dann `gcs=G111`

### Besucher werden erneut nach Cookies gefragt

**Ursache:** Die Einwilligung wird in neuen Cookies (`nova-gtm-statistics`, `nova-gtm-marketing`) gespeichert

**Lösung:** Einmaliger, gewollter Effekt nach der Umstellung

### Bei manchen Besuchern kommen gar keine Daten an

**Ursache:** Werbeblocker blockieren `googletagmanager.com`

**Lösung:** Optional serverseitiges Tagging über eine eigene Domain (Einstellung „URL für serverseitiges Tagging“)

### `purchase` fehlt beim Neuladen der Abschlussseite

**Ursache:** Kauf wird absichtlich **nur einmal** pro Bestellung gemeldet

**Lösung:** Gewollt (verhindert doppelte Umsätze)

### Kunden-ID ist auf normalen Seiten `null`

**Ursache:** Absichtlich nur im Checkout/Kundenkonto – andere Seiten liegen im HTTP-Cache

**Lösung:** Gewollt (Datenschutz)

### Mengenänderungen im Offcanvas-Warenkorb fehlen

**Ursache:** Hängt davon ab, dass das Theme das Shopware-Standard-Mengenfeld nutzt

**Lösung:** In der Browser-Konsole mit der Einstellung „Debug“ prüfen; bei eigenem Theme-Markup Rückmeldung als Issue

### Ich will sehen, was gesendet wird

**Ursache:** –

**Lösung:** Einstellung **„Debug“** aktivieren: alle Datenlayer-Einträge erscheinen in der Browser-Konsole (F12)

### Käufe werden in Google Ads doppelt oder mehrfach gezählt

**Ursache:** Neben dem GTM-Container sendet Shopwares eingebautes Google Analytics (*Verkaufskanäle → Shop → Analyse*, „Google Analytics aktivieren“ / „Bestellungen verfolgen“). Dessen `gtag("event", "purchase")` landet im selben Datenlayer, und GTM-Trigger auf `purchase` springen zweimal an – einmal für den Nova-Eintrag, einmal für Shopwares. In Tag Assistant zu erkennen an zwei „purchase“-Einträgen, einer davon mit `cookie_prefix: "_swag_ga"`

**Lösung:** In allen Shops mit Nova Tag Manager Shopwares Google Analytics ausschalten und GA4 über den Container senden (Vorlage siehe „GTM-Vorlage“). Im Kauf-Conversion-Tag die Transaktions-ID setzen – dann verwirft Google Ads doppelte Meldungen derselben Bestellung selbst

### GA4 zeigt keine Umsätze, Google Ads aber schon

**Ursache:** Im GTM-Container gibt es nur einen Google-Tag (Seitenaufrufe) und Google-Ads-Tags, aber keinen GA4-Ereignis-Tag für die Shop-Ereignisse

**Lösung:** Vorlage `vorlagen/gtm-ga4-shop-ereignisse.json` importieren und die Mess-ID eintragen

## Geplante Funktionen

Vorgemerkte Erweiterungen (z. B. serverseitiges Nachmelden von Käufen an GA4) stehen in der [ROADMAP.md](ROADMAP.md).

## Lizenz

MIT
