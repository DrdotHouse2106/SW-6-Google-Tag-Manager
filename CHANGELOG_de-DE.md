# 1.1.1
- Neues Plugin-Icon im Nova-Stil (Erweiterungen → Meine Erweiterungen)
- Repository umbenannt in SW6-Nova-Tag-Manager – alte Adresse leitet weiter

# 1.1.0
- Neu: Kundendaten für erweiterte Conversions (Google Ads) – beim Kauf `ecommerce.user_email` und `user_data` (E-Mail, Telefon im Format +49…, Rechnungsadresse), nur mit Marketing-Einwilligung; abschaltbar, Standard aus
- GTM-Vorlagen im Ordner `vorlagen/` mit Schritt-für-Schritt-Anleitung in der README

# 1.0.1
- Cookie-Dialog: Fehlt die Gruppe „Statistik“ oder „Marketing“ (z. B. weil andere Plugins noch den alten Cookie-Provider nutzen), wird sie angelegt – sonst konnte die Einwilligung nicht erteilt werden und Google erhielt dauerhaft „abgelehnt“

# 1.0.0
- Erste Version: Google Tag Manager pro Verkaufskanal mit GA4-E-Commerce-Datenlayer
- Consent Mode v2 über den Shopware-Cookie-Banner oder ein externes Consent-Tool
- Produktlisten, Slider, Cross-Selling, „Zuletzt angesehen“ und Suchfenster werden erfasst
- Klicks, Warenkorb (inkl. Mengenänderungen), Checkout, Kauf (nur einmal pro Bestellung), Login, Registrierung, Suche
- Google-Ads-Remarketing (google_tag_params), optional serverseitiges Tagging
