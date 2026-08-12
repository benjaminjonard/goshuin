<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Enum\PhotoType;
use App\Form\Type\GoshuinType;
use App\Repository\GoshuinRepository;
use App\Repository\PhotoRepository;
use App\Service\PhotoInstructions;
use App\Service\PhotoSet;
use App\Service\Positioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/goshuincho/{slug}/goshuin')]
class GoshuinController extends AbstractController
{
    public function __construct(
        private readonly GoshuinRepository $goshuins,
        private readonly PhotoRepository $photos,
        private readonly Positioner $positioner,
        private readonly PhotoSet $set,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/add', name: 'app_goshuin_add', methods: ['GET', 'POST'])]
    public function add(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Goshuincho $goshuincho): Response
    {
        $goshuin = new Goshuin();
        $goshuin->setGoshuincho($goshuincho);

        $form = $this->createForm(GoshuinType::class, $goshuin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->positioner->add($goshuin);
            $this->photograph($request, $goshuin);

            if ($request->request->has('goshuin_again')) {
                $this->addFlash('success', [
                    'key' => 'message.goshuin_added',
                    'parameters' => ['%location%' => $goshuin->getLocation()?->getRomanizedName()],
                ]);

                return $this->redirectToRoute('app_goshuin_add', ['slug' => $goshuin->getGoshuincho()?->getSlug()]);
            }

            return $this->redirectToRoute('app_goshuincho_show', ['slug' => $goshuin->getGoshuincho()?->getSlug()]);
        }

        return $this->render('App/Goshuin/add.html.twig', [
            'form' => $form,
            'goshuincho' => $goshuincho,
            'photos' => $this->sets($goshuin),
        ]);
    }

    #[Route(path: '/{position}', name: 'app_goshuin_show', requirements: ['position' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Goshuincho $goshuincho, int $position): Response
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

    #[Route(path: '/{position}/edit', name: 'app_goshuin_edit', requirements: ['position' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Goshuincho $goshuincho, int $position): Response
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
                'slug' => $goshuin->getGoshuincho()?->getSlug(),
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

    #[Route(path: '/{position}/delete', name: 'app_goshuin_delete', requirements: ['position' => '\d+'], methods: ['GET', 'POST'])]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Goshuincho $goshuincho, int $position): Response
    {
        $goshuin = $this->goshuins->atPosition($goshuincho, $position);

        if ($goshuin === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createDeleteForm('app_goshuin_delete', ['slug' => $goshuincho->getSlug(), 'position' => $position]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->positioner->remove($goshuin);

            return $this->redirectToRoute('app_goshuincho_show', ['slug' => $goshuincho->getSlug()]);
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
        $refused = 0;

        foreach (PhotoType::cases() as $type) {
            $refused += $this->set->apply($goshuin, $type, new PhotoInstructions(
                order: array_values($request->request->all()['photo_order'][$type->value] ?? []),
                labels: $request->request->all()['photo_label'][$type->value] ?? [],
                removed: array_values($request->request->all()['photo_remove'][$type->value] ?? []),
                added: array_values(array_filter($request->files->all()['photo_add'][$type->value] ?? [])),
                addedLabels: array_values($request->request->all()['photo_add_label'][$type->value] ?? []),
            ));
        }

        if ($refused > 0) {
            $this->addFlash('warning', ['key' => 'message.photos_refused', 'parameters' => ['%count%' => $refused]]);
        }
    }
}
