<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Goshuincho;
use App\Form\Type\GoshuinchoType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/goshuincho')]
class GoshuinchoController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
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

            return $this->redirectToRoute('app_goshuincho_show', ['slug' => $goshuincho->getSlug()]);
        }

        return $this->render('App/Goshuincho/add.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/{slug}', name: 'app_goshuincho_show', methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Goshuincho $goshuincho): Response
    {
        return $this->render('App/Goshuincho/show.html.twig', [
            'goshuincho' => $goshuincho,
        ]);
    }

    #[Route(path: '/{slug}/edit', name: 'app_goshuincho_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Goshuincho $goshuincho): Response
    {
        $form = $this->createForm(GoshuinchoType::class, $goshuincho);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_goshuincho_show', ['slug' => $goshuincho->getSlug()]);
        }

        return $this->render('App/Goshuincho/edit.html.twig', [
            'form' => $form,
            'goshuincho' => $goshuincho,
        ]);
    }

    #[Route(path: '/{slug}/delete', name: 'app_goshuincho_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Goshuincho $goshuincho): Response
    {
        $form = $this->createDeleteForm('app_goshuincho_delete', ['slug' => $goshuincho->getSlug()]);
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
