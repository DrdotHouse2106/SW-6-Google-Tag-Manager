import ListTracker from './list-tracker';
import CartQuantity from './cart-quantity';

const { PluginBaseClass } = window;

const COOKIE_STATISTICS = 'nova-gtm-statistics';
const COOKIE_MARKETING = 'nova-gtm-marketing';
const COOKIE_EVENT = 'nova-gtm-event';
const SEARCH_DELAY = 1000;

/**
 * Erfasst Interaktionen im Browser und schreibt sie im GA4-Format in den Datenlayer:
 * view_item_list (Slider, Cross-Selling, Suchfenster, „Zuletzt angesehen“, nachgeladene Listings),
 * select_item, add_to_cart, remove_from_cart (auch Mengenänderungen), add_to_wishlist,
 * search, login, sign_up. Außerdem werden Änderungen im Shopware-Cookie-Dialog an den
 * Consent Mode übergeben.
 *
 * Die Seitendaten (view_item, view_item_list des Listings, purchase …) schreibt bereits das
 * Inline-Skript im <head> – siehe views/storefront/nova-tag-manager/head.js.twig.
 */
export default class NovaTagManagerPlugin extends PluginBaseClass {
    init() {
        this._gtm = window.novaGtm;
        if (!this._gtm) {
            return;
        }

        this._cfg = this._gtm.config;

        this._trackCustomerEvent();
        this._listenToConsent();

        if (this._cfg.clicks) {
            this._lists = 'IntersectionObserver' in window ? new ListTracker(this._gtm) : null;
            this._quantity = new CartQuantity(this._gtm);
            this._listenToSearch();

            document.addEventListener('click', this._onClick.bind(this), true);
            document.addEventListener('submit', this._onSubmit.bind(this), true);
        }
    }

    /* ---------- Consent ---------- */

    _listenToConsent() {
        if (this._cfg.consent !== 'shopware' || !document.$emitter) {
            return;
        }

        document.$emitter.subscribe('CookieConfiguration_Update', (event) => {
            const changes = event.detail || {};
            const update = {};

            if (COOKIE_STATISTICS in changes) {
                this._gtm.consent.statistics = !!changes[COOKIE_STATISTICS];
                update.analytics_storage = this._gtm.consent.statistics ? 'granted' : 'denied';
            }

            if (COOKIE_MARKETING in changes) {
                this._gtm.consent.marketing = !!changes[COOKIE_MARKETING];
                const value = this._gtm.consent.marketing ? 'granted' : 'denied';
                update.ad_storage = value;
                update.ad_user_data = value;
                update.ad_personalization = value;
            }

            if (Object.keys(update).length === 0) {
                return;
            }

            window.gtag('consent', 'update', update);
            window.dataLayer.push({ event: 'consent_update' });

            if (this._cfg.load === 'consent' && (this._gtm.consent.statistics || this._gtm.consent.marketing)) {
                this._gtm.load();
            }
        });
    }

    /* ---------- Login / Registrierung (vom Server per Cookie gemeldet) ---------- */

    _trackCustomerEvent() {
        const match = document.cookie.match(new RegExp(`(?:^|; )${COOKIE_EVENT}=([^;]*)`));
        if (!match) {
            return;
        }

        document.cookie = `${COOKIE_EVENT}=; Max-Age=0; path=/; SameSite=Lax`;

        if (match[1] === 'login' || match[1] === 'sign_up') {
            this._gtm.push({ event: match[1], method: 'Shopware' });
        }
    }

    /* ---------- Suchfenster ---------- */

    /**
     * Das Suchfenster meldet jede Ergebnis-Anzeige per `nova:search` ({ term, total }).
     * Gemeldet wird erst nach 1 s Ruhe (keine Teilbegriffe beim Tippen) und jeder Begriff
     * nur einmal je Fensteröffnung (`nova:search-open` / `nova:search-close` setzen zurück).
     */
    _listenToSearch() {
        this._searchedTerms = new Set();
        this._pendingSearch = null;

        document.addEventListener('nova:search', (event) => {
            const term = String(event.detail?.term || '').trim();
            if (!term) {
                return;
            }

            window.clearTimeout(this._pendingSearch?.timer);
            this._pendingSearch = {
                term,
                total: Number(event.detail?.total) || 0,
                timer: window.setTimeout(() => this._flushSearch(), SEARCH_DELAY),
            };
        });

        const reset = () => {
            this._flushSearch();
            this._searchedTerms.clear();
        };
        document.addEventListener('nova:search-open', reset);
        document.addEventListener('nova:search-close', () => this._flushSearch());
        window.addEventListener('pagehide', () => this._flushSearch());
    }

