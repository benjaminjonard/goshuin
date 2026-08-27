<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Location;
use App\Form\Type\LocationType;
use App\Repository\CityRepository;
use App\Repository\DeityRepository;
use App\Repository\LocationRepository;
use App\Repository\PrefectureRepository;
use App\Service\PhotoSet;
use App\Service\Uses;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocationController extends AbstractController
{
    public function __construct(
        private readonly LocationRepository $locations,
        private readonly CityRepository $cities,
        private readonly PrefectureRepository $prefectures,
        private readonly DeityRepository $deities,
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
            'locations' => $this->locations->browse($request->getLocale(), $term, $page, $scope?->subject),
            'scope' => $scope,
            'term' => $term,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route(path: '/location/{id}', name: 'app_location_show', methods: ['GET'])]
    public function show(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Location $location): Response
    {
        return $this->render('App/Location/show.html.twig', [
            'enshrined' => $this->deities->enshrinedIn($location, $request->getLocale()),
            'location' => $location,
        ]);
    }

    #[Route(path: '/location/{id}/edit', name: 'app_location_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Location $location): Response
    {
        $form = $this->createForm(LocationType::class, $location);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->set->applyFrom($request, $location, 'place');

            return $this->redirectToRoute('app_location_show', ['id' => $location->getId()]);
        }

        return $this->render('App/Location/edit.html.twig', [
            'form' => $form,
            'location' => $location,
        ]);
    }

    #[Route(path: '/location/{id}/delete', name: 'app_location_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Location $location): Response
    {
        return $this->deleteSubject($request, $location, 'location', $this->uses->of($location));
    }
}
