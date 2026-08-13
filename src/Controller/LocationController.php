<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Location;
use App\Form\Type\LocationType;
use App\Repository\LocationRepository;
use App\Service\Uses;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationRepository $locations,
        private readonly Uses $uses,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/locations', name: 'app_location_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $term = trim($request->query->getString('q'));

        return $this->render('App/Location/index.html.twig', [
            'locations' => $this->locations->browse($term),
            'term' => $term,
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
    public function edit(Request $request, Location $location): Response
    {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_location_show', ['id' => $location->getId()]);
        }

        return $this->render('App/Location/edit.html.twig', [
            'form' => $form,
            'location' => $location,
        ]);
    }

    #[Route(path: '/location/{id}/delete', name: 'app_location_delete', methods: ['GET', 'POST'])]
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
}
