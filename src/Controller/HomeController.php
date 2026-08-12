<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route(path: '/', name: 'app_homepage', methods: ['GET'])]
    public function index(): Response
    {
        // The populated Home screen arrives in Epic 4. Until a Goshuincho can exist
        // there is only the stated empty state, a screen in its own right (FR-10).
        return $this->render('App/Home/index.html.twig');
    }
}
