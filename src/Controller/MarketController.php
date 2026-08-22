<?php

namespace App\Controller;

use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MarketController extends AbstractController
{
    public function __construct(private readonly ProductCatalog $catalog, private readonly PriceObservationRepository $observations)
    {
    }

    #[Route(path: ['en' => '/tools/poland-used-price-index', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych'], name: 'market_home', methods: ['GET'])]
    public function home(): Response
    {
        $products = array_map(fn ($product) => ['product' => $product, 'latest' => $this->observations->latest($product->slug)], $this->catalog->all());

        return $this->render('market/home.html.twig', ['products' => $products]);
    }

    #[Route(path: ['en' => '/tools/poland-used-price-index/{slug}', 'pl' => '/pl/narzedzia/indeks-cen-uzywanych/{slug}'], name: 'market_product', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function product(string $slug): Response
    {
        $product = $this->catalog->get($slug);
        if ($product === null) {
            throw $this->createNotFoundException();
        }
        $history = $this->observations->history($slug);

        return $this->render('market/product.html.twig', ['product' => $product, 'history' => $history, 'latest' => $history[0] ?? null]);
    }
}
