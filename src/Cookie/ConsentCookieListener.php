<?php declare(strict_types=1);

namespace Nova\TagManager\Cookie;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Trägt zwei Einträge in den Shopware-Cookie-Dialog ein:
 *  - „Statistik“  → analytics_storage
 *  - „Marketing“ → ad_storage, ad_user_data, ad_personalization
 */
class ConsentCookieListener
{
    public const COOKIE_STATISTICS = 'nova-gtm-statistics';
    public const COOKIE_MARKETING = 'nova-gtm-marketing';

    public function __construct(private readonly SystemConfigService $config)
    {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();

        if ($this->config->getString('NovaTagManager.config.containerId', $salesChannelId) === ''
            || $this->config->getString('NovaTagManager.config.consentSource', $salesChannelId) === 'external') {
            return;
        }

        $groups = $event->cookieGroupCollection;

        $this->addEntry($groups, CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL, self::COOKIE_STATISTICS, 'novaTagManager.cookie.statistics');
        $this->addEntry($groups, CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING, self::COOKIE_MARKETING, 'novaTagManager.cookie.marketing');
    }

    private function addEntry(CookieGroupCollection $groups, string $groupName, string $cookie, string $label): void
    {
        $group = $groups->get($groupName);
        if ($group === null) {
            return;
        }

        $entries = $group->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $group->setEntries($entries);
        }

        $entry = new CookieEntry($cookie);
        $entry->name = $label;
        $entry->value = '1';
        $entry->expiration = 30;

        $entries->add($entry);
    }
}
