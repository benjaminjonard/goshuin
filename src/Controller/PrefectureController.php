<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Goshuincho;
use App\Entity\Prefecture;
use App\Form\Type\PrefectureType;
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
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/prefectures', name: 'app_prefecture_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $term = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));
        $scope = $this->scope($request);
        $pages = $this->prefectures->pages($term, $scope?->subject);

        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render('App/Prefecture/index.html.twig', [
            'prefectures' => $this->prefectures->browse($term, $page, $scope?->subject),
            'scope' => $scope,
            'term' => $term,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route(path: '/prefecture/{slug}', name: 'app_prefecture_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Prefecture $prefecture): Response
    {
        return $this->render('App/Prefecture/show.html.twig', [
            'prefecture' => $prefecture,
            'cities' => $this->cities->countIn($prefecture),
            'locations' => $this->locations->countIn($prefecture),
            'goshuinchos' => $this->goshuinchos->holding($prefecture),
            'goshuins' => $this->goshuins->from($prefecture),
        ]);
    }

    #[Route(path: '/prefecture/{slug}/edit', name: 'app_prefecture_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Prefecture $prefecture): Response
    {
        $form = $this->createForm(PrefectureType::class, $prefecture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->photograph($request, $prefecture);

            return $this->redirectToRoute('app_prefecture_show', ['slug' => $prefecture->getSlug()]);
        }

        return $this->render('App/Prefecture/edit.html.twig', [
            'form' => $form,
            'prefecture' => $prefecture,
        ]);
    }

    #[Route(path: '/prefecture/{slug}/delete', name: 'app_prefecture_delete', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Prefecture $prefecture): Response
    {
        $held = $this->uses->of($prefecture);
        $form = $this->createDeleteForm('app_prefecture_delete', ['slug' => $prefecture->getSlug()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($held > 0) {
                return $this->redirectToRoute('app_prefecture_show', ['slug' => $prefecture->getSlug()]);
            }

            $this->entityManager->remove($prefecture);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_prefecture_index');
        }

        return $this->render('App/Prefecture/delete.html.twig', [
            'prefecture' => $prefecture,
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

        return null;
    }

    private function photograph(Request $request, Prefecture $prefecture): void
    {
        $this->set->applyTo($prefecture, new PhotoInstructions(
            order: array_values($request->request->all()['photo_order']['prefecture'] ?? []),
            labels: $request->request->all()['photo_label']['prefecture'] ?? [],
            removed: array_values($request->request->all()['photo_remove']['prefecture'] ?? []),
            added: array_values(array_filter($request->files->all()['photo_add']['prefecture'] ?? [])),
            addedLabels: array_values($request->request->all()['photo_add_label']['prefecture'] ?? []),
        ));
    }
}
