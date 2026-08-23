<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Analytics\Application\TrafficAnalytics;
use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;
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
        $trafficPeak = max(1, ...array_column($traffic['daily'], 'page_views'));
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
        $password = (string) $request->request->get('password', '');
        $adminToken = (string) ($_ENV['ADMIN_TOKEN'] ?? ($_ENV['MARKET_ADMIN_TOKEN'] ?? $this->secret));

        if (
            hash_equals($adminToken, $password)
            || hash_equals($this->secret, $password)
            || (isset($_ENV['MARKET_ADMIN_TOKEN']) && hash_equals((string) $_ENV['MARKET_ADMIN_TOKEN'], $password))
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

    private function isAuthenticated(Request $request): bool
    {
        $token = $request->headers->get('X-Admin-Token');
        if ($token === null || $token === '') {
            $authHeader = (string) $request->headers->get('Authorization', '');
            if (preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }

        $adminToken = (string) ($_ENV['ADMIN_TOKEN'] ?? ($_ENV['MARKET_ADMIN_TOKEN'] ?? $this->secret));

        if (
            $token !== null
            && $token !== ''
            && (hash_equals($adminToken, $token)
                || hash_equals($this->secret, $token)
                || (isset($_ENV['MARKET_ADMIN_TOKEN']) && hash_equals((string) $_ENV['MARKET_ADMIN_TOKEN'], $token)))
        ) {
            return true;
        }

        $cookie = $request->cookies->get(self::AUTH_COOKIE_NAME);
        if ($cookie !== null) {
            $expected = hash_hmac('sha256', 'portfolio_admin_authenticated', $this->secret);
            return hash_equals($expected, $cookie);
        }

        return false;
    }
}
