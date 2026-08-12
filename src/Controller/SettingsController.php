<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\Theme;
use App\Form\Type\AccountType;
use App\Form\Type\PasswordChangeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

class SettingsController extends AbstractController
{
    #[Route(path: '/settings', name: 'app_settings', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user,
    ): Response {
        $account = $this->createForm(AccountType::class, $user);
        $password = $this->createForm(PasswordChangeType::class, $user);

        $account->handleRequest($request);
        if ($account->isSubmitted() && $account->isValid()) {
            $entityManager->flush();
            $request->getSession()->set('_locale', $user->getLocale());

            return $this->redirectToRoute('app_settings');
        }

        $password->handleRequest($request);
        if ($password->isSubmitted() && $password->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('App/Settings/index.html.twig', [
            'account' => $account,
            'password' => $password,
        ]);
    }

    #[Route(path: '/settings/theme', name: 'app_theme', methods: ['POST'])]
    #[IsCsrfTokenValid('submit', tokenKey: '_token')]
    public function theme(
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user,
    ): Response {
        $theme = Theme::tryFrom((string) $request->request->get('theme'));

        if ($theme === null) {
            throw $this->createNotFoundException();
        }

        $user->setTheme($theme);
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
