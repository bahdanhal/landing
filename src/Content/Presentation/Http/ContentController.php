<?php

declare(strict_types=1);

namespace App\Content\Presentation\Http;

use App\Content\Application\EditContent;
use App\Content\Domain\ContentValidationException;
use App\Content\Infrastructure\ContentAdminAccess;
use App\Content\Infrastructure\ContentConflictException;
use App\Content\Infrastructure\ContentDefaults;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContentController extends AbstractController
{
    private const CSRF_ID = 'site_content';

    public function __construct(
        private readonly ContentAdminAccess $access,
        private readonly ContentDefaults $defaults,
        private readonly EditContent $editor,
    ) {
    }

    #[Route('/admin/content', name: 'portfolio_admin_content', methods: ['GET'])]
    public function edit(Request $request): Response
    {
        if (!$this->access->isAuthenticated($request)) {
            return $this->redirectToRoute('portfolio_admin_dashboard');
        }
        $preview = [];
        foreach (['landing' => 'Home', 'resume' => 'Résumé', 'services' => 'Services'] as $route => $label) {
            foreach (['en', 'pl'] as $locale) {
                $preview[] = [
                    'label' => $label . ' · ' . strtoupper($locale),
                    'url' => $this->generateUrl($route, ['_locale' => $locale, '_content_preview' => '1']),
                ];
            }
        }

        return $this->render('admin/content.html.twig', [
            'boot' => [
                'schema' => EditorSchema::build($this->defaults->texts()),
                'textDefaults' => $this->defaults->texts(),
                'state' => $this->editor->state(),
                'preview' => $preview,
                'endpoint' => $this->generateUrl('portfolio_admin_content_save'),
                'csrf' => $this->access->csrfToken(self::CSRF_ID),
            ],
        ]);
    }

    #[Route('/admin/content', name: 'portfolio_admin_content_save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        if (!$this->access->isAuthenticated($request)) {
            return $this->json(['error' => 'Your session has ended. Sign in again in another tab, then save.'], 401);
        }
        if (!$this->access->validCsrf(self::CSRF_ID, (string) $request->headers->get('X-Csrf-Token', ''))) {
            return $this->json(['error' => 'The editor has expired. Copy unsaved text, then reload the page.'], 403);
        }
        try {
            $payload = json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->json(['error' => 'The editor sent an unreadable request.'], 400);
        }
        if (!is_array($payload)) {
            return $this->json(['error' => 'The editor sent an unreadable request.'], 400);
        }
        $document = is_array($payload['document'] ?? null) ? $payload['document'] : [];
        $revision = is_int($payload['revision'] ?? null) ? $payload['revision'] : -1;
        try {
            $state = $this->editor->apply((string) ($payload['action'] ?? ''), $document, $revision);
        } catch (ContentConflictException $exception) {
            return $this->json(['error' => $exception->getMessage(), 'conflict' => true], 409);
        } catch (ContentValidationException | \InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }

        return $this->json(['state' => $state]);
    }
}
