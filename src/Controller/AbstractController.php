<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\City;
use App\Entity\Interface\Sluggable;
use App\Entity\Prefecture;
use App\Entity\Tag;
use App\Model\Scope;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

abstract class AbstractController extends SymfonyAbstractController
{
    protected EntityManagerInterface $entityManager;

    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    protected function createDeleteForm(string $route, array $parameters = []): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl($route, $parameters))
            ->setMethod(Request::METHOD_POST)
            ->getForm()
        ;
    }

    /**
     * @return array{string, int}
     */
    protected function browsing(Request $request): array
    {
        return [
            trim($request->query->getString('q')),
            max(1, $request->query->getInt('page', 1)),
        ];
    }

    /**
     * @param array<string, array{route: string, repository: ObjectRepository<City|Prefecture|Tag>}> $narrowings
     */
    protected function scopeOf(Request $request, array $narrowings): ?Scope
    {
        foreach ($narrowings as $key => $narrowing) {
            $slug = trim($request->query->getString($key));

            if ($slug === '') {
                continue;
            }

            $place = $narrowing['repository']->findOneBy(['slug' => $slug]);

            if ($place === null) {
                throw $this->createNotFoundException();
            }

            return new Scope(
                key: $key,
                value: $slug,
                icon: $key,
                label: $place->getName(),
                href: $this->generateUrl($narrowing['route'], ['slug' => $slug]),
                subject: $place,
            );
        }

        return null;
    }

    protected function deleteSluggable(Request $request, Sluggable $subject, string $name, int $held, ?string $blocked = null): Response
    {
        $slug = $subject->getSlug();
        $form = $this->createDeleteForm('app_'.$name.'_delete', ['slug' => $slug]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($held > 0) {
                return $this->redirectToRoute($blocked ?? 'app_'.$name.'_show', ['slug' => $slug]);
            }

            $this->entityManager->remove($subject);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_'.$name.'_index');
        }

        return $this->render('App/'.ucfirst($name).'/delete.html.twig', [
            $name => $subject,
            'held' => $held,
            'form' => $form,
        ]);
    }
}
