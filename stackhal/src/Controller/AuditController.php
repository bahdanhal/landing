<?php

declare(strict_types=1);

namespace App\Controller;

use App\Audit\Application\IssueGrouper;
use App\Audit\Application\SiteAuditor;
use App\Crawl\Domain\UnsafeUrlException;
use App\Lead\Application\CaptureLead;
use App\Shared\Application\DailyQuota;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AuditController extends AbstractController
{
    public function __construct(
        private readonly SiteAuditor $auditor,
        private readonly CaptureLead $captureLead,
        private readonly DailyQuota $auditQuota,
        private readonly RateLimiterFactory $contactLimiter,
        private readonly TranslatorInterface $translator,
        private readonly IssueGrouper $issueGrouper,
    ) {
    }

    #[Route(
        path: ['en' => '/seo-audit', 'pl' => '/pl/audyt-seo'],
        name: 'seo_audit_home',
        methods: ['GET']
    )]
    public function home(): Response
    {
        return $this->render('audit/home.html.twig');
    }

    #[Route(
        path: ['en' => '/tools/seo-audit', 'pl' => '/pl/narzedzia/audyt-seo'],
        name: 'legacy_seo_audit_home',
        methods: ['GET']
    )]
    public function legacyHome(Request $request): Response
    {
        return $this->redirectToRoute('seo_audit_home', ['_locale' => $request->getLocale()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(path: ['en' => '/audit', 'pl' => '/pl/audyt'], name: 'audit', methods: ['POST'])]
    public function audit(Request $request): Response
    {
        if (($limited = $this->limitExceeded($request)) !== null) {
            return $limited;
        }
        $url = trim((string) $request->request->get('url'));
        $refresh = $request->request->getBoolean('refresh');
        try {
            $report = $this->auditor->audit($url, $refresh);
            return $this->render('audit/report.html.twig', [
                'report' => $report,
                'issueGroups' => $this->issueGrouper->group($report['issues']),
            ]);
        } catch (UnsafeUrlException | \RuntimeException $exception) {
            return $this->render('audit/home.html.twig', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ], new Response(status: 422));
        }
    }

    #[Route('/api/audit', name: 'api_audit', defaults: ['_locale' => 'en'], methods: ['POST'])]
    public function api(Request $request): Response
    {
        if (($limited = $this->limitExceeded($request, true)) !== null) {
            return $limited;
        }
        $payload = json_decode($request->getContent(), true);
        $url = is_array($payload) ? (string) ($payload['url'] ?? '') : '';
        $refresh = is_array($payload) && filter_var($payload['refresh'] ?? false, FILTER_VALIDATE_BOOL);
        try {
            return $this->json($this->auditor->audit($url, $refresh));
        } catch (UnsafeUrlException | \RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/healthz', name: 'health', defaults: ['_locale' => 'en'], methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }

    #[Route(path: ['en' => '/contact', 'pl' => '/pl/kontakt'], name: 'contact', methods: ['POST'])]
    public function contact(Request $request): JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return $this->json(['error' => $this->translator->trans('contact.invalid_origin')], 403);
        }
        if (trim((string) $request->request->get('company')) !== '') {
            return $this->json(['ok' => true, 'message' => $this->translator->trans('contact.saved')]);
        }

        $limit = $this->contactLimiter->create($this->dailyKey($request))->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => $this->translator->trans('contact.too_many')], 429);
        }

        $email = strtolower(trim((string) $request->request->get('email')));
        $phone = trim((string) $request->request->get('phone'));
        $message = trim((string) $request->request->get('message'));
        if (
            strlen($email) > 254
            || mb_strlen($phone) > 30
            || mb_strlen($message) > 1000
            || ($email === '' && $phone === '')
            || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)
            || ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{7,30}$/', $phone))
        ) {
            return $this->json(['error' => $this->translator->trans('contact.invalid_contact')], 422);
        }

        try {
            $this->captureLead->execute(
                $email,
                $phone,
                $message,
                $request->getClientIp() ?? 'unknown',
                (string) $request->request->get('source', 'website'),
            );
        } catch (\RuntimeException) {
            return $this->json(['error' => $this->translator->trans('contact.failed')], 503);
        }

        return $this->json(['ok' => true, 'message' => $this->translator->trans('contact.saved')]);
    }

    private function limitExceeded(Request $request, bool $json = false): ?Response
    {
        $decision = $this->auditQuota->consume($request->getClientIp() ?? 'unknown');
        if ($decision->accepted) {
            return null;
        }

        $message = $this->translator->trans('audit.limit.message');
        $response = $json
            ? $this->json([
                'error' => $message,
                'resets' => '00:00 UTC',
                'contact' => [
                    'email' => 'bahdan.hal@hotmail.com',
                    'linkedin' => 'https://www.linkedin.com/in/bahdan-hal/',
                    'upwork' => 'https://www.upwork.com/freelancers/~014111a2d384da6af9',
                ],
            ], 429)
            : $this->render('audit/home.html.twig', [
                'error' => $message,
                'limit_exhausted' => true,
            ], new Response(status: 429));
        $response->headers->set('Retry-After', (string) $decision->retryAfterSeconds);

        return $response;
    }

    private function dailyKey(Request $request): string
    {
        return ($request->getClientIp() ?? 'unknown') . '|' . gmdate('Y-m-d');
    }
}
