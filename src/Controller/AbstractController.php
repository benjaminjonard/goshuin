<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractController extends SymfonyAbstractController
{
    protected function createDeleteForm(string $route, array $parameters = []): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl($route, $parameters))
            ->setMethod(Request::METHOD_POST)
            ->getForm()
        ;
    }
}
