/**
 * Erfasst Produktlisten im Browser und meldet sichtbare Produkte als `view_item_list`.
 *
 * Erfasst werden alle Elemente mit `data-product-information` (Shopware-Produktboxen,
 * Karten des Suchfensters, „Zuletzt angesehen“ …), sobald sie zu mindestens 50 % sichtbar sind.
 * Gemeldet wird gebündelt (spätestens nach 1 s oder bei 10 Produkten je Liste) und jedes
 * Produkt nur einmal pro Liste und Seitenaufruf.
 *
 * Zuordnung zur Liste (erste passende Regel gewinnt):
 *  1. Vorfahre mit `data-nova-list-id` / `data-nova-list-name`
 *  2. Shopware-Cross-Selling (Tab auf der Produktseite) → cross_selling_<ID>, „Cross-Selling: <Titel>“
 *  3. CMS-Produkt-Slider → product_slider_<n>, „Slider: <Titel>“
 *  4. Produkt-Listing (Kategorie, Suche) → Liste der Seite. Die beim Laden vorhandenen Produkte
 *     meldet bereits der Server; hier werden nur nachgeladene/gefilterte Produkte gemeldet.
 *  5. Alles andere → product_list, „Produktliste“
 */
const PRODUCT_SELECTOR = '[data-product-information]';
const LISTING_SELECTOR = '.cms-element-product-listing, .js-listing-wrapper';
const FLUSH_DELAY = 1000;
const MAX_ITEMS = 10;

export default class ListTracker {
    constructor(gtm) {
        this._gtm = gtm;
        this._cfg = gtm.config;
        this._seen = new Set();          // „listId|productId“ – bereits gemeldet
        this._queues = new Map();        // listId → { list, items: [] }
        this._timer = null;
        this._sliderCount = 0;
        this._serverRendered = new WeakSet();

        this._observer = new IntersectionObserver((entries) => this._onIntersect(entries), { threshold: 0.5 });

        // Listing-Produkte, die der Server beim Seitenaufbau schon gemeldet hat, nicht doppelt melden
        document.querySelectorAll(`${LISTING_SELECTOR} ${PRODUCT_SELECTOR}`).forEach((element) => {
            const info = this.info(element);
            if (info && this._cfg.items[info.id]) {
                this._serverRendered.add(element);
                // Auch nach Filtern/Nachladen im Listing nicht erneut melden
                this._seen.add(`${this.resolveList(element).item_list_id}|${info.id}`);
            }
        });

        this.scan(document);

        // Nachgeladene Inhalte: Absprache-Event und als Rückfall ein MutationObserver
        document.addEventListener('nova:products-rendered', (event) => this.scan(event.detail?.container || document));
        this._mutations = new MutationObserver((records) => {
            records.forEach((record) => record.addedNodes.forEach((node) => {
                if (node.nodeType === 1) {
                    this.scan(node);
                }
            }));
        });
        this._mutations.observe(document.body, { childList: true, subtree: true });

        // Beim Verlassen der Seite noch ausstehende Meldungen senden
        document.addEventListener('visibilitychange', () => document.visibilityState === 'hidden' && this.flush());
        window.addEventListener('pagehide', () => this.flush());
    }

    /** Alle Produkt-Elemente in einem Bereich beobachten */
    scan(root) {
        const elements = root.matches?.(PRODUCT_SELECTOR) ? [root] : [];
        root.querySelectorAll?.(PRODUCT_SELECTOR).forEach((element) => elements.push(element));

        elements.forEach((element) => {
            // Duplikate des Endlos-Sliders überspringen
            if (!element.closest('.tns-slide-cloned')) {
                this._observer.observe(element);
            }
        });
    }

    info(element) {
        try {
            return JSON.parse(element.getAttribute('data-product-information'));
        } catch (e) {
            return null;
        }
    }

