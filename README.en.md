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

## GTM container setup

1. Turn off Shopware's built-in Google Analytics (*Sales channels → Analytics*) – otherwise purchases and page views are counted twice.
2. Import `vorlagen/gtm-ga4-shop-ereignisse.json` (*Admin → Import container*, option **Merge → Rename conflicting**; the preview must show "0 deleted").
3. Set the variable "Nova – GA4 Mess-ID" to the shop's GA4 measurement ID.
4. In the Google Ads purchase conversion tag set value `ecommerce.value`, transaction ID `ecommerce.transaction_id`, currency `{{Nova – ecommerce.currency}}`.
5. Test in preview mode (`purchase` fires each tag exactly once), then submit/publish. In Google Ads keep only the purchase as primary conversion.

## Troubleshooting

Known pitfalls from real installations. **Please add new ones** (or open an issue).

- **No events in GTM preview:** set the container ID in the right sales channel.
- **Double counting:** run only one tracking per sales channel (old GTM plugin or Shopware's own Analytics off).
- **Visitors asked for consent again:** new consent cookies – one-time effect.
- **No data from some visitors:** ad blockers – consider server-side tagging.
- **Second container from another shop on the same page:** remove the container ID under "All sales channels" when every shop has its own container, then clear the cache.
- **Page views go to another shop's GA4 after adding the Google Ads Google tag:** the `AW-…` tag is combined with another property in Google Ads – don't load it in that container, or use a separate Ads account per shop.
- **Debug:** enable "Debug" to log every data layer push to the browser console.

## Planned

Planned features (e.g. server-side purchase reporting to GA4 via Measurement Protocol) are listed in [ROADMAP.md](ROADMAP.md) (German).
