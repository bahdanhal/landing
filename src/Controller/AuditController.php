<?php

namespace App\Controller;

use App\Exception\UnsafeUrlException;
use App\Service\ContactLeadStore;
use App\Service\SiteAuditor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class AuditController extends AbstractController
{
    public function __construct(
        private readonly SiteAuditor $auditor,
        private readonly ContactLeadStore $contactLeadStore,
        private readonly RateLimiterFactory $auditLimiter,
        private readonly RateLimiterFactory $contactLimiter,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('audit/home.html.twig');
    }

    #[Route('/audit', name: 'audit', methods: ['POST'])]
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
                'issueGroups' => $this->groupIssues($report['issues']),
            ]);
        } catch (UnsafeUrlException|\RuntimeException $exception) {
            return $this->render('audit/home.html.twig', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ], new Response(status: 422));
        }
    }

    #[Route('/api/audit', name: 'api_audit', methods: ['POST'])]
    public function api(Request $request): JsonResponse
    {
        if (($limited = $this->limitExceeded($request, true)) !== null) {
            return $limited;
        }
        $payload = json_decode($request->getContent(), true);
        $url = is_array($payload) ? (string) ($payload['url'] ?? '') : '';
        $refresh = is_array($payload) && filter_var($payload['refresh'] ?? false, FILTER_VALIDATE_BOOL);
        try {
            return $this->json($this->auditor->audit($url, $refresh));
        } catch (UnsafeUrlException|\RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }
    }

    #[Route('/healthz', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }

    #[Route('/contact', name: 'contact', methods: ['POST'])]
    public function contact(Request $request): JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return $this->json(['error' => 'Invalid form origin.'], 403);
        }
        if (trim((string) $request->request->get('company')) !== '') {
            return $this->json(['ok' => true, 'message' => 'Thanks — your request has been saved.']);
        }

        $limit = $this->contactLimiter->create($this->dailyKey($request))->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => 'Too many contact requests today. Please use a direct contact link instead.'], 429);
        }

        $email = strtolower(trim((string) $request->request->get('email')));
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->json(['error' => 'Please enter a valid email address.'], 422);
        }

        try {
            $this->contactLeadStore->store(
                $email,
                $request->getClientIp() ?? 'unknown',
                (string) $request->request->get('source', 'website'),
            );
        } catch (\RuntimeException) {
            return $this->json(['error' => 'Could not save your email right now. Please use a direct contact link.'], 503);
        }

        return $this->json(['ok' => true, 'message' => 'Thanks — your email is saved. Bahdan will follow up.']);
    }

    private function limitExceeded(Request $request, bool $json = false): ?Response
    {
        $limit = $this->auditLimiter->create($this->dailyKey($request))->consume();
        if ($limit->isAccepted()) {
            return null;
        }

        $retryAfter = max(1, (new \DateTimeImmutable('tomorrow', new \DateTimeZone('UTC')))->getTimestamp() - time());
        $message = 'You have used today’s 10 free audits. Leave your email or contact Bahdan directly for help.';
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
        $response->headers->set('Retry-After', (string) $retryAfter);

        return $response;
    }

    private function dailyKey(Request $request): string
    {
        return ($request->getClientIp() ?? 'unknown').'|'.gmdate('Y-m-d');
    }

    private function groupIssues(array $issues): array
    {
        $groups = [];
        foreach ($issues as $issue) {
            $key = $issue['severity'].'|'.$issue['code'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'severity' => $issue['severity'],
                    'code' => $issue['code'],
                    'title' => $issue['title'],
                    'occurrences' => [],
                ];
            }
            $groups[$key]['occurrences'][] = [
                'detail' => $issue['detail'],
                'evidence' => $issue['evidence'] ?? [],
            ];
        }

        return array_values($groups);
    }
}
