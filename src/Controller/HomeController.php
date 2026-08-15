<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\GoshuinRepository;
use App\Repository\GoshuinchoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly GoshuinchoRepository $goshuinchos,
        private readonly GoshuinRepository $goshuins,
    ) {
    }

    #[Route(path: '/', name: 'app_homepage', methods: ['GET'])]
    public function index(): Response
    {
        $pins = $this->goshuins->pins();

        return $this->render('App/Home/index.html.twig', [
            'shelf' => $this->goshuinchos->shelf(),
            'pins' => $pins,
            'placed' => count($pins),
            'tally' => $this->goshuinchos->tally(),
            'recent' => $this->goshuins->recent(),
        ]);
    }
}
