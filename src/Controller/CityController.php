<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\City;
use App\Form\Type\CityType;
use App\Repository\CityRepository;
use App\Repository\GoshuinRepository;
use App\Repository\GoshuinchoRepository;
use App\Repository\LocationRepository;
use App\Repository\PrefectureRepository;
use App\Service\PhotoSet;
use App\Service\Uses;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CityController extends AbstractController
{
    public function __construct(
        private readonly CityRepository $cities,
        private readonly LocationRepository $locations,
        private readonly GoshuinRepository $goshuins,
        private readonly GoshuinchoRepository $goshuinchos,
        private readonly PrefectureRepository $prefectures,
        private readonly Uses $uses,
        private readonly PhotoSet $set,
    ) {
    }

    #[Route(path: '/cities', name: 'app_city_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$term, $page] = $this->browsing($request);
        $scope = $this->scopeOf($request, [
            'prefecture' => ['route' => 'app_prefecture_show', 'repository' => $this->prefectures],
        ]);
        $pages = $this->cities->pages($term, $scope?->subject);

        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render('App/City/index.html.twig', [
            'cities' => $this->cities->browse($term, $page, $scope?->subject),
            'scope' => $scope,
            'term' => $term,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route(path: '/city/{slug}', name: 'app_city_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] City $city): Response
    {
        return $this->render('App/City/show.html.twig', [
            'city' => $city,
            'locations' => $this->locations->countIn($city),
            'goshuinchos' => $this->goshuinchos->holding($city),
            'goshuins' => $this->goshuins->from($city),
        ]);
    }

    #[Route(path: '/city/{slug}/edit', name: 'app_city_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] City $city): Response
    {
        $form = $this->createForm(CityType::class, $city);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->set->applyFrom($request, $city, 'city');

            return $this->redirectToRoute('app_city_show', ['slug' => $city->getSlug()]);
        }

        return $this->render('App/City/edit.html.twig', [
            'form' => $form,
            'city' => $city,
        ]);
    }

    #[Route(path: '/city/{slug}/delete', name: 'app_city_delete', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] City $city): Response
    {
        return $this->deleteSluggable($request, $city, 'city', $this->uses->of($city));
    }
}
