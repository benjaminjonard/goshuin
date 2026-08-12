<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * A URL identifies an object, not a language, so no route carries a locale prefix
 * and the locale is decided here instead (AD-12).
 *
 * The source of truth is the User's own preference. Until the User exists it comes
 * from the session, which is where Story 1.8 will put the stored choice — so this
 * listener does not change when that lands, only what fills the session does.
 */
#[AsEventListener(event: 'kernel.request', priority: 16)]
final readonly class LocaleListener
{
    public function __construct(
        #[Autowire('%default_locale%')] private string $defaultLocale,
        #[Autowire('%app.locales%')] private array $locales,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $request->hasSession() ? $request->getSession()->get('_locale') : null;

        $request->setLocale(\in_array($locale, $this->locales, true) ? $locale : $this->defaultLocale);
    }
}
