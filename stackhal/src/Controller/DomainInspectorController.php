<?php

declare(strict_types=1);

namespace App\Controller;

use App\DomainInspector\Application\DomainInspector;
use App\Shared\Application\DailyQuota;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DomainInspectorController extends AbstractController
{
    public function __construct(
        private readonly DomainInspector $inspector,
        private readonly DailyQuota $auditQuota,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: ['en' => '/domain-inspector', 'pl' => '/pl/inspektor-domen'],
        name: 'domain_inspector_home',
        methods: ['GET']
    )]
    public function home(): Response
    {
        return $this->render('domain_inspector/home.html.twig');
    }

    #[Route(
        path: ['en' => '/tools/domain-inspector', 'pl' => '/pl/narzedzia/inspektor-domen'],
        name: 'legacy_domain_inspector_home',
        methods: ['GET']
    )]
    public function legacyHome(Request $request): Response
    {
        return $this->redirectToRoute('domain_inspector_home', ['_locale' => $request->getLocale()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        path: ['en' => '/domain-inspector', 'pl' => '/pl/inspektor-domen'],
        name: 'domain_inspector_check',
        methods: ['POST']
    )]
    public function inspect(Request $request): Response
    {
        $domain = trim((string) $request->request->get('domain'));
        $isJson = $request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json');

        $decision = $this->auditQuota->consume($request->getClientIp() ?? 'unknown');
        if (!$decision->accepted) {
            $message = $this->translator->trans('audit.limit.message');
            if ($isJson) {
                return new JsonResponse(
                    ['status' => 'error', 'error' => $message],
                    status: 429,
                    headers: ['Retry-After' => (string) $decision->retryAfterSeconds]
                );
            }

            return $this->render('domain_inspector/home.html.twig', [
                'domain' => $domain,
                'error' => $message,
                'limit_exhausted' => true,
            ], new Response(status: 429, headers: ['Retry-After' => (string) $decision->retryAfterSeconds]));
        }

        if ($isJson) {
            try {
                $report = $this->inspector->inspect($domain);

                return new JsonResponse(['status' => 'completed', 'report' => $report->toArray()]);
            } catch (\InvalidArgumentException | \RuntimeException $exception) {
                return new JsonResponse(['status' => 'error', 'error' => $exception->getMessage()], status: 422);
            }
        }

        try {
            $report = $this->inspector->inspect($domain);

            return $this->render('domain_inspector/report.html.twig', [
                'report' => $report,
            ]);
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            return $this->render('domain_inspector/home.html.twig', [
                'domain' => $domain,
                'error' => $exception->getMessage(),
            ], new Response(status: 422));
        }
    }
}
