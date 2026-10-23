<?php declare(strict_types=1);

namespace Nova\TagManager\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * Kundendaten für die „erweiterten Conversions“ von Google Ads – im Format, das Googles
 * „Von Nutzern bereitgestellte Daten“ erwartet (E-Mail klein geschrieben, Telefon im Format +49…).
 * Google hasht die Werte selbst, bevor sie gesendet werden.
 */
class UserDataBuilder
{
    /** Ländervorwahlen für Telefonnummern ohne +/00 (nur Länder, in die typischerweise geliefert wird) */
    private const CALLING_CODES = [
        'DE' => '49', 'AT' => '43', 'CH' => '41', 'FR' => '33', 'NL' => '31', 'BE' => '32', 'LU' => '352',
        'IT' => '39', 'ES' => '34', 'PT' => '351', 'DK' => '45', 'SE' => '46', 'NO' => '47', 'FI' => '358',
        'PL' => '48', 'CZ' => '420', 'SK' => '421', 'HU' => '36', 'SI' => '386', 'HR' => '385', 'GB' => '44',
        'IE' => '353', 'GR' => '30',
    ];

    /**
     * @return array{email?: string, phone_number?: string, address?: array<string, string>}
     */
    public static function fromOrder(OrderEntity $order): array
    {
        $data = [];

        $email = self::email((string) $order->getOrderCustomer()?->getEmail());
        if ($email !== null) {
            $data['email'] = $email;
        }

        $address = self::billingAddress($order);
        if ($address === null) {
            return $data;
        }

        $country = $address->getCountry()?->getIso();
        $phone = self::phone((string) $address->getPhoneNumber(), $country);
        if ($phone !== null) {
            $data['phone_number'] = $phone;
        }

        $data['address'] = array_filter([
            'first_name' => trim((string) $address->getFirstName()),
            'last_name' => trim((string) $address->getLastName()),
            'street' => trim((string) $address->getStreet()),
            'city' => trim((string) $address->getCity()),
            'postal_code' => trim((string) $address->getZipcode()),
            'country' => strtoupper(trim((string) $country)),
        ], static fn (string $value): bool => $value !== '');

        if ($data['address'] === []) {
            unset($data['address']);
        }

        return $data;
    }

    public static function email(string $email): ?string
    {
        $email = mb_strtolower(trim($email));

        return filter_var($email, \FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /**
     * E.164: „+“, Ländervorwahl, Nummer ohne führende 0 – z. B. „06403 1234“ (DE) → „+4964031234“.
     */
    public static function phone(string $phone, ?string $countryIso): ?string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }

        // „+49 (0)6403 …“: die eingeklammerte 0 gehört nicht zur internationalen Nummer
        $phone = str_replace('(0)', '', $phone);
        $plus = str_starts_with($phone, '+');
        $digits = (string) preg_replace('/\D/', '', $phone);

        if ($plus) {
            $number = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $number = substr($digits, 2);
        } else {
            $code = self::CALLING_CODES[strtoupper((string) $countryIso)] ?? null;
            if ($code === null || !str_starts_with($digits, '0')) {
                return null;
            }
            $number = $code . ltrim($digits, '0');
        }

        return preg_match('/^[1-9]\d{7,14}$/', $number) === 1 ? '+' . $number : null;
    }

    private static function billingAddress(OrderEntity $order): ?OrderAddressEntity
    {
        $billing = $order->getBillingAddress();
        if ($billing !== null) {
            return $billing;
        }

        return $order->getAddresses()?->get($order->getBillingAddressId());
    }
}
