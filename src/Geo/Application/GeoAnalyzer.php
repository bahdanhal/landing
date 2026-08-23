<?php

declare(strict_types=1);

namespace App\Geo\Application;

use App\Crawl\Application\PageAnalyzer;
use App\Crawl\Domain\RobotsPolicy;
use App\Shared\Infrastructure\Http\SafeHttpFetcher;
use App\Shared\Infrastructure\Http\UrlGuard;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class GeoAnalyzer
{
    // phpcs:ignore Generic.Files.LineLength
    private const CONTENT_SCHEMA_TYPES = ['Article', 'NewsArticle', 'BlogPosting', 'FAQPage', 'HowTo', 'Product', 'Dataset', 'WebApplication', 'SoftwareApplication'];
    private const ENTITY_SCHEMA_TYPES = ['Organization', 'LocalBusiness', 'Person', 'WebSite'];
    private const AI_AGENTS = ['GPTBot', 'ChatGPT-User', 'ClaudeBot', 'PerplexityBot', 'Google-Extended'];

    public function __construct(
        private UrlGuard $urlGuard,
        private SafeHttpFetcher $fetcher,
        private PageAnalyzer $pageAnalyzer,
        private RobotsPolicy $robotsPolicy,
        private CacheInterface $auditCache,
        private int $cacheTtl,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $input, bool $refresh = false): array
    {
        $target = $this->urlGuard->normalize($input);
        $cacheKey = hash('sha256', 'geo-v1|' . $target);
        if ($refresh) {
            $this->auditCache->delete($cacheKey);
        }

        $computed = false;
        $report = $this->auditCache->get($cacheKey, function (ItemInterface $item) use ($target, &$computed): array {
            $computed = true;
            $item->expiresAfter($this->cacheTtl);

            return $this->perform($target);
        });
        $report['cache'] = ['hit' => !$computed, 'ttl_seconds' => $this->cacheTtl];

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function perform(string $target): array
    {
        $started = hrtime(true);
        $fetch = $this->fetcher->fetch($target);
        if ($fetch['status'] === 0) {
            throw new \RuntimeException('The page could not be reached: ' . $fetch['error']);
        }
        if (!str_contains($fetch['content_type'], 'html')) {
            throw new \RuntimeException('The URL did not return an HTML page.');
        }

        $page = $this->pageAnalyzer->analyze($fetch);
        $origin = $this->origin($fetch['final_url']);
        $robotsFetch = $this->fetcher->fetch($origin . '/robots.txt');
        $llmsFetch = $this->fetcher->fetch($origin . '/llms.txt');
        $document = $this->document($fetch['body']);
        $xpath = new \DOMXPath($document);
        $robotsBody = $robotsFetch['status'] === 200 ? $robotsFetch['body'] : '';
        $robotsAllowed = $this->robotsPolicy->allows($fetch['final_url'], $this->robotsPolicy->parse($robotsBody));
        $xRobots = strtolower(implode(', ', $fetch['headers']['x-robots-tag'] ?? []));
        $metaRobots = strtolower($page['robots'] ?? '');
        $schema = $this->schema($xpath);
        $types = $schema['types'];
        $firstParagraph = $this->firstParagraph($xpath);
        $paragraphWords = $this->wordCount($firstParagraph);
        $h2Count = $xpath->query('//h2') instanceof \DOMNodeList ? $xpath->query('//h2')->length : 0;
        $paragraphNodes = $xpath->query('//main//p | //article//p');
        $paragraphCount = $paragraphNodes instanceof \DOMNodeList ? $paragraphNodes->length : 0;
        $listNodes = $xpath->query('//main//ul | //main//ol | //main//table | //article//ul | //article//ol | //article//table');
        $hasListOrTable = $listNodes instanceof \DOMNodeList && $listNodes->length > 0;
        $mainNodes = $xpath->query('//main | //article');
        $hasMainContent = $mainNodes instanceof \DOMNodeList && $mainNodes->length > 0;
        $externalLinks = $this->externalLinks($xpath, $fetch['final_url']);
        $hasAuthor = $schema['has_author'] || $this->matchesText($xpath, 'author|written by|reviewed by|autor|napisał|recenzja');
        $hasPublisher = $schema['has_publisher'];
        $timeNodes = $xpath->query('//time[@datetime]');
        $hasDate = $schema['has_date'] || ($timeNodes instanceof \DOMNodeList && $timeNodes->length > 0);
        $questionCount = $this->questionHeadingCount($xpath);
        $hasFaq = in_array('FAQPage', $types, true) || $questionCount >= 2;
        $hasEntitySchema = array_intersect(self::ENTITY_SCHEMA_TYPES, $types) !== [];
        $hasContentSchema = array_intersect(self::CONTENT_SCHEMA_TYPES, $types) !== [];
        $hasAboutContact = $this->hasAboutContactLink($xpath);
        $siteNameNodes = $xpath->query('//meta[translate(@property,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="og:site_name"]');
        $hasSiteName = $siteNameNodes instanceof \DOMNodeList && $siteNameNodes->length > 0;

        $checks = [];
        $this->add($checks, 'access', $fetch['status'] === 200 ? 10 : 0, 10, ['status' => $fetch['status'], 'final_url' => $fetch['final_url']]);
        $indexable = $fetch['status'] === 200 && $robotsAllowed && !str_contains($metaRobots, 'noindex') && !str_contains($xRobots, 'noindex');
        $this->add($checks, 'indexability', $indexable ? 10 : 0, 10, [
            'robots_allowed' => $robotsAllowed,
            'meta_robots' => $page['robots'],
            'x_robots_tag' => $xRobots ?: null,
        ]);
        $canonicalPoints = $page['canonical'] === null ? 0 : ($this->sameUrl($page['canonical'], $fetch['final_url']) ? 5 : 2);
        $this->add($checks, 'canonical', $canonicalPoints, 5, ['canonical' => $page['canonical'], 'final_url' => $fetch['final_url']]);
        $metadataPoints = ($page['title'] ? 3 : 0) + ($page['description'] ? 3 : 0) + (count($page['h1']) === 1 ? 2 : 0);
        $this->add($checks, 'metadata', $metadataPoints, 8, [
            'title' => $page['title'],
            'description' => $page['description'],
            'h1_count' => count($page['h1']),
        ]);
        $this->add($checks, 'language', $page['lang'] ? 3 : 0, 3, ['html_lang' => $page['lang']]);
        $answerPoints = $paragraphWords >= 25 && $paragraphWords <= 120 ? 10 : ($paragraphWords > 0 ? 5 : 0);
        $this->add($checks, 'direct_answer', $answerPoints, 10, [
            'first_paragraph_words' => $paragraphWords,
            'sample' => $this->truncate($firstParagraph, 240),
        ]);
        // phpcs:ignore Generic.Files.LineLength
        $structurePoints = ($h2Count >= 2 ? 3 : ($h2Count === 1 ? 1 : 0)) + ($hasListOrTable ? 2 : 0) + ($hasMainContent ? 2 : 0) + ($paragraphCount >= 3 ? 1 : 0);
        $this->add($checks, 'structure', $structurePoints, 8, [
            'h2_count' => $h2Count,
            'content_paragraphs' => $paragraphCount,
            'list_or_table' => $hasListOrTable,
            'main_or_article' => $hasMainContent,
        ]);
        $schemaPoints = ($schema['valid_count'] > 0 ? 4 : 0) + ($hasEntitySchema ? 4 : 0) + ($hasContentSchema ? 4 : 0);
        $this->add($checks, 'schema', $schemaPoints, 12, [
            'valid_json_ld_blocks' => $schema['valid_count'],
            'invalid_json_ld_blocks' => $schema['invalid_count'],
            'types' => $types,
        ]);
        $provenancePoints = ($hasAuthor ? 4 : 0) + ($hasPublisher ? 3 : 0) + ($hasDate ? 3 : 0);
        $this->add($checks, 'provenance', $provenancePoints, 10, ['author' => $hasAuthor, 'publisher' => $hasPublisher, 'dated' => $hasDate]);
        $citationPoints = count($externalLinks) >= 3 ? 8 : (count($externalLinks) > 0 ? 4 : 0);
        $this->add($checks, 'citations', $citationPoints, 8, ['external_links' => count($externalLinks), 'sample' => array_slice($externalLinks, 0, 5)]);
        // phpcs:ignore Generic.Files.LineLength
        $this->add($checks, 'faq', $hasFaq ? 5 : ($questionCount > 0 ? 2 : 0), 5, ['faq_schema' => in_array('FAQPage', $types, true), 'question_headings' => $questionCount]);
        $this->add($checks, 'freshness', $hasDate ? 5 : 0, 5, ['date_in_schema_or_time' => $hasDate]);

        $entityPoints = ($hasEntitySchema ? 2 : 0) + ($hasAboutContact ? 2 : 0) + ($hasSiteName ? 2 : 0);
        // phpcs:ignore Generic.Files.LineLength
        $this->add($checks, 'entity', $entityPoints, 6, ['entity_schema' => $hasEntitySchema, 'about_or_contact_link' => $hasAboutContact, 'site_name' => $hasSiteName]);

        $score = array_sum(array_column($checks, 'earned'));
        $counts = ['pass' => 0, 'warning' => 0, 'fail' => 0];
        foreach ($checks as $check) {
            ++$counts[$check['status']];
        }

        $botPolicies = [];
        foreach (self::AI_AGENTS as $agent) {
            $botPolicies[$agent] = $this->botPolicy($robotsBody, $agent);
        }

        return [
            'target' => $target,
            'final_url' => $fetch['final_url'],
            'origin' => $origin,
            'generated_at' => gmdate(DATE_ATOM),
            'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'score' => $score,
            'counts' => $counts,
            'checks' => $checks,
            'page' => [
                'status' => $fetch['status'], 'title' => $page['title'], 'description' => $page['description'],
                'canonical' => $page['canonical'], 'lang' => $page['lang'], 'word_count' => $page['word_count'],
                'schema_types' => $types,
            ],
            'crawler_controls' => [
                'robots_status' => $robotsFetch['status'], 'policies' => $botPolicies,
                'llms_txt_status' => $llmsFetch['status'],
                'llms_txt_present' => $llmsFetch['status'] === 200 && trim($llmsFetch['body']) !== '',
            ],
        ];
    }

    /**
     * @param list<array{id: string, earned: int, maximum: int, status: string, evidence: array<string, mixed>}> $checks
     * @param array<string, mixed> $evidence
     */
    private function add(array &$checks, string $id, int $earned, int $maximum, array $evidence): void
    {
        $checks[] = [
            'id' => $id,
            'earned' => $earned,
            'maximum' => $maximum,
            'status' => $earned === $maximum ? 'pass' : ($earned > 0 ? 'warning' : 'fail'),
            'evidence' => $evidence,
        ];
    }

    private function document(string $html): \DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    /**
     * @return array{types: list<string>, valid_count: int, invalid_count: int, has_author: bool, has_publisher: bool, has_date: bool}
     */
    private function schema(\DOMXPath $xpath): array
    {
        $types = [];
        $valid = 0;
        $invalid = 0;
        $hasAuthor = false;
        $hasPublisher = false;
        $hasDate = false;
        $nodes = $xpath->query('//script[translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="application/ld+json"]');
        if ($nodes instanceof \DOMNodeList) {
            foreach ($nodes as $node) {
                if (!$node instanceof \DOMNode) {
                    continue;
                }
                try {
                    $data = json_decode((string) $node->textContent, true, 64, JSON_THROW_ON_ERROR);
                    ++$valid;
                    $this->collectTypes($data, $types);
                    $encoded = strtolower(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
                    $hasAuthor = $hasAuthor || str_contains($encoded, '"author"');
                    $hasPublisher = $hasPublisher || str_contains($encoded, '"publisher"');
                    $hasDate = $hasDate || str_contains($encoded, '"datepublished"') || str_contains($encoded, '"datemodified"');
                } catch (\JsonException) {
                    ++$invalid;
                }
            }
        }
        sort($types);

        return [
            'types' => array_values(array_unique($types)),
            'valid_count' => $valid,
            'invalid_count' => $invalid,
            'has_author' => $hasAuthor,
            'has_publisher' => $hasPublisher,
            'has_date' => $hasDate,
        ];
    }

    /**
     * @param list<string> $types
     */
    private function collectTypes(mixed $value, array &$types): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            if ($key === '@type') {
                foreach ((array) $item as $type) {
                    if (is_string($type) && $type !== '') {
                        $types[] = $type;
                    }
                }
            } else {
                $this->collectTypes($item, $types);
            }
        }
    }

    private function firstParagraph(\DOMXPath $xpath): string
    {
        $nodes = $xpath->query('(//main//p[normalize-space()] | //article//p[normalize-space()] | //body//p[normalize-space()])[1]');
        $item = $nodes instanceof \DOMNodeList ? $nodes->item(0) : null;
        $content = $item instanceof \DOMNode ? (string) $item->textContent : '';

        return trim((string) preg_replace('/\s+/u', ' ', $content));
    }

    private function wordCount(string $text): int
    {
        return preg_match_all('/[\p{L}\p{N}]+(?:[’\'-][\p{L}\p{N}]+)*/u', $text) ?: 0;
    }

    /**
     * @return list<string>
     */
    private function externalLinks(\DOMXPath $xpath, string $pageUrl): array
    {
        $host = strtolower((string) parse_url($pageUrl, PHP_URL_HOST));
        $links = [];
        $nodes = $xpath->query('//a[@href]');
        if ($nodes instanceof \DOMNodeList) {
            foreach ($nodes as $anchor) {
                if (!$anchor instanceof \DOMElement) {
                    continue;
                }
                $href = trim($anchor->getAttribute('href'));
                if (!preg_match('#^https?://#i', $href)) {
                    continue;
                }
                $linkHost = strtolower((string) parse_url($href, PHP_URL_HOST));
                if ($linkHost !== '' && $linkHost !== $host) {
                    $links[$href] = true;
                }
            }
        }

        return array_keys($links);
    }

    private function matchesText(\DOMXPath $xpath, string $pattern): bool
    {
        $bodyNodes = $xpath->query('//body');
        $item = $bodyNodes instanceof \DOMNodeList ? $bodyNodes->item(0) : null;
        $content = $item instanceof \DOMNode ? (string) $item->textContent : '';
        $text = trim((string) preg_replace('/\s+/u', ' ', $content));

        return preg_match('/\b(?:' . $pattern . ')\b/iu', $text) === 1;
    }

    private function questionHeadingCount(\DOMXPath $xpath): int
    {
        $count = 0;
        $nodes = $xpath->query('//h2 | //h3 | //summary');
        if ($nodes instanceof \DOMNodeList) {
            foreach ($nodes as $node) {
                if (!$node instanceof \DOMNode) {
                    continue;
                }
                if (str_contains(trim((string) $node->textContent), '?')) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    private function hasAboutContactLink(\DOMXPath $xpath): bool
    {
        $nodes = $xpath->query('//a[@href]');
        if ($nodes instanceof \DOMNodeList) {
            foreach ($nodes as $anchor) {
                if (!$anchor instanceof \DOMElement) {
                    continue;
                }
                $value = strtolower($anchor->getAttribute('href') . ' ' . (string) $anchor->textContent);
                if (preg_match('/about|contact|o-nas|o nas|kontakt|redakcja/u', $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function botPolicy(string $robotsBody, string $agent): string
    {
        if (trim($robotsBody) === '') {
            return 'not-addressed';
        }
        $groups = $this->robotsGroups($robotsBody);
        $exact = array_values(array_filter($groups, static fn (array $group): bool => in_array(strtolower($agent), $group['agents'], true)));
        $selected = $exact !== [] ? $exact : array_values(array_filter($groups, static fn (array $group): bool => in_array('*', $group['agents'], true)));
        if ($selected === []) {
            return 'not-addressed';
        }
        $hasRootBlock = false;
        $hasRootAllow = false;
        foreach ($selected as $group) {
            foreach ($group['directives'] as $directive) {
                $hasRootBlock = $hasRootBlock || ($directive['name'] === 'disallow' && $directive['value'] === '/');
                $hasRootAllow = $hasRootAllow || ($directive['name'] === 'allow' && $directive['value'] === '/');
            }
        }

        return $hasRootBlock && !$hasRootAllow ? 'blocked' : ($exact !== [] ? 'allowed' : 'not-addressed');
    }

    /**
     * @return list<array{agents: list<string>, directives: list<array{name: string, value: string}>}>
     */
    private function robotsGroups(string $body): array
    {
        $groups = [];
        $agents = [];
        $directives = [];
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s*#.*$/', '', $line));
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $name = strtolower($name);
            if ($name === 'user-agent') {
                if ($directives !== []) {
                    $groups[] = compact('agents', 'directives');
                    $agents = [];
                    $directives = [];
                }
                $agents[] = strtolower($value);
            } elseif ($agents !== []) {
                $directives[] = ['name' => $name, 'value' => $value];
            }
        }
        if ($agents !== [] || $directives !== []) {
            $groups[] = compact('agents', 'directives');
        }

        return $groups;
    }

    private function sameUrl(string $a, string $b): bool
    {
        return strtolower(rtrim($a, '/')) === strtolower(rtrim($b, '/'));
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) <= $length ? $value : mb_substr($value, 0, $length - 1) . '…';
    }
}
