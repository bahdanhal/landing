<?php

declare(strict_types=1);

namespace App\Controller;

use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SitemapController
{
    public function __construct(
        private ProductCatalog $catalog,
        private PriceObservationRepository $observations,
    ) {
    }

    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function __invoke(): Response
    {
        $pairs = [
            ['/', '/pl/'],
            ['/salary-calculator', '/kalkulator-wynagrodzen'],
        ];
        $entries = [];
        foreach ($pairs as [$en, $pl]) {
            $entries[] = $this->entry($en, $en, $pl);
            $entries[] = $this->entry($pl, $en, $pl);
        }
        foreach ($this->catalog->all() as $product) {
            $latest = $this->observations->latest($product->slug);
            if ($latest === null) {
                continue;
            }
            $en = '/prices/' . $product->slug;
            $pl = '/ceny/' . $product->slug;
            $lastModified = $latest->observedAt->format('Y-m-d');
            $entries[] = $this->entry($en, $en, $pl, $lastModified);
            $entries[] = $this->entry($pl, $en, $pl, $lastModified);
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<?xml-stylesheet type=\"text/xsl\" href=\"/sitemap.xsl\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n"
            . implode("\n", $entries) . "\n"
            . "</urlset>\n";

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
        ]);
    }

    private function entry(string $location, string $english, string $polish, ?string $lastModified = null): string
    {
        $base = 'https://ileza.pl';
        $lastmod = $lastModified === null ? '' : '<lastmod>' . $lastModified . '</lastmod>';

        $format = '  <url><loc>%s</loc>%s'
            . '<xhtml:link rel="alternate" hreflang="en" href="%s"/>'
            . '<xhtml:link rel="alternate" hreflang="pl" href="%s"/>'
            . '<xhtml:link rel="alternate" hreflang="x-default" href="%s"/></url>';

        return sprintf(
            $format,
            $base . $location,
            $lastmod,
            $base . $english,
            $base . $polish,
            $base . $english
        );
    }
}
