<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Location;
use App\Form\Type\LocationType;
use App\Repository\LocationRepository;
use App\Service\PhotoInstructions;
use App\Service\PhotoSet;
use App\Service\Uses;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationRepository $locations,
        private readonly Uses $uses,
        private readonly PhotoSet $set,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/locations', name: 'app_location_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $term = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));
        $pages = $this->locations->pages($term);

        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render('App/Location/index.html.twig', [
            'locations' => $this->locations->browse($term, $page),
            'term' => $term,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route(path: '/location/{id}', name: 'app_location_show', methods: ['GET'])]
    public function show(Location $location): Response
    {
        return $this->render('App/Location/show.html.twig', [
            'location' => $location,
        ]);
    }

    #[Route(path: '/location/{id}/edit', name: 'app_location_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Location $location): Response
    {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->photograph($request, $location);

            return $this->redirectToRoute('app_location_show', ['id' => $location->getId()]);
        }

        return $this->render('App/Location/edit.html.twig', [
            'form' => $form,
            'location' => $location,
        ]);
    }

    #[Route(path: '/location/{id}/delete', name: 'app_location_delete', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Location $location): Response
    {
        $held = $this->uses->of($location);
        $form = $this->createDeleteForm('app_location_delete', ['id' => $location->getId()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($held > 0) {
                return $this->redirectToRoute('app_location_show', ['id' => $location->getId()]);
            }

            $this->entityManager->remove($location);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_location_index');
        }

        return $this->render('App/Location/delete.html.twig', [
            'location' => $location,
            'held' => $held,
            'form' => $form,
        ]);
    }

    private function photograph(Request $request, Location $location): void
    {
        $this->set->applyToLocation($location, new PhotoInstructions(
            order: array_values($request->request->all()['photo_order']['place'] ?? []),
            labels: $request->request->all()['photo_label']['place'] ?? [],
            removed: array_values($request->request->all()['photo_remove']['place'] ?? []),
            added: array_values(array_filter($request->files->all()['photo_add']['place'] ?? [])),
            addedLabels: array_values($request->request->all()['photo_add_label']['place'] ?? []),
        ));
    }
}
