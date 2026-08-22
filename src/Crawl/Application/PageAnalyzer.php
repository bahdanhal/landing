<?php

declare(strict_types=1);

namespace App\Crawl\Application;

use App\Crawl\Infrastructure\HttpFetcher;

final readonly class PageAnalyzer
{
    public function __construct(private HttpFetcher $fetcher)
    {
    }

    public function analyze(array $fetch): array
    {
        $result = [
            'url' => $fetch['requested_url'],
            'final_url' => $fetch['final_url'],
            'status' => $fetch['status'],
            'duration_ms' => $fetch['duration_ms'],
            'redirects' => $fetch['redirects'],
            'content_type' => $fetch['content_type'],
            'title' => null,
            'description' => null,
            'canonical' => null,
            'canonical_count' => 0,
            'robots' => null,
            'h1' => [],
            'links' => [],
            'get_forms' => [],
            'lang' => null,
            'word_count' => 0,
            'content_hash' => null,
            'error' => $fetch['error'],
        ];

        if ($fetch['status'] < 200 || $fetch['status'] >= 400 || !str_contains($fetch['content_type'], 'html')) {
            return $result;
        }

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($fetch['body'], LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            $result['error'] = 'HTML could not be parsed.';
            return $result;
        }

        $xpath = new \DOMXPath($document);
        $result['title'] = $this->text($xpath->query('//title')->item(0));
        // phpcs:ignore Generic.Files.LineLength
        $result['description'] = $this->attribute($xpath, '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]', 'content');
        // phpcs:ignore Generic.Files.LineLength
        $canonicals = $xpath->query('//link[contains(concat(" ", translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), " "), " canonical ")]');
        $result['canonical_count'] = $canonicals->length;
        $canonical = $canonicals->item(0)?->attributes?->getNamedItem('href')?->nodeValue;
        $result['canonical'] = $canonical ? $this->fetcher->resolveUrl($fetch['final_url'], trim($canonical)) : null;
        $result['robots'] = $this->attribute($xpath, '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots"]', 'content');
        $result['lang'] = $document->documentElement?->getAttribute('lang') ?: null;

        foreach ($xpath->query('//h1') as $heading) {
            $text = $this->text($heading);
            if ($text !== null) {
                $result['h1'][] = $text;
            }
        }

        $links = [];
        foreach ($xpath->query('//a[@href]') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript|data):#i', $href)) {
                continue;
            }
            $absolute = $this->fetcher->resolveUrl($fetch['final_url'], html_entity_decode($href));
            $absolute = preg_replace('/#.*$/', '', $absolute);
            $links[$absolute] = true;
        }
        $result['links'] = array_keys($links);

        foreach ($xpath->query('//form[not(@method) or translate(@method,"abcdefghijklmnopqrstuvwxyz","ABCDEFGHIJKLMNOPQRSTUVWXYZ")="GET"]') as $form) {
            $names = [];
            foreach ((new \DOMXPath($document))->query('.//*[@name]', $form) as $field) {
                $names[] = $field->getAttribute('name');
            }
            $result['get_forms'][] = [
                'action' => $this->fetcher->resolveUrl($fetch['final_url'], $form->getAttribute('action') ?: $fetch['final_url']),
                'parameters' => array_values(array_unique(array_filter($names))),
            ];
        }

        $bodyText = $this->text($xpath->query('//body')->item(0)) ?? '';
        $result['word_count'] = str_word_count($bodyText);
        $result['content_hash'] = hash('xxh3', preg_replace('/\s+/', ' ', strtolower($bodyText)));

        return $result;
    }

    private function text(?\DOMNode $node): ?string
    {
        if ($node === null) {
            return null;
        }
        $value = trim(preg_replace('/\s+/', ' ', $node->textContent));
        return $value === '' ? null : $value;
    }

    private function attribute(\DOMXPath $xpath, string $query, string $name): ?string
    {
        $value = $xpath->query($query)->item(0)?->attributes?->getNamedItem($name)?->nodeValue;
        $value = is_string($value) ? trim($value) : '';
        return $value === '' ? null : $value;
    }
}
