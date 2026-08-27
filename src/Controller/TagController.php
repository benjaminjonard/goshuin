<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tag;
use App\Form\Type\TagType;
use App\Repository\GoshuinRepository;
use App\Repository\TagRepository;
use App\Service\Uses;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TagController extends AbstractController
{
    public function __construct(
        private readonly TagRepository $tags,
        private readonly GoshuinRepository $goshuins,
        private readonly Uses $uses,
    ) {
    }

    #[Route(path: '/tags', name: 'app_tag_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$term, $page] = $this->browsing($request);
        $pages = $this->tags->pages($term);

        if ($page > $pages) {
            throw $this->createNotFoundException();
        }

        $tags = $this->tags->browse($term, $page);

        return $this->render('App/Tag/index.html.twig', [
            'tags' => $tags,
            'counts' => $this->goshuins->countPerTag($tags),
            'term' => $term,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    #[Route(path: '/tag/{id}/edit', name: 'app_tag_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Tag $tag): Response
    {
        $form = $this->createForm(TagType::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_tag_index');
        }

        return $this->render('App/Tag/edit.html.twig', [
            'form' => $form,
            'tag' => $tag,
        ]);
    }

    #[Route(path: '/tag/{id}/delete', name: 'app_tag_delete', methods: ['GET', 'POST'])]
    public function delete(Request $request, #[MapEntity(expr: 'repository.findById(id)')] Tag $tag): Response
    {
        return $this->deleteSubject($request, $tag, 'tag', $this->uses->of($tag), 'app_tag_delete');
    }
}
