# Geplante Funktionen

Ideen, die vorgemerkt, aber noch nicht umgesetzt sind. Vorschläge gern als Issue.

## Serverseitiges Nachmelden von Käufen (GA4 Measurement Protocol)

**Status:** zurückgestellt

**Problem:** Der Kauf wird vom Browser auf der Bestellabschlussseite gemeldet. Kehrt der Kunde
nach der Zahlung (PayPal, Klarna …) nicht in den Shop zurück, ist ein Werbeblocker aktiv oder
lädt die Abschlussseite nicht, fehlt der Kauf in GA4 – erfahrungsgemäß 5–15 % der Bestellungen.

**Lösung (Entwurf):**

1. Beim Bestellen (`CheckoutOrderPlacedEvent`) die GA4-Client-ID aus dem `_ga`-Cookie und die
   Session-ID aus `_ga_<Stream>` an der Bestellung speichern – **nur mit Statistik-Einwilligung**
   (`nova-gtm-statistics`). Ohne Einwilligung wird nichts nachgemeldet.
2. Beim Aufruf der Abschlussseite die Bestellung als „vom Browser gemeldet“ markieren.
3. Geplante Aufgabe (z. B. alle 5 Minuten): Bestellungen, die älter als die Wartezeit sind, den
   gewählten Status erreicht haben und noch nicht gemeldet wurden, per Measurement Protocol
   (`POST https://www.google-analytics.com/mp/collect?measurement_id=…&api_secret=…`) als
   `purchase` mit `transaction_id`, Wert, Steuer, Versand, Währung und Artikeln senden –
   genau einmal je Bestellung (Status in eigener Tabelle).
4. Konfiguration pro Verkaufskanal: Mess-ID (`G-…`), API-Geheimnis (Passwortfeld),
   „nachmelden, sobald bestellt / bezahlt“, Wartezeit in Minuten, Diagnose-Protokoll.

**Grenzen:** Nur GA4. Google-Ads-Conversions lassen sich so nicht nachmelden – Ads profitiert
nur, wenn die GA4-Käufe in Google Ads importiert werden.

**Voraussetzung im Shop:** In GA4 *Verwaltung → Datenstreams → Stream → Measurement Protocol
API-Secrets* ein Geheimnis anlegen; geplante Aufgaben müssen laufen (Cron).

**Vorher prüfen, ob es sich lohnt:** Anzahl der Bestellungen in Shopware mit den Käufen in GA4
(*Berichte → Monetarisierung → E-Commerce-Käufe*) für 30 Tage vergleichen.
