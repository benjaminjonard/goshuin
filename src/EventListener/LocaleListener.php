<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

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
        $locale = $request->hasPreviousSession() ? $request->getSession()->get('_locale') : null;

        $request->setLocale(\in_array($locale, $this->locales, true) ? $locale : $this->defaultLocale);
    }
}
