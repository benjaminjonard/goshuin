<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Form\Type\GoshuinchoType;
use App\Repository\GoshuinchoRepository;
use App\Service\Positioner;
use App\Service\Trip;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/goshuincho')]
class GoshuinchoController extends AbstractController
{
    public function __construct(
        private readonly GoshuinchoRepository $goshuinchos,
        private readonly Positioner $positioner,
        private readonly Trip $trip,
    ) {
    }

    #[Route(path: '', name: 'app_goshuincho_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$term, $page] = $this->browsing($request);
        $pages = $this->goshuinchos->pages($term);

        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render('App/Goshuincho/index.html.twig', [
            'goshuinchos' => $this->goshuinchos->browse($term, $page),
            'term' => $term,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route(path: '/add', name: 'app_goshuincho_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $goshuincho = new Goshuincho();
        $form = $this->createForm(GoshuinchoType::class, $goshuincho, ['with_hue' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($goshuincho);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_goshuincho_show', ['id' => $goshuincho->getId()]);
        }

        return $this->render('App/Goshuincho/add.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id}', name: 'app_goshuincho_show', methods: ['GET'])]
    public function show(Request $request, string $id): Response
    {
        $goshuincho = $this->goshuinchos->withGoshuins($id);
        if ($goshuincho === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('App/Goshuincho/show.html.twig', [
            'goshuincho' => $goshuincho,
            'summary' => $this->goshuinchos->summary($goshuincho, $request->getLocale()),
            'days' => $this->trip->days($goshuincho->getGoshuins()),
            'pinned' => array_values(array_filter(
                iterator_to_array($goshuincho->getGoshuins()),
                static fn (Goshuin $goshuin): bool => $goshuin->getLocation()?->hasCoordinates() === true,
            )),
        ]);
    }

    #[Route(path: '/{id}/edit', name: 'app_goshuincho_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Goshuincho $goshuincho): Response
    {
        $form = $this->createForm(GoshuinchoType::class, $goshuincho);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $order = array_values($request->request->all()['goshuin_order'] ?? []);

            if ($order !== []) {
                $this->positioner->order($goshuincho, $order);
            }

            return $this->redirectToRoute('app_goshuincho_show', ['id' => $goshuincho->getId()]);
        }

        return $this->render('App/Goshuincho/edit.html.twig', [
            'form' => $form,
            'goshuincho' => $goshuincho,
        ]);
    }

    #[Route(path: '/{id}/delete', name: 'app_goshuincho_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Goshuincho $goshuincho): Response
    {
        $form = $this->createDeleteForm('app_goshuincho_delete', ['id' => $goshuincho->getId()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($goshuincho);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_homepage');
        }

        return $this->render('App/Goshuincho/delete.html.twig', [
            'goshuincho' => $goshuincho,
            'form' => $form,
        ]);
    }
}
