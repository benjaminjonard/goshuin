<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Deity;
use App\Form\Type\DeityType;
use App\Repository\DeityRepository;
use App\Repository\LocationRepository;
use App\Service\Uses;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DeityController extends AbstractController
{
    public function __construct(
        private readonly DeityRepository $deities,
        private readonly LocationRepository $locations,
        private readonly Uses $uses,
    ) {
    }

    #[Route(path: '/deities', name: 'app_deity_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$term, $page] = $this->browsing($request);
        $pages = $this->deities->pages($term);

        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render('App/Deity/index.html.twig', [
            'deities' => $this->deities->browse($term, $page),
            'term' => $term,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route(path: '/deity/{slug}', name: 'app_deity_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Deity $deity): Response
    {
        return $this->render('App/Deity/show.html.twig', [
            'deity' => $deity,
            'locations' => $this->locations->enshrining($deity),
        ]);
    }

    #[Route(path: '/deity/{slug}/edit', name: 'app_deity_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Deity $deity): Response
    {
        $form = $this->createForm(DeityType::class, $deity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_deity_show', ['slug' => $deity->getSlug()]);
        }

        return $this->render('App/Deity/edit.html.twig', [
            'form' => $form,
            'deity' => $deity,
        ]);
    }

    #[Route(path: '/deity/{slug}/delete', name: 'app_deity_delete', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Deity $deity): Response
    {
        return $this->deleteSluggable($request, $deity, 'deity', $this->uses->of($deity));
    }
}
