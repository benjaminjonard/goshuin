<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\City;
use App\Entity\Goshuincho;
use App\Form\Type\CityType;
use App\Repository\CityRepository;
use App\Repository\GoshuinRepository;
use App\Repository\GoshuinchoRepository;
use App\Repository\LocationRepository;
use App\Repository\PrefectureRepository;
use App\Service\PhotoInstructions;
use App\Service\PhotoSet;
use App\Service\Scope;
use App\Service\Uses;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/cities', name: 'app_city_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $term = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));
        $scope = $this->scope($request);
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

    #[Route(path: '/city/{id}', name: 'app_city_show', methods: ['GET'])]
    public function show(City $city): Response
    {
        return $this->render('App/City/show.html.twig', [
            'city' => $city,
            'locations' => $this->locations->countIn($city),
            'goshuinchos' => $this->goshuinchos->holding($city),
            'goshuins' => $this->goshuins->from($city),
        ]);
    }

    #[Route(path: '/city/{id}/edit', name: 'app_city_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, City $city): Response
    {
        $form = $this->createForm(CityType::class, $city);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->photograph($request, $city);

            return $this->redirectToRoute('app_city_show', ['id' => $city->getId()]);
        }

        return $this->render('App/City/edit.html.twig', [
            'form' => $form,
            'city' => $city,
        ]);
    }

    #[Route(path: '/city/{id}/delete', name: 'app_city_delete', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, City $city): Response
    {
        $held = $this->uses->of($city);
        $form = $this->createDeleteForm('app_city_delete', ['id' => $city->getId()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($held > 0) {
                return $this->redirectToRoute('app_city_show', ['id' => $city->getId()]);
            }

            $this->entityManager->remove($city);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_city_index');
        }

        return $this->render('App/City/delete.html.twig', [
            'city' => $city,
            'held' => $held,
            'form' => $form,
        ]);
    }

    private function scope(Request $request): ?Scope
    {
        $slug = trim($request->query->getString('goshuincho'));

        if ($slug !== '') {
            $goshuincho = $this->goshuinchos->findOneBy(['slug' => $slug]);

            if ($goshuincho === null) {
                throw $this->createNotFoundException();
            }

            return new Scope(
                key: 'goshuincho',
                value: $slug,
                icon: 'goshuincho',
                label: $goshuincho->getTitle(),
                href: $this->generateUrl('app_goshuincho_show', ['slug' => $slug]),
                subject: $goshuincho,
            );
        }

        $id = trim($request->query->getString('prefecture'));

        if ($id !== '') {
            $prefecture = $this->prefectures->find($id);

            if ($prefecture === null) {
                throw $this->createNotFoundException();
            }

            return new Scope(
                key: 'prefecture',
                value: $id,
                icon: 'prefecture',
                label: $prefecture->getName(),
                href: $this->generateUrl('app_prefecture_show', ['id' => $id]),
                subject: $prefecture,
            );
        }

        return null;
    }

    private function photograph(Request $request, City $city): void
    {
        $this->set->applyTo($city, new PhotoInstructions(
            order: array_values($request->request->all()['photo_order']['city'] ?? []),
            labels: $request->request->all()['photo_label']['city'] ?? [],
            removed: array_values($request->request->all()['photo_remove']['city'] ?? []),
            added: array_values(array_filter($request->files->all()['photo_add']['city'] ?? [])),
            addedLabels: array_values($request->request->all()['photo_add_label']['city'] ?? []),
        ));
    }
}
