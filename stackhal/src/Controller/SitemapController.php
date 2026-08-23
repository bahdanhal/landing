<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SitemapController
{
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function __invoke(): Response
    {
        $urls = [
            '/',
            '/geo-audit',
            '/seo-audit',
            '/bimi-studio',
            '/domain-inspector',
            '/caddy-transpiler',
            '/apple-pkpass-inspector',
            '/cidr-subnet-matrix',
        ];

        $entries = array_map(fn (string $path): string => sprintf('  <url><loc>https://stackhal.com%s</loc></url>', $path), $urls);

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<?xml-stylesheet type=\"text/xsl\" href=\"/sitemap.xsl\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            . implode("\n", $entries) . "\n"
            . "</urlset>\n";

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
        ]);
    }
}
