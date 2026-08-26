<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\LocaleHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request', priority: 15)]
final readonly class LocaleListener
{
    public function __construct(
        private LocaleHelper $locales,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$request->hasPreviousSession()) {
            return;
        }

        $locale = $request->getSession()->get('_locale');

        if (\is_string($locale) && $this->locales->knows($locale)) {
            $request->setLocale($locale);
        }
    }
}
