<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * No listener guards the first run: every private route already redirects here,
     * and here is where zero Users sends the visitor on to setup. One query on one
     * route beats a count on every request (FR-1).
     */
    #[Route(path: '/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, UserRepository $userRepository): Response
    {
        if ($userRepository->hasNone()) {
            return $this->redirectToRoute('app_setup');
        }

        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_homepage');
        }

        return $this->render('App/Security/login.html.twig', [
            'lastEmail' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
    }
}
