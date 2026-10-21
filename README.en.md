# Nova Tag Manager for Shopware 6

🇩🇪 [Deutsche Version](README.md)

Lightweight Google Tag Manager integration for Shopware 6.7 with a **GA4 e-commerce data layer**, **Google Ads remarketing** and **Consent Mode v2** – configurable per sales channel.

## Features

- One GTM container per sales channel
- GA4 events: `view_item_list`, `search`, `view_item`, `select_item`, `add_to_wishlist`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`, `login`, `sign_up`
- Product lists tracked in the browser when visible: CMS product sliders, cross-selling tabs, custom lists (`data-nova-list-id` / `data-nova-list-name`, e.g. recently viewed, search window), filtered/reloaded listings – batched, each product once per list
- Cart quantity changes reported as `add_to_cart` / `remove_from_cart` with the difference
- Integration events: `nova:products-rendered`, `nova:search` ({ term, total }), `nova:search-open`, `nova:search-close` on `document`
- `google_tag_params` for Google Ads dynamic remarketing
- Consent Mode v2 connected to the Shopware cookie banner or an external consent tool
- Load strategies: immediately, after page load, or only after consent
- Optional server-side tagging URL
- Purchase is reported only once, even when the finish page is reloaded
- HTTP-cache safe: no personal data in cached pages

## Installation

```bash
cd <shopware-root>/custom/plugins
git clone https://github.com/DrdotHouse2106/SW-6-Google-Tag-Manager.git NovaTagManager
cd <shopware-root>
bin/console plugin:refresh
bin/console plugin:install --activate NovaTagManager
bin/console cache:clear
```

Configure it under *Extensions → My extensions → Nova Tag Manager → Configure* (choose the sales channel at the top).

## Compatibility

Shopware 6.7.3 – 6.7.x (checked against 6.7.15). License: MIT.
