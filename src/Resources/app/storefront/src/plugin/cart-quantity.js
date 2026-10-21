/**
 * Mengenänderungen im Warenkorb (Warenkorbseite und Offcanvas-Warenkorb).
 *
 * Mehr → `add_to_cart`, weniger → `remove_from_cart`, jeweils mit der Differenzmenge.
 * Mehrere schnelle Klicks auf +/- werden zusammengefasst (1 s Ruhe).
 * Die Artikeldaten stehen im Mengenformular (component/line-item/element/quantity.html.twig).
 */
const DELAY = 1000;

export default class CartQuantity {
    constructor(gtm) {
        this._gtm = gtm;
        this._baseline = new Map();   // item_id → zuletzt gemeldete Menge
        this._pending = new Map();    // item_id → { item, timer }

        document.addEventListener('change', (event) => this._onChange(event), true);
    }

    _onChange(event) {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.name !== 'quantity' || !input.form) {
            return;
        }

        const marker = input.form.querySelector('[data-nova-gtm-role="quantity"]');
        if (!marker) {
            return;
        }

        let item;
        try {
            item = JSON.parse(marker.getAttribute('data-nova-gtm-item'));
        } catch (e) {
            return;
        }

        if (!this._baseline.has(item.item_id)) {
            this._baseline.set(item.item_id, Number(item.quantity) || 0);
        }

        const pending = this._pending.get(item.item_id);
        window.clearTimeout(pending?.timer);

        const quantity = parseInt(input.value, 10);
        if (Number.isNaN(quantity)) {
            return;
        }

        this._pending.set(item.item_id, {
            timer: window.setTimeout(() => this._report(item, quantity), DELAY),
        });
    }

    _report(item, quantity) {
        this._pending.delete(item.item_id);

        const before = this._baseline.get(item.item_id);
        const diff = quantity - before;
        if (diff === 0) {
            return;
        }
        this._baseline.set(item.item_id, quantity);

        const amount = Math.abs(diff);
        this._gtm.push({
            event: diff > 0 ? 'add_to_cart' : 'remove_from_cart',
            ecommerce: {
                currency: this._gtm.config.currency,
                value: Math.round(item.price * amount * 100) / 100,
                items: [{ ...item, quantity: amount }],
            },
        });
    }
}
