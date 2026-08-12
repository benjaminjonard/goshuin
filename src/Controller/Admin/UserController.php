<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Type\PasswordSetType;
use App\Form\Type\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route(path: '/admin/users')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('App/Admin/User/index.html.twig', [
            'users' => $this->users->findBy([], ['createdAt' => 'ASC']),
        ]);
    }

    #[Route(path: '/add', name: 'app_admin_user_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['require_password' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('App/Admin/User/add.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $password = $this->createForm(PasswordSetType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && !$this->stranded($form, $user)) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_admin_user_index');
        }

        $password->handleRequest($request);
        if ($password->isSubmitted() && $password->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('App/Admin/User/edit.html.twig', [
            'form' => $form,
            'password' => $password,
            'user' => $user,
        ]);
    }

    #[Route(path: '/{id}/delete', name: 'app_admin_user_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, User $user): Response
    {
        $refused = $this->users->countAdministrators($user->getId()) === 0;
        $form = $this->createDeleteForm('app_admin_user_delete', ['id' => $user->getId()]);
        $form->handleRequest($request);

        if (!$refused && $form->isSubmitted() && $form->isValid()) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('App/Admin/User/delete.html.twig', [
            'user' => $user,
            'form' => $form,
            'refused' => $refused,
        ], new Response(status: $refused && $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function stranded(FormInterface $form, User $user): bool
    {
        $remaining = $this->users->countAdministrators($user->getId())
            + (($user->isAdmin() && $user->isEnabled()) ? 1 : 0);

        if ($remaining > 0) {
            return false;
        }

        $form->addError(new FormError($this->translator->trans('error.admin_last')));

        return true;
    }
}
