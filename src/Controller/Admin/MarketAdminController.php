<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Infrastructure\JsonPriceTipRepository;
use App\Market\Infrastructure\JsonProductRequestStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MarketAdminController extends AbstractController
{
    private const AUTH_COOKIE_NAME = 'market_admin_auth';

    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly PriceObservationRepository $observations,
        private readonly JsonProductRequestStore $productRequests,
        private readonly JsonPriceTipRepository $priceTips,
        private readonly string $secret,
    ) {
    }

    #[Route('/admin/market', name: 'market_admin_dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->render('admin/login.html.twig');
        }

        $category = $request->query->get('category');
        $search = strtolower(trim((string) $request->query->get('q', '')));

        $allProducts = $this->catalog->all();
        $filtered = array_filter($allProducts, function ($product) use ($category, $search): bool {
            if ($category !== null && $category !== '' && $product->category !== $category) {
                return false;
            }
            if ($search !== '' && !str_contains(strtolower($product->name), $search) && !str_contains($product->slug, $search)) {
                return false;
            }
            return true;
        });

        $productData = array_map(function ($product) {
            $history = $this->observations->history($product->slug);
            $latest = $history[0] ?? null;
            $previous = $history[1] ?? null;

            return [
                'product' => $product,
                'latest' => $latest,
                'previous' => $previous,
                'observation_count' => count($history),
            ];
        }, $filtered);

        return $this->render('admin/market.html.twig', [
            'products' => $productData,
            'all_products' => $allProducts,
            'families' => $this->catalog->families(),
            'requests' => $this->productRequests->all(),
            'price_tips' => $this->priceTips->all(),
            'current_category' => $category,
            'search' => $search,
            'status' => $request->query->get('status'),
            'error' => $request->query->get('error'),
        ]);
    }

    #[Route('/admin/market/login', name: 'market_admin_login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $password = (string) $request->request->get('password', '');
        $adminToken = (string) ($_ENV['ADMIN_TOKEN'] ?? $this->secret);

        if (hash_equals($adminToken, $password) || hash_equals($this->secret, $password)) {
            $response = $this->redirectToRoute('market_admin_dashboard');
            $authHash = hash_hmac('sha256', 'market_admin_authenticated', $this->secret);
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
            'error' => 'Invalid token or passphrase.',
        ]);
    }

    #[Route('/admin/market/logout', name: 'market_admin_logout', methods: ['GET'])]
    public function logout(): Response
    {
        $response = $this->redirectToRoute('market_admin_dashboard');
        $response->headers->clearCookie(self::AUTH_COOKIE_NAME, '/');

        return $response;
    }

    #[Route('/admin/market/observation', name: 'market_admin_save_observation', methods: ['POST'])]
    public function saveObservation(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $slug = trim((string) $request->request->get('product_slug'));
        $dateStr = trim((string) $request->request->get('observed_at'));
        $medianPln = (float) $request->request->get('median_pln');
        $lowPln = (float) $request->request->get('low_pln');
        $highPln = (float) $request->request->get('high_pln');
        $sampleSize = (int) $request->request->get('sample_size', 5);
        $confidence = (string) $request->request->get('confidence', 'high');

        $product = $this->catalog->get($slug);
        if ($product === null) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Unknown product slug: ' . $slug]);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Invalid date format. Expected YYYY-MM-DD.']);
        }

        try {
            $observedAt = new \DateTimeImmutable($dateStr . ' 12:00:00', new \DateTimeZone('Europe/Warsaw'));
            $observation = new PriceObservation(
                $slug,
                $observedAt,
                (int) round($medianPln * 100),
                (int) round($lowPln * 100),
                (int) round($highPln * 100),
                max(3, $sampleSize),
                in_array($confidence, ['low', 'medium', 'high'], true) ? $confidence : 'high',
                '',
                PriceObservation::METHODOLOGY_MANUAL
            );

            $this->observations->save($observation);
        } catch (\Throwable $e) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Failed to save observation: ' . $e->getMessage()]);
        }

        return $this->redirectToRoute('market_admin_dashboard', ['status' => 'Observation saved for ' . $product->name]);
    }

    #[Route('/admin/market/delete-observation', name: 'market_admin_delete_observation', methods: ['POST'])]
    public function deleteObservation(Request $request): Response
    {
        if (!$this->isAuthenticated($request)) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $slug = trim((string) $request->request->get('product_slug'));
        $date = trim((string) $request->request->get('date'));

        try {
            $this->observations->delete($slug, $date);
        } catch (\Throwable $e) {
            return $this->redirectToRoute('market_admin_dashboard', ['error' => 'Failed to delete: ' . $e->getMessage()]);
        }

        return $this->redirectToRoute('market_admin_dashboard', ['status' => 'Deleted observation for ' . $slug . ' (' . $date . ')']);
    }

    private function isAuthenticated(Request $request): bool
    {
        $token = $request->query->get('token') ?? $request->headers->get('X-Admin-Token');
        $adminToken = (string) ($_ENV['ADMIN_TOKEN'] ?? $this->secret);

        if ($token !== null && (hash_equals($adminToken, $token) || hash_equals($this->secret, $token))) {
            return true;
        }

        $cookie = $request->cookies->get(self::AUTH_COOKIE_NAME);
        if ($cookie !== null) {
            $expected = hash_hmac('sha256', 'market_admin_authenticated', $this->secret);
            return hash_equals($expected, $cookie);
        }

        return false;
    }
}
