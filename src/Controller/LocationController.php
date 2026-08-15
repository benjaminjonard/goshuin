<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Location;
use App\Form\Type\LocationType;
use App\Repository\CityRepository;
use App\Repository\LocationRepository;
use App\Repository\PrefectureRepository;
use App\Service\PhotoSet;
use App\Service\Uses;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationRepository $locations,
        private readonly CityRepository $cities,
        private readonly PrefectureRepository $prefectures,
        private readonly Uses $uses,
        private readonly PhotoSet $set,
    ) {
    }

    #[Route(path: '/locations', name: 'app_location_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$term, $page] = $this->browsing($request);
        $scope = $this->scopeOf($request, [
            'city' => ['route' => 'app_city_show', 'repository' => $this->cities],
            'prefecture' => ['route' => 'app_prefecture_show', 'repository' => $this->prefectures],
        ]);
        $pages = $this->locations->pages($term, $scope?->subject);

        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render('App/Location/index.html.twig', [
            'locations' => $this->locations->browse($term, $page, $scope?->subject),
            'scope' => $scope,
            'term' => $term,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route(path: '/location/{slug}', name: 'app_location_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Location $location): Response
    {
        return $this->render('App/Location/show.html.twig', [
            'location' => $location,
        ]);
    }

    #[Route(path: '/location/{slug}/edit', name: 'app_location_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Location $location): Response
    {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->set->applyFrom($request, $location, 'place');

            return $this->redirectToRoute('app_location_show', ['slug' => $location->getSlug()]);
        }

        return $this->render('App/Location/edit.html.twig', [
            'form' => $form,
            'location' => $location,
        ]);
    }

    #[Route(path: '/location/{slug}/delete', name: 'app_location_delete', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Location $location): Response
    {
        return $this->deleteSluggable($request, $location, 'location', $this->uses->of($location));
    }
}
