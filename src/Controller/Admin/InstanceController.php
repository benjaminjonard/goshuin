<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Service\DiskUsage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class InstanceController extends AbstractController
{
    #[Route(path: '/admin', name: 'app_admin_instance', methods: ['GET'])]
    public function index(
        DiskUsage $diskUsage,
        #[Autowire('%app.release%')] string $release,
        #[Autowire('%app.uploads_dir%')] string $uploadsDir,
    ): Response {
        return $this->render('App/Admin/Instance/index.html.twig', [
            'release' => $release,
            'phpVersion' => \PHP_VERSION,
            'symfonyVersion' => Kernel::VERSION,
            'frankenPhpVersion' => \extension_loaded('frankenphp') ? ltrim(phpversion('frankenphp'), 'v') : null,
            'diskUsage' => $diskUsage->of($uploadsDir),
        ]);
    }
}
