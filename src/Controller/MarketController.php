<?php

declare(strict_types=1);

namespace App\Controller;

use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Infrastructure\JsonProductRequestStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MarketController extends AbstractController
{
    public function __construct(
        private readonly ProductCatalog $catalog,
        private readonly PriceObservationRepository $observations,
        private readonly JsonProductRequestStore $productRequests,
        private readonly RateLimiterFactory $productRequestLimiter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: ['en' => '/tools/poland-used-price-index', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych'],
        name: 'market_home',
        methods: ['GET']
    )]
    public function home(): Response
    {
        $families = array_map(fn ($family) => [
            'family' => $family,
            'configurations' => array_map(fn ($product) => [
                'product' => $product,
                'latest' => $this->observations->latest($product->slug),
            ], $family->configurations),
        ], $this->catalog->families());

        return $this->render('market/home.html.twig', ['families' => $families]);
    }

    #[Route(
        path: ['en' => '/tools/poland-used-price-index/{slug}', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych/{slug}'],
        name: 'market_product',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['GET']
    )]
    public function product(string $slug): Response
    {
        $product = $this->catalog->get($slug);
        if ($product === null) {
            throw $this->createNotFoundException();
        }
        $history = $this->observations->history($slug);

        return $this->render('market/product.html.twig', [
            'product' => $product,
            'history' => $history,
            'latest' => $history[0] ?? null,
        ]);
    }

    #[Route(
        path: ['en' => '/tools/poland-used-price-index/request', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych/zglos'],
        name: 'market_request',
        methods: ['POST'],
        priority: 10
    )]
    public function requestProduct(Request $request): JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return $this->json(['error' => $this->translator->trans('market.request.invalid')], 403);
        }
        if (trim((string) $request->request->get('company')) !== '') {
            return $this->json(['ok' => true, 'message' => $this->translator->trans('market.request.saved')]);
        }
        $limitKey = ($request->getClientIp() ?? 'unknown') . '|' . gmdate('Y-m-d');
        $limit = $this->productRequestLimiter->create($limitKey)->consume();
        if (!$limit->isAccepted()) {
            return $this->json(['error' => $this->translator->trans('market.request.too_many')], 429);
        }
        $product = trim((string) $request->request->get('product'));
        $email = strtolower(trim((string) $request->request->get('email')));
        if ($product === '' || mb_strlen($product) > 160 || ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            return $this->json(['error' => $this->translator->trans('market.request.invalid')], 422);
        }
        try {
            $this->productRequests->save($product, $email, $request->getClientIp() ?? 'unknown');
        } catch (\RuntimeException) {
            return $this->json(['error' => $this->translator->trans('market.request.failed')], 503);
        }

        return $this->json(['ok' => true, 'message' => $this->translator->trans('market.request.saved')]);
    }
}
