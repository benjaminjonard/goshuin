<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Goshuin;
use App\Entity\Goshuincho;
use App\Form\Type\GoshuinType;
use App\Service\Positioner;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/goshuincho/{slug}/goshuin')]
class GoshuinController extends AbstractController
{
    public function __construct(
        private readonly Positioner $positioner,
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

            if ($goshuin->isReceivedInTheFuture()) {
                $this->addFlash('warning', 'message.received_in_the_future');
            }

            return $this->redirectToRoute('app_goshuincho_show', ['slug' => $goshuin->getGoshuincho()?->getSlug()]);
        }

        return $this->render('App/Goshuin/add.html.twig', [
            'form' => $form,
            'goshuincho' => $goshuincho,
        ]);
    }
}
