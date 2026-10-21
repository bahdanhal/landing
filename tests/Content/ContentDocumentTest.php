<?php

declare(strict_types=1);

namespace App\Tests\Content;

use App\Content\Domain\ContentDocument;
use App\Content\Domain\ContentSchema;
use App\Content\Domain\ContentValidationException;
use App\Content\Infrastructure\ContentDefaults;
use PHPUnit\Framework\TestCase;

final class ContentDocumentTest extends TestCase
{
    private ContentDefaults $defaults;

    protected function setUp(): void
    {
        $this->defaults = new ContentDefaults(dirname(__DIR__, 2));
    }

    public function testShippedDefaultsAreValidAndStable(): void
    {
        $document = $this->defaults->document();
        $normalized = ContentDocument::normalize($document, $this->defaults->texts());

        self::assertSame($document['collections'], $normalized['collections']);
        self::assertSame([], $normalized['texts']);
        self::assertCount(5, $normalized['collections']['experience']);
        self::assertSame(['web24', 'freedom', 'gwo', 'manao'], array_column(
            array_filter($normalized['collections']['experience'], static fn (array $job): bool => $job['on_home'] === true),
            'id',
        ));
    }

    public function testEveryTextKeyUsedByTemplatesHasADefault(): void
    {
        $root = dirname(__DIR__, 2) . '/templates';
        $files = array_merge(glob($root . '/*.twig') ?: [], glob($root . '/*/*.twig') ?: []);
        $templates = implode("\n", array_map(static fn (string $file): string => (string) file_get_contents($file), $files));
        preg_match_all("/'([a-z0-9_]+(?:\\.[a-z0-9_]+)+)'\\|trans/", $templates, $matches);
        $missing = array_diff(array_unique($matches[1]), array_keys($this->defaults->texts()));

        self::assertSame([], array_values($missing));
    }

    public function testTextsEqualToDefaultsAreNotStored(): void
    {
        $texts = $this->defaults->texts();
        $result = ContentDocument::normalize([
            'collections' => $this->defaults->document()['collections'],
            'texts' => [
                'about.heading' => $texts['about.heading'],
                'about.intro' => ['en' => 'New intro', 'pl' => $texts['about.intro']['pl']],
            ],
        ], $texts);

        self::assertSame(['about.intro' => ['en' => 'New intro', 'pl' => $texts['about.intro']['pl']]], $result['texts']);
    }

    public function testSharedLinksMustBeHttpsAndAreCopiedToBothLanguages(): void
    {
        $texts = $this->defaults->texts();
        $result = ContentDocument::normalize(['texts' => ['links.obd_website' => ['en' => 'https://example.com/', 'pl' => 'ignored']]], $texts);
        self::assertSame(['en' => 'https://example.com/', 'pl' => 'https://example.com/'], $result['texts']['links.obd_website']);

        $this->expectException(ContentValidationException::class);
        ContentDocument::normalize(['texts' => ['links.obd_website' => ['en' => 'javascript:alert(1)']]], $texts);
    }

    public function testListEntriesKeepOrderAndDropEmptyRows(): void
    {
        $job = $this->job(['highlights' => [['en' => 'B', 'pl' => 'B pl'], ['en' => '', 'pl' => ' '], ['en' => 'A', 'pl' => '']]]);
        $result = ContentDocument::normalize(['collections' => ['experience' => [$job]]], []);

        self::assertSame([['en' => 'B', 'pl' => 'B pl'], ['en' => 'A', 'pl' => '']], $result['collections']['experience'][0]['highlights']);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidDocuments(): iterable
    {
        yield 'unknown list' => [['collections' => ['secrets' => []]], 'Unknown content list'];
        yield 'duplicate id' => [['collections' => ['faq' => [self::faq('a'), self::faq('a')]]], 'duplicate identifier'];
        yield 'bad id' => [['collections' => ['faq' => [self::faq('../x')]]], 'invalid or duplicate identifier'];
        yield 'unknown field' => [['collections' => ['faq' => [self::faq('a') + ['html' => 'x']]]], 'unknown fields html'];
        $partial = ['id' => 'a', 'question' => ['en' => 'Q'], 'answer' => ['en' => 'A', 'pl' => 'A']];
        yield 'missing language' => [['collections' => ['faq' => [$partial]]], 'Question (PL)'];
        yield 'unknown image' => [['collections' => ['projects' => [self::project(['image' => '/etc/passwd'])]]], 'choose one of the available images'];
        yield 'http link' => [['collections' => ['projects' => [self::project(['url' => 'http://example.com'])]]], 'https://'];
        $long = ['id' => 'a', 'question' => ['en' => str_repeat('x', 5001), 'pl' => ''], 'answer' => ['en' => '', 'pl' => '']];
        yield 'too long' => [['collections' => ['faq' => [$long]]], 'at most 5000 characters'];
        yield 'unknown text' => [['texts' => ['kernel.secret' => ['en' => 'x', 'pl' => 'x']]], 'Unknown page text'];
    }

    /** @param array<string, mixed> $document */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidDocuments')]
    public function testInvalidDocumentsAreRejectedWithReadableMessages(array $document, string $message): void
    {
        $this->expectException(ContentValidationException::class);
        $this->expectExceptionMessage($message);
        ContentDocument::normalize($document, $this->defaults->texts());
    }

    public function testItemLimitIsEnforced(): void
    {
        $items = [];
        for ($i = 0; $i <= ContentSchema::MAX_ITEMS; $i++) {
            $items[] = self::faq('q' . $i);
        }
        $this->expectExceptionMessage('at most ' . ContentSchema::MAX_ITEMS . ' items');
        ContentDocument::normalize(['collections' => ['faq' => $items]], []);
    }

    /** @return array{id: string, question: array{en: string, pl: string}, answer: array{en: string, pl: string}} */
    private static function faq(string $id): array
    {
        return ['id' => $id, 'question' => ['en' => 'Q', 'pl' => 'P'], 'answer' => ['en' => 'A', 'pl' => 'O']];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function project(array $overrides): array
    {
        $pair = ['en' => 'x', 'pl' => 'y'];

        return $overrides + [
            'id' => 'p', 'name' => 'P', 'url' => 'https://example.com/', 'image' => ContentSchema::IMAGES[0],
            'image_alt' => $pair, 'label' => $pair, 'title' => $pair, 'text' => $pair,
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function job(array $overrides): array
    {
        $pair = ['en' => 'x', 'pl' => 'y'];

        return $overrides + [
            'id' => 'job', 'company' => $pair, 'role' => $pair, 'period' => $pair, 'location' => $pair,
            'highlights' => [], 'on_home' => false,
        ];
    }
}