    /** Liste eines Produkt-Elements bestimmen – siehe Regeln oben */
    resolveList(element) {
        const custom = element.closest('[data-nova-list-id]');
        if (custom) {
            const id = custom.getAttribute('data-nova-list-id');
            return { container: custom, item_list_id: id, item_list_name: custom.getAttribute('data-nova-list-name') || id };
        }

        const pane = element.closest('.product-detail-cross-selling .tab-pane');
        if (pane) {
            const id = (pane.id || '').replace(/^cross-selling-tab-/, '').replace(/-pane$/, '') || 'unknown';
            const tab = document.getElementById(`cross-selling-tab-${id}`);
            const title = (tab?.getAttribute('title') || tab?.textContent || '').trim();
            return { container: pane, item_list_id: `cross_selling_${id}`, item_list_name: `Cross-Selling: ${title}` };
        }

        const slider = element.closest('.cms-element-product-slider');
        if (slider) {
            if (!slider.hasAttribute('data-nova-slider-index')) {
                slider.setAttribute('data-nova-slider-index', String(++this._sliderCount));
            }
            const title = (slider.querySelector('.cms-element-title-paragraph')?.textContent || 'Produkt-Slider').trim();
            return {
                container: slider,
                item_list_id: `product_slider_${slider.getAttribute('data-nova-slider-index')}`,
                item_list_name: `Slider: ${title}`,
            };
        }

        const listing = element.closest(LISTING_SELECTOR);
        if (listing) {
            return {
                container: listing,
                item_list_id: this._cfg.list.item_list_id || 'listing',
                item_list_name: this._cfg.list.item_list_name || 'Listing',
                listing: true,
            };
        }

        return { container: document.body, item_list_id: 'product_list', item_list_name: 'Produktliste' };
    }

    /** GA4-Item für ein Produkt-Element inkl. Listenbezug und Position */
    itemFor(element) {
        const info = this.info(element);
        if (!info) {
            return null;
        }

        const list = this.resolveList(element);
        const siblings = [...list.container.querySelectorAll(PRODUCT_SELECTOR)]
            .filter((el) => !el.closest('.tns-slide-cloned'));
        const base = this._cfg.items[info.id] || {
            item_id: String(info.sku || info.id),
            item_name: info.name,
            item_brand: info.brand || '',
            price: Math.round((Number(info.price) || 0) * 100) / 100,
        };

        return {
            productId: info.id,
            list,
            item: {
                ...base,
                item_list_id: list.item_list_id,
                item_list_name: list.item_list_name,
                index: Math.max(0, siblings.indexOf(element)),
            },
        };
    }

    _onIntersect(entries) {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            const element = entry.target;
            this._observer.unobserve(element);

            if (this._serverRendered.has(element)) {
                return;
            }

            const result = this.itemFor(element);
            if (!result) {
                return;
            }

            const key = `${result.list.item_list_id}|${result.productId}`;
            if (this._seen.has(key)) {
                return;
            }
            this._seen.add(key);

            this._enqueue(result.list, result.item);
        });
    }

    _enqueue(list, item) {
        const queue = this._queues.get(list.item_list_id) || { list, items: [] };
        queue.items.push(item);
        this._queues.set(list.item_list_id, queue);

        if (queue.items.length >= MAX_ITEMS) {
            this._send(list.item_list_id);
        }

        if (!this._timer) {
            this._timer = window.setTimeout(() => this.flush(), FLUSH_DELAY);
        }
    }

    _send(listId) {
        const queue = this._queues.get(listId);
        this._queues.delete(listId);
        if (!queue || queue.items.length === 0) {
            return;
        }

        this._gtm.push({
            event: 'view_item_list',
            ecommerce: {
                item_list_id: queue.list.item_list_id,
                item_list_name: queue.list.item_list_name,
                items: queue.items,
            },
        });
    }

    flush() {
        window.clearTimeout(this._timer);
        this._timer = null;
        [...this._queues.keys()].forEach((listId) => this._send(listId));
    }
}