    _flushSearch() {
        const pending = this._pendingSearch;
        this._pendingSearch = null;
        if (!pending) {
            return;
        }

        window.clearTimeout(pending.timer);
        const key = pending.term.toLowerCase();
        if (this._searchedTerms.has(key)) {
            return;
        }

        this._searchedTerms.add(key);
        this._gtm.push({ event: 'search', search_term: pending.term, search_results: pending.total });
    }

    /* ---------- Klicks: Produkt & Merkzettel ---------- */

    _onClick(event) {
        const wishlist = event.target.closest('[data-add-to-wishlist]');
        if (wishlist) {
            if (wishlist.classList.contains('product-wishlist-not-added')) {
                const item = this._listItem(wishlist) || this._pageItem();
                if (item) {
                    this._ecommerce('add_to_wishlist', { value: item.price, items: [item] });
                }
            }
            return;
        }

        const link = event.target.closest('a[href]');
        if (!link || link.closest('form')) {
            return;
        }

        const product = link.closest('[data-product-information]') || link.closest('.product-box, .nova-search-card-item')?.querySelector('[data-product-information]');
        if (!product) {
            return;
        }

        const item = this._listItem(product);
        if (item) {
            // Eine noch ausstehende Suche zuerst melden, damit die Reihenfolge stimmt
            this._flushSearch?.();
            this._ecommerce('select_item', {
                item_list_id: item.item_list_id,
                item_list_name: item.item_list_name,
                items: [item],
            });
        }
    }

    /* ---------- Formulare: In den Warenkorb / Entfernen ---------- */

    _onSubmit(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const removed = form.querySelector('[data-nova-gtm-role="remove"]');
        if (removed) {
            const item = this._parse(removed.getAttribute('data-nova-gtm-item'));
            if (item) {
                this._ecommerce('remove_from_cart', {
                    value: this._round(item.price * item.quantity),
                    items: [item],
                });
            }
            return;
        }

        if (!form.matches('[data-add-to-cart]')) {
            return;
        }

        const items = [];
        form.querySelectorAll('input[name^="lineItems["][name$="][id]"]').forEach((input) => {
            const id = input.value;
            const quantityField = form.querySelector(`[name="lineItems[${id}][quantity]"]`);
            const quantity = Math.max(1, parseInt(quantityField ? quantityField.value : '1', 10) || 1);
            // In einer Liste (Slider, Listing, Suchfenster …) mit Listenbezug, sonst Artikel der Produktseite
            const item = this._listItem(form) || this._cfg.items[id] || this._pageItem();

            if (item) {
                items.push({ ...item, quantity });
            }
        });

        if (items.length) {
            const value = items.reduce((sum, item) => sum + (item.price || 0) * item.quantity, 0);
            this._ecommerce('add_to_cart', { value: this._round(value), items });
        }
    }

    /* ---------- Hilfsfunktionen ---------- */

    _ecommerce(event, data) {
        this._gtm.push({ event, ecommerce: { currency: this._cfg.currency, ...data } });
    }

    /** Artikel aus der umgebenden Produktbox/-karte inkl. Listenbezug */
    _listItem(element) {
        const product = element.closest('[data-product-information]')
            || element.closest('.product-box, .nova-search-card-item')?.querySelector('[data-product-information]');
        if (!product) {
            return null;
        }

        if (this._lists) {
            return this._lists.itemFor(product)?.item || null;
        }

        const info = this._parse(product.getAttribute('data-product-information'));
        if (!info) {
            return null;
        }

        return this._cfg.items[info.id] || {
            item_id: String(info.sku || info.id),
            item_name: info.name,
            item_brand: info.brand || '',
            price: this._round(info.price),
        };
    }

    /** Artikel der Produktdetailseite (dort gibt es genau einen) */
    _pageItem() {
        const items = Object.values(this._cfg.items || {});
        return items.length === 1 ? items[0] : null;
    }

    _parse(json) {
        try {
            return JSON.parse(json);
        } catch (e) {
            return null;
        }
    }

    _round(value) {
        return Math.round((Number(value) || 0) * 100) / 100;
    }
}
