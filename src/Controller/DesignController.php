<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[When('dev')]
class DesignController extends AbstractController
{
    #[Route(path: '/_design', name: 'app_design', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('App/Design/index.html.twig');
    }
}
