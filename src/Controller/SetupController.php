<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\Type\SetupType;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SetupController extends AbstractController
{
    #[Route(path: '/setup', name: 'app_setup', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UserRepository $userRepository,
        ManagerRegistry $managerRegistry,
        Security $security,
    ): Response {
        if (!$userRepository->hasNone()) {
            throw $this->createNotFoundException();
        }

        $user = new User();
        $form = $this->createForm(SetupType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles(['ROLE_ADMIN']);
            $managerRegistry->getManager()->persist($user);
            $managerRegistry->getManager()->flush();

            $security->login($user, 'form_login');

            return $this->redirectToRoute('app_homepage');
        }

        return $this->render('App/Setup/index.html.twig', [
            'form' => $form,
        ]);
    }
}
