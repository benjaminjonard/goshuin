<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(event: 'kernel.request')]
final readonly class FilterListener
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private Security $security,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $filters = $this->managerRegistry->getManager()->getFilters();
        $user = $this->security->getUser();

        if ($user instanceof User && !$this->isAdministration($event->getRequest())) {
            $filters->enable('ownership')->setParameter('id', $user->getId(), 'string');

            return;
        }

        if ($filters->isEnabled('ownership')) {
            $filters->disable('ownership');
        }
    }

    private function isAdministration(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/admin');
    }
}
