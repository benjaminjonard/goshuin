<?php

declare(strict_types=1);

namespace App\Tests\App;

use App\Entity\User;
use App\EventListener\OwnerListener;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class OwnerListenerTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function test_it_assigns_the_authenticated_user_to_an_ownerless_entity(): void
    {
        $user = UserFactory::createOne();
        $entity = $this->owned();

        $this->listenerFor($user)->prePersist($this->event($entity));

        $this->assertSame($user, $entity->getOwner());
    }

    public function test_it_leaves_an_owner_that_is_already_set(): void
    {
        $mine = UserFactory::createOne();
        $theirs = UserFactory::createOne();
        $entity = $this->owned();
        $entity->setOwner($theirs);

        $this->listenerFor($mine)->prePersist($this->event($entity));

        $this->assertSame($theirs, $entity->getOwner(), 'An existing owner was overwritten.');
    }

    public function test_it_assigns_nothing_when_nobody_is_authenticated(): void
    {
        $entity = $this->owned();

        $this->listenerFor(null)->prePersist($this->event($entity));

        $this->assertNull($entity->getOwner());
    }

    public function test_it_ignores_an_entity_without_an_owner(): void
    {
        $user = UserFactory::createOne();
        $entity = new \stdClass();

        $this->listenerFor($user)->prePersist($this->event($entity));

        $this->assertObjectNotHasProperty('owner', $entity);
    }

    private function listenerFor(?User $user): OwnerListener
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new OwnerListener($security);
    }

    private function event(object $entity): PrePersistEventArgs
    {
        return new PrePersistEventArgs($entity, static::getContainer()->get('doctrine')->getManager());
    }

    private function owned(): object
    {
        return new class {
            private ?User $owner = null;

            public function getOwner(): ?User
            {
                return $this->owner;
            }

            public function setOwner(?User $owner): void
            {
                $this->owner = $owner;
            }
        };
    }
}
