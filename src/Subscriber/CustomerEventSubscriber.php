<?php declare(strict_types=1);

namespace Nova\TagManager\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Merkt sich Login und Registrierung und gibt sie per kurzlebigem Cookie an den
 * Browser weiter. Dort schreibt das Storefront-Plugin das Event in den Datenlayer.
 * Funktioniert auch mit HTTP-Cache, da kein Seiteninhalt verändert wird.
 */
class CustomerEventSubscriber implements EventSubscriberInterface
{
    public const COOKIE = 'nova-gtm-event';

    private ?string $pendingEvent = null;

    public function __construct(private readonly SystemConfigService $config)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onLogin',
            CustomerRegisterEvent::class => 'onRegister',
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onLogin(CustomerLoginEvent $event): void
    {
        if ($this->isActive($event->getSalesChannelId())) {
            // Registrierung hat Vorrang, falls direkt danach automatisch eingeloggt wird
            $this->pendingEvent ??= 'login';
        }
    }

    public function onRegister(CustomerRegisterEvent $event): void
    {
        if ($this->isActive($event->getSalesChannelId())) {
            $this->pendingEvent = 'sign_up';
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($this->pendingEvent === null || !$event->isMainRequest()) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(self::COOKIE, $this->pendingEvent)
                ->withExpires(time() + 300)
                ->withPath('/')
                ->withHttpOnly(false)
                ->withSecure($event->getRequest()->isSecure())
                ->withSameSite(Cookie::SAMESITE_LAX)
        );

        $this->pendingEvent = null;
    }

    private function isActive(string $salesChannelId): bool
    {
        return $this->config->getString('NovaTagManager.config.containerId', $salesChannelId) !== '';
    }
}
