<?php

declare(strict_types=1);

namespace App\Crawl\Application;

use App\Shared\Infrastructure\Http\SafeHttpFetcher;

final readonly class PageAnalyzer
{
    public function __construct(private SafeHttpFetcher $fetcher)
    {
    }

    /**
     * @param array{
     *     requested_url: string,
     *     final_url: string,
     *     status: int,
     *     headers: array<string, list<string>>,
     *     body: string,
     *     content_type: string,
     *     duration_ms: int,
     *     redirects: list<array{url: string, status: int, location: ?string}>,
     *     error: ?string
     * } $fetch
     * @return array{
     *     url: string,
     *     final_url: string,
     *     status: int,
     *     duration_ms: int,
     *     redirects: list<array{url: string, status: int, location: ?string}>,
     *     content_type: string,
     *     title: ?string,
     *     description: ?string,
     *     canonical: ?string,
     *     canonical_count: int,
     *     robots: ?string,
     *     h1: list<string>,
     *     links: list<string>,
     *     get_forms: list<array{action: string, parameters: list<string>}>,
     *     lang: ?string,
     *     word_count: int,
     *     content_hash: ?string,
     *     error: ?string
     * }
     */
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
        $titleNode = $xpath->query('//title');
        $result['title'] = $titleNode !== false ? $this->text($titleNode->item(0)) : null;
        // phpcs:ignore Generic.Files.LineLength
        $result['description'] = $this->attribute($xpath, '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="description"]', 'content');
        // phpcs:ignore Generic.Files.LineLength
        $canonicals = $xpath->query('//link[contains(concat(" ", translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"), " "), " canonical ")]');
        $result['canonical_count'] = $canonicals instanceof \DOMNodeList ? $canonicals->length : 0;
        $canonicalItem = $canonicals instanceof \DOMNodeList ? $canonicals->item(0) : null;
        $canonical = $canonicalItem instanceof \DOMElement ? $canonicalItem->getAttribute('href') : null;
        $result['canonical'] = $canonical !== null && $canonical !== '' ? $this->fetcher->resolveUrl($fetch['final_url'], trim($canonical)) : null;
        $result['robots'] = $this->attribute($xpath, '//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots"]', 'content');
        $result['lang'] = $document->documentElement?->getAttribute('lang') ?: null;

        $h1Nodes = $xpath->query('//h1');
        if ($h1Nodes instanceof \DOMNodeList) {
            foreach ($h1Nodes as $heading) {
                $text = $this->text($heading);
                if ($text !== null) {
                    $result['h1'][] = $text;
                }
            }
        }

        $links = [];
        $anchorNodes = $xpath->query('//a[@href]');
        if ($anchorNodes instanceof \DOMNodeList) {
            foreach ($anchorNodes as $anchor) {
                if (!$anchor instanceof \DOMElement) {
                    continue;
                }
                $href = trim($anchor->getAttribute('href'));
                if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript|data):#i', $href)) {
                    continue;
                }
                $absolute = $this->fetcher->resolveUrl($fetch['final_url'], html_entity_decode($href));
                $cleanUrl = preg_replace('/#.*$/', '', $absolute) ?? $absolute;
                $links[$cleanUrl] = true;
            }
        }
        $result['links'] = array_keys($links);

        $formNodes = $xpath->query('//form[not(@method) or translate(@method,"abcdefghijklmnopqrstuvwxyz","ABCDEFGHIJKLMNOPQRSTUVWXYZ")="GET"]');
        if ($formNodes instanceof \DOMNodeList) {
            foreach ($formNodes as $form) {
                if (!$form instanceof \DOMElement) {
                    continue;
                }
                $names = [];
                $fieldNodes = (new \DOMXPath($document))->query('.//*[@name]', $form);
                if ($fieldNodes instanceof \DOMNodeList) {
                    foreach ($fieldNodes as $field) {
                        if ($field instanceof \DOMElement) {
                            $nameAttr = $field->getAttribute('name');
                            if ($nameAttr !== '') {
                                $names[] = $nameAttr;
                            }
                        }
                    }
                }
                $actionAttr = $form->getAttribute('action');
                $result['get_forms'][] = [
                    'action' => $this->fetcher->resolveUrl($fetch['final_url'], $actionAttr !== '' ? $actionAttr : $fetch['final_url']),
                    'parameters' => array_values(array_unique($names)),
                ];
            }
        }

        $bodyNode = $xpath->query('//body');
        $bodyText = $bodyNode !== false ? ($this->text($bodyNode->item(0)) ?? '') : '';
        $result['word_count'] = str_word_count($bodyText);
        $normalizedText = preg_replace('/\s+/', ' ', strtolower($bodyText)) ?? strtolower($bodyText);
        $result['content_hash'] = hash('xxh3', $normalizedText);

        return $result;
    }

    private function text(\DOMNode|\DOMNameSpaceNode|null $node): ?string
    {
        if (!$node instanceof \DOMNode) {
            return null;
        }
        $value = trim((string) preg_replace('/\s+/', ' ', (string) $node->textContent));
        return $value === '' ? null : $value;
    }

    private function attribute(\DOMXPath $xpath, string $query, string $name): ?string
    {
        $nodeList = $xpath->query($query);
        $item = $nodeList instanceof \DOMNodeList ? $nodeList->item(0) : null;
        if (!$item instanceof \DOMElement) {
            return null;
        }
        $value = trim($item->getAttribute($name));
        return $value === '' ? null : $value;
    }
}
