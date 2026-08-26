<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Bahdan\LeadCaptureBundle\Domain\Lead;
use Bahdan\LeadCaptureBundle\Domain\LeadRepository;
use Bahdan\PrivacyAnalyticsBundle\Application\TrafficAnalytics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PortfolioAdminController extends AbstractController
{
    private const AUTH_COOKIE_NAME = 'portfolio_admin_auth';

    public function __construct(
        private readonly LeadRepository $leadRepository,
        private readonly TrafficAnalytics $trafficAnalytics,
        private readonly string $secret,
    ) {
    }

    #[Route('/admin', name: 'portfolio_admin_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->render('admin/login.html.twig');
        }

        $search = strtolower(trim((string) $request->query->get('q', '')));
        $allLeads = $this->leadRepository->all();

        $leads = array_values(array_filter($allLeads, function (Lead $lead) use ($search): bool {
            if ($search === '') {
                return true;
            }

            return str_contains(strtolower($lead->email), $search)
                || str_contains(strtolower($lead->phone), $search)
                || str_contains(strtolower($lead->message), $search)
                || str_contains(strtolower($lead->source), $search);
        }));

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $sevenDaysAgo = $now->modify('-7 days');
        $thirtyDaysAgo = $now->modify('-30 days');

        $traffic = $this->trafficAnalytics->summary($now);
        $trafficPeak = max([1, ...array_column($traffic['daily'], 'page_views')]);
        $traffic['daily'] = array_map(static fn (array $day): array => [
            ...$day,
            'height_percent' => $day['page_views'] === 0
                ? 2
                : max(8, (int) round(($day['page_views'] / $trafficPeak) * 100)),
        ], $traffic['daily']);

        return $this->render('admin/dashboard.html.twig', [
            'leads' => $leads,
            'total_leads' => count($allLeads),
            'leads_last_7_days' => count(array_filter(
                $allLeads,
                static fn (Lead $lead): bool => $lead->createdAt >= $sevenDaysAgo
            )),
            'leads_last_30_days' => count(array_filter(
                $allLeads,
                static fn (Lead $lead): bool => $lead->createdAt >= $thirtyDaysAgo
            )),
            'traffic' => $traffic,
            'search' => $search,
        ]);
    }

    #[Route('/admin/login', name: 'portfolio_admin_login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $password = trim((string) $request->request->get('password', ''));
        $adminToken = trim((string) ($_ENV['ADMIN_TOKEN'] ?? ($_ENV['MARKET_ADMIN_TOKEN'] ?? $this->secret)));
        $marketAdminToken = trim((string) ($_ENV['MARKET_ADMIN_TOKEN'] ?? ''));
        $secret = trim($this->secret);

        if (
            !$this->isHeaderAuthenticated($request)
            && !$this->isCsrfTokenValid('portfolio_admin_login', (string) $request->request->get('_token'))
        ) {
            return $this->render('admin/login.html.twig', [
                'error' => 'Invalid or expired CSRF token.',
            ]);
        }

        if (
            $password !== ''
            && (
                ($adminToken !== '' && hash_equals($adminToken, $password))
                || ($secret !== '' && hash_equals($secret, $password))
                || ($marketAdminToken !== '' && hash_equals($marketAdminToken, $password))
            )
        ) {
            $response = $this->redirectToRoute('portfolio_admin_dashboard');
            $authHash = hash_hmac('sha256', 'portfolio_admin_authenticated', $this->secret);
            $response->headers->setCookie(
                Cookie::create(
                    self::AUTH_COOKIE_NAME,
                    $authHash,
                    time() + (86400 * 30),
                    '/',
                    null,
                    $request->isSecure(),
                    true,
                    false,
                    Cookie::SAMESITE_LAX
                )
            );

            return $response;
        }

        return $this->render('admin/login.html.twig', [
            'error' => 'Invalid admin token or passphrase.',
        ]);
    }

    #[Route('/admin/logout', name: 'portfolio_admin_logout', methods: ['GET'])]
    public function logout(): Response
    {
        $response = $this->redirectToRoute('portfolio_admin_dashboard');
        $response->headers->clearCookie(self::AUTH_COOKIE_NAME, '/');

        return $response;
    }

    private function isHeaderAuthenticated(Request $request): bool
    {
        $token = $request->headers->get('X-Admin-Token');
        if ($token === null || $token === '') {
            $authHeader = (string) $request->headers->get('Authorization', '');
            if (preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }

        if ($token === null || trim($token) === '') {
            return false;
        }

        $cleanToken = trim($token);
        $adminToken = trim((string) ($_ENV['ADMIN_TOKEN'] ?? ($_ENV['MARKET_ADMIN_TOKEN'] ?? $this->secret)));
        $marketAdminToken = trim((string) ($_ENV['MARKET_ADMIN_TOKEN'] ?? ''));
        $secret = trim($this->secret);

        return ($adminToken !== '' && hash_equals($adminToken, $cleanToken))
            || ($secret !== '' && hash_equals($secret, $cleanToken))
            || ($marketAdminToken !== '' && hash_equals($marketAdminToken, $cleanToken));
    }

    private function isAuthenticated(Request $request): bool
    {
        if ($this->isHeaderAuthenticated($request)) {
            return true;
        }

        $cookie = (string) $request->cookies->get(self::AUTH_COOKIE_NAME, '');
        if ($cookie !== '' && trim($this->secret) !== '') {
            $expected = hash_hmac('sha256', 'portfolio_admin_authenticated', $this->secret);
            return hash_equals($expected, $cookie);
        }

        return false;
    }

    protected function isCsrfTokenValid(string $id, #[\SensitiveParameter] ?string $token): bool
    {
        if ($token === null || $token === '' || trim($this->secret) === '') {
            return false;
        }

        $expected = hash_hmac('sha256', 'csrf:' . $id, $this->secret);

        return hash_equals($expected, $token);
    }
}
