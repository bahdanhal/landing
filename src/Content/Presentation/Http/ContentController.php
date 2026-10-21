<?php

declare(strict_types=1);

namespace App\Content\Presentation\Http;

use App\Content\Application\ContentCatalogue;
use App\Content\Application\EditContent;
use App\Content\Infrastructure\ContentAdminAccess;
use App\Content\Infrastructure\ContentStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class ContentController extends AbstractController
{
    public function __construct(
        private readonly ContentAdminAccess $access,
        private readonly ContentCatalogue $catalogue,
        private readonly ContentStore $store,
        private readonly EditContent $editor,
    ) {
    }

    #[Route('/admin/content/{locale}/{section}', name: 'portfolio_admin_content', defaults: ['locale' => 'en', 'section' => 'home'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $locale, string $section): Response
    {
        if (!$this->access->isAuthenticated($request)) {
            return $this->redirectToRoute('portfolio_admin_dashboard');
        }
        if (!in_array($locale, ['en', 'pl'], true) || !isset($this->catalogue->sections()[$section])) {
            throw $this->createNotFoundException();
        }
        $error = null;
        $status = 200;
        $submitted = null;
        if ($request->isMethod('POST')) {
            $submitted = $request->request->all('fields');
            if (!$this->access->validCsrf('content_' . $locale, (string) $request->request->get('_token', ''))) {
                throw new AccessDeniedHttpException('The form has expired. Reload the editor and try again.');
            }
            try {
                $action = (string) $request->request->get('action', 'draft');
                $this->editor->save($locale, $section, $submitted, $request->request->getInt('revision', -1), $action);

                return $this->redirectToRoute('portfolio_admin_content', [
                    'locale' => $locale, 'section' => $section, 'saved' => $action,
                ], Response::HTTP_SEE_OTHER);
            } catch (\DomainException | \InvalidArgumentException $exception) {
                $error = $exception->getMessage();
                $status = $exception instanceof \DomainException ? 409 : 422;
            }
        }
        $state = $this->store->read($locale);
        $fields = [];
        foreach ($this->catalogue->fields($locale, $section) as $key => $default) {
            $draft = $state['draft'][$key] ?? $default;
            $published = $state['published'][$key] ?? $default;
            $value = is_array($submitted) && isset($submitted[$key]) && is_string($submitted[$key]) ? $submitted[$key] : $draft;
            $fields[] = [
                'key' => $key, 'label' => $this->catalogue->label($key, $section), 'value' => $value,
                'default' => $default, 'pending' => $draft !== $published, 'link' => str_starts_with($key, 'links.'),
            ];
        }
        $response = new Response(status: $status);
        $preview = $this->generateUrl($this->catalogue->sections()[$section]['page'], ['_locale' => $locale, '_content_preview' => '1']);
        if ($section === 'obd') {
            $preview .= '#obd';
        }

        return $this->render('admin/content.html.twig', [
            'sections' => $this->catalogue->sections(), 'section' => $section, 'locale' => $locale,
            'fields' => $fields, 'revision' => $state['revision'], 'error' => $error,
            'conflict' => $status === 409, 'saved' => $request->query->get('saved'), 'preview' => $preview,
        ], $response);
    }
}
