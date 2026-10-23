<?php declare(strict_types=1);

namespace Nova\TagManager\Subscriber;

use Nova\TagManager\Service\ItemBuilder;
use Nova\TagManager\Service\UserDataBuilder;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Search\SearchPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Baut pro Seite die Datenlayer-Einträge (GA4-E-Commerce + Google-Ads-Remarketing)
 * und hängt sie als Extension „novaTagManager“ an die Seite. Ausgegeben werden sie
 * im Template layout/meta.html.twig.
 */
class PageDataSubscriber implements EventSubscriberInterface
{
    public const EXTENSION = 'novaTagManager';

    public function __construct(
        private readonly ItemBuilder $itemBuilder,
        private readonly SystemConfigService $config,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPage',
            NavigationPageLoadedEvent::class => 'onNavigationPage',
            SearchPageLoadedEvent::class => 'onSearchPage',
            CheckoutCartPageLoadedEvent::class => 'onCartPage',
            CheckoutConfirmPageLoadedEvent::class => 'onConfirmPage',
            CheckoutFinishPageLoadedEvent::class => 'onFinishPage',
        ];
    }

    public function onProductPage(ProductPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        if (!$this->isActive($context)) {
            return;
        }

        $product = $event->getPage()->getProduct();
        $item = $this->itemBuilder->fromProduct($product);

        $this->attach($event->getPage(), [
            $this->remarketing('product', [
                'ecomm_prodid' => $item['item_id'],
                'ecomm_totalvalue' => $item['price'],
            ]),
            $this->ecommerce('view_item', [
                'currency' => $this->currency($context),
                'value' => $item['price'],
                'items' => [$item],
            ]),
        ], [$product->getId() => $item]);
    }

    public function onNavigationPage(NavigationPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        if (!$this->isActive($context)) {
            return;
        }

        $page = $event->getPage();
        $isHome = $event->getRequest()->attributes->get('_route') === 'frontend.home.page';
        $category = $page->getCategory();
        $categoryName = (string) ($category?->getTranslation('name') ?? '');

        $products = $this->listingProducts($page->getCmsPage());
        $list = [
            'item_list_id' => 'category_' . ($category?->getId() ?? $page->getNavigationId()),
            'item_list_name' => 'Category: ' . $categoryName,
        ];

        [$items, $map] = $this->listItems($products, $list, $categoryName);

        $pushes = [
            $this->remarketing($isHome ? 'homepage' : 'category', array_filter([
                'ecomm_prodid' => array_column($items, 'item_id'),
                'ecomm_category' => $isHome ? null : $categoryName,
            ], static fn ($v) => $v !== null)),
        ];

        if ($items !== []) {
            $pushes[] = $this->ecommerce('view_item_list', $list + ['items' => $items]);
        }

        $this->attach($page, $pushes, $map, $list);
    }

    public function onSearchPage(SearchPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        if (!$this->isActive($context)) {
            return;
        }

        $page = $event->getPage();
        $term = $page->getSearchTerm();
        $list = ['item_list_id' => 'search', 'item_list_name' => 'Search'];

        [$items, $map] = $this->listItems($page->getListing()->getEntities(), $list);

        $pushes = [
            $this->remarketing('searchresults', ['ecomm_prodid' => array_column($items, 'item_id')]) + ['search_term' => $term],
            ['event' => 'search', 'search_term' => $term],
        ];

        if ($items !== []) {
            $pushes[] = $this->ecommerce('view_item_list', $list + ['items' => $items]);
        }

        $this->attach($page, $pushes, $map, $list);
    }

    public function onCartPage(CheckoutCartPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        if (!$this->isActive($context)) {
            return;
        }

        $cart = $event->getPage()->getCart();
        $payload = $this->cartPayload($cart, $context);

        $this->attach($event->getPage(), [
            $this->remarketing('cart', [
                'ecomm_prodid' => array_column($payload['items'], 'item_id'),
                'ecomm_totalvalue' => $payload['value'],
            ]),
            $this->ecommerce('view_cart', $payload),
        ]);
    }

    public function onConfirmPage(CheckoutConfirmPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        if (!$this->isActive($context)) {
            return;
        }

        $payload = $this->cartPayload($event->getPage()->getCart(), $context);

        $this->attach($event->getPage(), [
            $this->remarketing('cart', [
                'ecomm_prodid' => array_column($payload['items'], 'item_id'),
                'ecomm_totalvalue' => $payload['value'],
            ]),
            $this->ecommerce('begin_checkout', $payload),
            $this->ecommerce('add_shipping_info', $payload + [
                'shipping_tier' => (string) $context->getShippingMethod()->getTranslation('name'),
            ]),
            $this->ecommerce('add_payment_info', $payload + [
                'payment_type' => (string) $context->getPaymentMethod()->getTranslation('name'),
            ]),
        ]);
    }

    public function onFinishPage(CheckoutFinishPageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $page = $event->getPage();
        if (!$this->isActive($context) || $page->isPaymentFailed()) {
            return;
        }

        $order = $page->getOrder();
        $salesChannelId = $context->getSalesChannelId();

        $items = [];
        $coupons = [];
        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if ($lineItem->getType() === 'promotion') {
                $code = $lineItem->getPayload()['code'] ?? '';
                if ($code !== '') {
                    $coupons[] = $code;
                }
                continue;
            }

            $item = $this->itemBuilder->fromOrderLineItem($lineItem);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        $tax = 0.0;
        foreach ($order->getPrice()->getCalculatedTaxes() as $calculatedTax) {
            $tax += $calculatedTax->getTax();
        }

        $net = (bool) $this->config->get('NovaTagManager.config.revenueNet', $salesChannelId);
        $value = $net ? $order->getAmountNet() : $order->getAmountTotal();

        $purchase = [
            'transaction_id' => (string) $order->getOrderNumber(),
            'affiliation' => (string) $this->config->getString('core.basicInformation.shopName', $salesChannelId),
            'value' => round($value, 2),
            'tax' => round($tax, 2),
            'shipping' => round($order->getShippingTotal(), 2),
            'currency' => $order->getCurrency()?->getIsoCode() ?? $this->currency($context),
            'items' => $items,
        ];

        if ($coupons !== []) {
            $purchase['coupon'] = implode(',', $coupons);
        }

        // Erweiterte Conversions: nur wenn eingeschaltet und – beim Shopware-Cookie-Banner – mit Marketing-Einwilligung
        $userData = null;
        if ($this->config->getBool('NovaTagManager.config.enhancedConversions', $salesChannelId)) {
            $external = $this->config->getString('NovaTagManager.config.consentSource', $salesChannelId) === 'external';
            if ($external || $event->getRequest()->cookies->get('nova-gtm-marketing') === '1') {
                $userData = UserDataBuilder::fromOrder($order) ?: null;
            }
        }

        $this->attach($page, [
            $this->remarketing('purchase', [
                'ecomm_prodid' => array_column($items, 'item_id'),
                'ecomm_totalvalue' => round($order->getAmountTotal(), 2),
            ]),
            $this->ecommerce('purchase', $purchase),
        ], [], [], (string) $order->getOrderNumber(), $userData);
    }

    private function isActive(SalesChannelContext $context): bool
    {
        return $this->config->getString('NovaTagManager.config.containerId', $context->getSalesChannelId()) !== '';
    }

    private function currency(SalesChannelContext $context): string
    {
        return $context->getCurrency()->getIsoCode();
    }

    /**
     * @return array{currency: string, value: float, items: list<array<string, mixed>>}
     */
    private function cartPayload(Cart $cart, SalesChannelContext $context): array
    {
        $items = [];
        foreach ($cart->getLineItems() as $lineItem) {
            $item = $this->itemBuilder->fromCartLineItem($lineItem);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return [
            'currency' => $this->currency($context),
            'value' => round($cart->getPrice()->getTotalPrice(), 2),
            'items' => $items,
        ];
    }

    /**
     * @return list<SalesChannelProductEntity>
     */
    private function listingProducts(?CmsPageEntity $cmsPage): array
    {
        $products = [];
        foreach ($cmsPage?->getSections() ?? [] as $section) {
            foreach ($section->getBlocks() ?? [] as $block) {
                foreach ($block->getSlots() ?? [] as $slot) {
                    $data = $slot->getData();
                    if ($data instanceof ProductListingStruct && $data->getListing() !== null) {
                        foreach ($data->getListing()->getEntities() as $product) {
                            if ($product instanceof SalesChannelProductEntity) {
                                $products[] = $product;
                            }
                        }
                    }
                }
            }
        }

        return $products;
    }

    /**
     * @param iterable<mixed> $products
     * @param array{item_list_id: string, item_list_name: string} $list
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, array<string, mixed>>}
     */
    private function listItems(iterable $products, array $list, ?string $fallbackCategory = null): array
    {
        $items = [];
        $map = [];
        $index = 0;

        foreach ($products as $product) {
            if (!$product instanceof SalesChannelProductEntity) {
                continue;
            }

            $item = $this->itemBuilder->fromProduct($product, $fallbackCategory, $list + ['index' => $index++]);
            $items[] = $item;
            $map[$product->getId()] = $item;
        }

        return [$items, $map];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function remarketing(string $pageType, array $params): array
    {
        return ['google_tag_params' => $params + ['ecomm_pagetype' => $pageType]];
    }

    /**
     * @param array<string, mixed> $ecommerce
     *
     * @return array<string, mixed>
     */
    private function ecommerce(string $event, array $ecommerce): array
    {
        return ['event' => $event, 'ecommerce' => $ecommerce];
    }

    /**
     * @param list<array<string, mixed>> $pushes
     * @param array<string, array<string, mixed>> $items Produkt-ID → GA4-Item (für Klick-Events im Browser)
     * @param array<string, string> $list
     * @param array<string, mixed>|null $userData Kundendaten für erweiterte Conversions (nur Abschlussseite)
     */
    private function attach(Page $page, array $pushes, array $items = [], array $list = [], ?string $transactionId = null, ?array $userData = null): void
    {
        $page->addExtension(self::EXTENSION, new ArrayStruct([
            'pushes' => $pushes,
            'items' => $items,
            'list' => $list,
            'transactionId' => $transactionId,
            'userData' => $userData,
        ]));
    }
}
