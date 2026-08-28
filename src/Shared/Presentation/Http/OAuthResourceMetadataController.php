<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class OAuthResourceMetadataController extends AbstractController
{
    #[Route(
        path: '/.well-known/oauth-protected-resource{path}',
        name: 'oauth_protected_resource_metadata',
        requirements: ['path' => '.*'],
        methods: ['GET']
    )]
    public function metadata(Request $request): JsonResponse
    {
        return $this->json([
            'resource' => $request->getSchemeAndHttpHost() . '/mcp',
            'authorization_servers' => [],
        ]);
    }
}
