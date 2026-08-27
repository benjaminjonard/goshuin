<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Entity\Tag;
use App\Enum\PhotoType;
use App\Form\Type\GoshuinType;
use App\Model\PhotoInstructions;
use App\Repository\GoshuinRepository;
use App\Repository\PhotoRepository;
use App\Repository\TagRepository;
use App\Service\PhotoSet;
use App\Service\Positioner;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GoshuinController extends AbstractController
{
    public function __construct(
        private readonly GoshuinRepository $goshuins,
        private readonly PhotoRepository $photos,
        private readonly TagRepository $tags,
        private readonly Positioner $positioner,
        private readonly PhotoSet $set,
    ) {
    }

    #[Route(path: '/goshuin', name: 'app_goshuin_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$term, $page] = $this->browsing($request);
        $scope = $this->scopeOf($request, [
            'tag' => ['route' => 'app_goshuin_index', 'repository' => $this->tags],
        ]);
        $tag = $scope?->subject instanceof Tag ? $scope->subject : null;
        $pages = $this->goshuins->pages($term, $tag);

        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render('App/Goshuin/index.html.twig', [
            'goshuins' => $this->goshuins->browse($term, $page, $tag),
            'scope' => $scope,
            'term' => $term,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route(path: '/goshuincho/{id}/goshuin/add', name: 'app_goshuin_add', methods: ['GET', 'POST'])]
    public function add(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Goshuincho $goshuincho): Response
    {
        $goshuin = new Goshuin();
        $goshuin->setGoshuincho($goshuincho);

        $form = $this->createForm(GoshuinType::class, $goshuin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->positioner->add($goshuin);
            $this->photograph($request, $goshuin);

            if ($request->request->has('goshuin_again')) {
                return $this->redirectToRoute('app_goshuin_add', ['id' => $goshuin->getGoshuincho()?->getId()]);
            }

            return $this->redirectToRoute('app_goshuincho_show', ['id' => $goshuin->getGoshuincho()?->getId()]);
        }

        return $this->render('App/Goshuin/add.html.twig', [
            'form' => $form,
            'goshuincho' => $goshuincho,
            'photos' => $this->sets($goshuin),
        ]);
    }

    #[Route(path: '/goshuincho/{id}/goshuin/{position}', name: 'app_goshuin_show', requirements: ['position' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(expr: 'repository.findById(id)')] Goshuincho $goshuincho, int $position): Response
    {
        $goshuin = $this->goshuins->atPosition($goshuincho, $position);

        if ($goshuin === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('App/Goshuin/show.html.twig', [
            'goshuincho' => $goshuincho,
            'goshuin' => $goshuin,
            'pages' => $this->goshuins->countIn($goshuincho),
            'photos' => $this->sets($goshuin),
        ]);
    }

    #[Route(path: '/goshuincho/{id}/goshuin/{position}/edit', name: 'app_goshuin_edit', requirements: ['position' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Goshuincho $goshuincho, int $position): Response
    {
        $goshuin = $this->goshuins->atPosition($goshuincho, $position);

        if ($goshuin === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(GoshuinType::class, $goshuin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->photograph($request, $goshuin);

            return $this->redirectToRoute('app_goshuin_show', [
                'id' => $goshuin->getGoshuincho()?->getId(),
                'position' => $goshuin->getPosition(),
            ]);
        }

        return $this->render('App/Goshuin/edit.html.twig', [
            'form' => $form,
            'goshuincho' => $goshuincho,
            'goshuin' => $goshuin,
            'photos' => $this->sets($goshuin),
        ]);
    }

    #[Route(path: '/goshuincho/{id}/goshuin/{position}/delete', name: 'app_goshuin_delete', requirements: ['position' => '\d+'], methods: ['GET', 'POST'])]
    public function delete(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Goshuincho $goshuincho, int $position): Response
    {
        $goshuin = $this->goshuins->atPosition($goshuincho, $position);

        if ($goshuin === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createDeleteForm('app_goshuin_delete', ['id' => $goshuincho->getId(), 'position' => $position]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->positioner->remove($goshuin);

            return $this->redirectToRoute('app_goshuincho_show', ['id' => $goshuincho->getId()]);
        }

        return $this->render('App/Goshuin/delete.html.twig', [
            'form' => $form,
            'goshuincho' => $goshuincho,
            'goshuin' => $goshuin,
        ]);
    }


    /**
     * @return array<string, list<\App\Entity\Photo>>
     */
    private function sets(Goshuin $goshuin): array
    {
        return [
            PhotoType::Location->value => $goshuin->getPosition() === null ? [] : $this->photos->ofType($goshuin, PhotoType::Location),
            PhotoType::Other->value => $goshuin->getPosition() === null ? [] : $this->photos->ofType($goshuin, PhotoType::Other),
        ];
    }

    private function photograph(Request $request, Goshuin $goshuin): void
    {
        foreach (PhotoType::cases() as $type) {
            $this->set->apply($goshuin, $type, new PhotoInstructions(
                order: array_values($request->request->all()['photo_order'][$type->value] ?? []),
                labels: $request->request->all()['photo_label'][$type->value] ?? [],
                removed: array_values($request->request->all()['photo_remove'][$type->value] ?? []),
                added: array_values(array_filter($request->files->all()['photo_add'][$type->value] ?? [])),
                addedLabels: array_values($request->request->all()['photo_add_label'][$type->value] ?? []),
            ));
        }
    }
}
