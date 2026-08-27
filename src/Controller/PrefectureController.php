<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Prefecture;
use App\Form\Type\PrefectureType;
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

class PrefectureController extends AbstractController
{
    public function __construct(
        private readonly PrefectureRepository $prefectures,
        private readonly CityRepository $cities,
        private readonly LocationRepository $locations,
        private readonly GoshuinRepository $goshuins,
        private readonly GoshuinchoRepository $goshuinchos,
        private readonly Uses $uses,
        private readonly PhotoSet $set,
    ) {
    }

    #[Route(path: '/prefectures', name: 'app_prefecture_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$term, $page] = $this->browsing($request);
        $pages = $this->prefectures->pages($term);

        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render('App/Prefecture/index.html.twig', [
            'prefectures' => $this->prefectures->browse($request->getLocale(), $term, $page),
            'term' => $term,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route(path: '/prefecture/{id}', name: 'app_prefecture_show', methods: ['GET'])]
    public function show(#[MapEntity(expr: 'repository.findById(id)')] Prefecture $prefecture): Response
    {
        return $this->render('App/Prefecture/show.html.twig', [
            'prefecture' => $prefecture,
            'cities' => $this->cities->countIn($prefecture),
            'locations' => $this->locations->countIn($prefecture),
            'goshuinchos' => $this->goshuinchos->holding($prefecture),
            'goshuins' => $this->goshuins->from($prefecture),
        ]);
    }

    #[Route(path: '/prefecture/{id}/edit', name: 'app_prefecture_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Prefecture $prefecture): Response
    {
        $form = $this->createForm(PrefectureType::class, $prefecture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->set->applyFrom($request, $prefecture, 'prefecture');

            return $this->redirectToRoute('app_prefecture_show', ['id' => $prefecture->getId()]);
        }

        return $this->render('App/Prefecture/edit.html.twig', [
            'form' => $form,
            'prefecture' => $prefecture,
        ]);
    }

    #[Route(path: '/prefecture/{id}/delete', name: 'app_prefecture_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Prefecture $prefecture): Response
    {
        return $this->deleteSubject($request, $prefecture, 'prefecture', $this->uses->of($prefecture));
    }
}
