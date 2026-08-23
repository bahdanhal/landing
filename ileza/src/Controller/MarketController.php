<?php

declare(strict_types=1);

namespace App\Controller;

use App\Market\Application\GetProductPriceHistory;
use App\Market\Application\ProductCatalog;
use App\Market\Application\RecordProductRequest;
use App\Market\Application\SubmitCommunityPriceTip;
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
        private readonly GetProductPriceHistory $priceHistory,
        private readonly RecordProductRequest $recordProductRequest,
        private readonly RateLimiterFactory $productRequestLimiter,
        private readonly SubmitCommunityPriceTip $submitPriceTip,
        private readonly RateLimiterFactory $priceTipLimiter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: ['en' => '/', 'pl' => '/pl/'],
        name: 'market_home',
        methods: ['GET']
    )]
    public function home(): Response
    {
        $families = array_map(fn ($family) => [
            'family' => $family,
            'configurations' => array_map(fn ($product) => [
                'product' => $product,
                'latest' => $this->priceHistory->latestForProduct($product->slug),
            ], $family->configurations),
        ], $this->catalog->families());

        return $this->render('market/home.html.twig', ['families' => $families]);
    }

    #[Route(
        path: ['en' => '/tools/poland-used-price-index', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych'],
        name: 'legacy_market_home',
        methods: ['GET']
    )]
    public function legacyHome(Request $request): Response
    {
        return $this->redirectToRoute('market_home', ['_locale' => $request->getLocale()], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        path: ['en' => '/prices/{slug}', 'pl' => '/ceny/{slug}'],
        name: 'market_product',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['GET']
    )]
    public function product(string $slug): Response
    {
        $detailed = $this->priceHistory->detailedHistory($slug);
        if ($detailed === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('market/product.html.twig', [
            'product' => $detailed['product'],
            'family' => $detailed['family'],
            'history' => $detailed['history'],
            'latest' => $detailed['latest'],
            'one_month_ago' => $detailed['one_month_ago'],
        ]);
    }

    #[Route(
        path: ['en' => '/tools/poland-used-price-index/{slug}', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych/{slug}'],
        name: 'legacy_market_product',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['GET']
    )]
    public function legacyProduct(string $slug, Request $request): Response
    {
        return $this->redirectToRoute('market_product', [
            '_locale' => $request->getLocale(),
            'slug' => $slug,
        ], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        path: ['en' => '/request', 'pl' => '/zglos'],
        name: 'market_request',
        methods: ['POST'],
        priority: 10
    )]
    #[Route(
        path: ['en' => '/tools/poland-used-price-index/request', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych/zglos'],
        name: 'legacy_market_request',
        methods: ['POST'],
        priority: 5
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
            $this->recordProductRequest->execute($product, $email, $request->getClientIp() ?? 'unknown');
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => $this->translator->trans('market.request.invalid')], 422);
        } catch (\RuntimeException) {
            return $this->json(['error' => $this->translator->trans('market.request.failed')], 503);
        }

        return $this->json(['ok' => true, 'message' => $this->translator->trans('market.request.saved')]);
    }

    #[Route(
        path: ['en' => '/prices/{slug}/price-tip', 'pl' => '/ceny/{slug}/okazja'],
        name: 'market_price_tip',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['POST'],
        priority: 20,
    )]
    #[Route(
        path: ['en' => '/tools/poland-used-price-index/{slug}/price-tip', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych/{slug}/okazja'],
        name: 'legacy_market_price_tip',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['POST'],
        priority: 15,
    )]
    public function submitPriceTip(string $slug, Request $request): JsonResponse
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return $this->json(['error' => $this->translator->trans('market.tip.invalid')], 403);
        }
        if (trim((string) $request->request->get('company')) !== '') {
            return $this->json(['ok' => true, 'message' => $this->translator->trans('market.tip.saved')]);
        }
        if ($this->catalog->get($slug) === null) {
            return $this->json(['error' => $this->translator->trans('market.tip.invalid')], 404);
        }
        $limitKey = ($request->getClientIp() ?? 'unknown') . '|' . gmdate('Y-m-d');
        if (!$this->priceTipLimiter->create($limitKey)->consume()->isAccepted()) {
            return $this->json(['error' => $this->translator->trans('market.tip.too_many')], 429);
        }

        try {
            $this->submitPriceTip->execute(
                $slug,
                (string) $request->request->get('listing_url'),
                (string) $request->request->get('email'),
                $request->getClientIp() ?? 'unknown',
            );
        } catch (\InvalidArgumentException) {
            return $this->json(['error' => $this->translator->trans('market.tip.invalid')], 422);
        } catch (\RuntimeException) {
            return $this->json(['error' => $this->translator->trans('market.tip.failed')], 503);
        }

        return $this->json(['ok' => true, 'message' => $this->translator->trans('market.tip.saved')]);
    }
}
