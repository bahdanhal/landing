<?php

namespace App\Controller;

use App\Exception\UnsafeUrlException;
use App\Service\GeoAnalyzer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GeoController extends AbstractController
{
    public function __construct(
        private readonly GeoAnalyzer $analyzer,
        private readonly RateLimiterFactory $auditLimiter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: ['en' => '/tools/geo-audit', 'pl' => '/pl/narzedzia/audyt-geo'], name: 'geo_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('geo/home.html.twig');
    }

    #[Route(path: ['en' => '/geo-audit', 'pl' => '/pl/audyt-geo'], name: 'geo_audit', methods: ['POST'])]
    public function audit(Request $request): Response
    {
        $limit = $this->auditLimiter->create(($request->getClientIp() ?? 'unknown').'|'.gmdate('Y-m-d'))->consume();
        if (!$limit->isAccepted()) {
            return $this->render('geo/home.html.twig', [
                'url' => trim((string) $request->request->get('url')),
                'error' => $this->translator->trans('audit.limit.message'),
                'limit_exhausted' => true,
            ], new Response(status: 429));
        }

        $url = trim((string) $request->request->get('url'));
        try {
            return $this->render('geo/report.html.twig', [
                'report' => $this->analyzer->analyze($url, $request->request->getBoolean('refresh')),
            ]);
        } catch (UnsafeUrlException|\RuntimeException $exception) {
            return $this->render('geo/home.html.twig', ['url' => $url, 'error' => $exception->getMessage()], new Response(status: 422));
        }
    }
}
