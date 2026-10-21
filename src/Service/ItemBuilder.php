<?php declare(strict_types=1);

namespace Nova\TagManager\Service;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;

/**
 * Wandelt Shopware-Produkte und Positionen in GA4-„items“ um.
 */
class ItemBuilder
{
    public const LINE_ITEM_TYPE_PRODUCT = 'product';

    /**
     * @param array<string, mixed> $extra Zusätzliche Felder, z. B. index oder item_list_name
     *
     * @return array<string, mixed>
     */
    public function fromProduct(SalesChannelProductEntity $product, ?string $fallbackCategory = null, array $extra = []): array
    {
        $prices = $product->getCalculatedPrices();
        $unitPrice = $prices->count() > 0
            ? $prices->first()?->getUnitPrice()
            : $product->getCalculatedPrice()->getUnitPrice();

        $item = [
            'item_id' => (string) $product->getProductNumber(),
            'item_name' => (string) ($product->getTranslation('name') ?? $product->getName()),
            'item_brand' => (string) ($product->getManufacturer()?->getTranslation('name') ?? ''),
            'item_category' => (string) ($product->getSeoCategory()?->getTranslation('name') ?? $fallbackCategory ?? ''),
            'item_variant' => $this->variantLabel($product->getVariation()),
            'price' => round((float) $unitPrice, 2),
        ];

        return array_merge($item, $extra);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromCartLineItem(LineItem $lineItem): ?array
    {
        if ($lineItem->getType() !== self::LINE_ITEM_TYPE_PRODUCT) {
            return null;
        }

        return [
            'item_id' => (string) ($lineItem->getPayloadValue('productNumber') ?? $lineItem->getReferencedId()),
            'item_name' => (string) $lineItem->getLabel(),
            'item_variant' => $this->optionsLabel($lineItem->getPayload()['options'] ?? []),
            'price' => round((float) $lineItem->getPrice()?->getUnitPrice(), 2),
            'quantity' => $lineItem->getQuantity(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fromOrderLineItem(OrderLineItemEntity $lineItem): ?array
    {
        if ($lineItem->getType() !== self::LINE_ITEM_TYPE_PRODUCT) {
            return null;
        }

        $payload = $lineItem->getPayload() ?? [];

        return [
            'item_id' => (string) ($payload['productNumber'] ?? $lineItem->getReferencedId()),
            'item_name' => (string) $lineItem->getLabel(),
            'item_variant' => $this->optionsLabel($payload['options'] ?? []),
            'price' => round($lineItem->getUnitPrice(), 2),
            'quantity' => $lineItem->getQuantity(),
        ];
    }

    /**
     * @param array<int, array{group?: string, option?: string}> $variation
     */
    private function variantLabel(array $variation): string
    {
        return implode(' / ', array_map(
            static fn (array $v): string => trim(($v['group'] ?? '') . ': ' . ($v['option'] ?? ''), ': '),
            $variation
        ));
    }

    /**
     * @param array<int, array{group?: string, option?: string}> $options
     */
    private function optionsLabel(array $options): string
    {
        return $this->variantLabel(array_values(array_filter($options, 'is_array')));
    }
}
