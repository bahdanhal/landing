<?php

declare(strict_types=1);

namespace App\Tests\Content;

use App\Content\Application\ContentCatalogue;
use App\Content\Application\EditContent;
use App\Content\Infrastructure\ContentStore;
use App\Tests\DoctrineTestCase;

final class ContentEditorTest extends DoctrineTestCase
{
    private ContentCatalogue $catalogue;
    private ContentStore $store;
    private EditContent $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogue = new ContentCatalogue(dirname(__DIR__, 2));
        $this->store = new ContentStore($this->entityManager->getConnection());
        $this->editor = new EditContent($this->catalogue, $this->store);
    }

    public function testDraftsAreSeparateFromPublishedTextAndLocales(): void
    {
        $fields = $this->catalogue->fields('en', 'obd');
        $fields['showcase.obd.description'] = 'A helpful car app';
        $this->editor->save('en', 'obd', $fields, 0, 'draft');
        $state = $this->store->read('en');
        self::assertSame('A helpful car app', $state['draft']['showcase.obd.description']);
        self::assertSame([], $state['published']);
        self::assertSame([], $this->store->read('pl')['draft']);
        $this->editor->save('en', 'obd', $fields, 1, 'publish');
        self::assertSame('A helpful car app', $this->store->read('en')['published']['showcase.obd.description']);
    }

    public function testPublishingOneSectionDoesNotPublishOtherDrafts(): void
    {
        $home = $this->catalogue->fields('en', 'home');
        $home['about.heading'] = 'An unpublished headline';
        $this->editor->save('en', 'home', $home, 0, 'draft');
        $obd = $this->catalogue->fields('en', 'obd');
        $obd['showcase.obd.description'] = 'Published app text';
        $this->editor->save('en', 'obd', $obd, 1, 'publish');
        self::assertArrayNotHasKey('about.heading', $this->store->read('en')['published']);
        self::assertSame('An unpublished headline', $this->store->read('en')['draft']['about.heading']);
    }

    public function testStaleSaveCannotOverwriteARecentEdit(): void
    {
        $fields = $this->catalogue->fields('en', 'obd');
        $fields['showcase.obd.description'] = 'First version';
        $this->editor->save('en', 'obd', $fields, 0, 'publish');
        try {
            $this->store->write('en', [], [], 0);
            self::fail('Expected stale version rejection.');
        } catch (\DomainException) {
            self::assertSame('First version', $this->store->read('en')['published']['showcase.obd.description']);
        }
    }

    public function testDiscardAndOriginalTextRestoreWork(): void
    {
        $fields = $this->catalogue->fields('en', 'obd');
        $fields['showcase.obd.description'] = 'Published';
        $this->editor->save('en', 'obd', $fields, 0, 'publish');
        $fields['showcase.obd.description'] = 'Draft';
        $this->editor->save('en', 'obd', $fields, 1, 'draft');
        $this->editor->save('en', 'obd', [], 2, 'discard');
        self::assertSame('Published', $this->store->read('en')['draft']['showcase.obd.description']);
        $this->editor->save('en', 'obd', $this->catalogue->fields('en', 'obd'), 3, 'publish');
        self::assertSame([], $this->store->read('en')['published']);
    }

    public function testRejectsUnsafeLinksAndUnknownFieldsWithoutWriting(): void
    {
        $fields = $this->catalogue->fields('en', 'links');
        $fields['links.obd_website'] = 'javascript:alert(1)';
        try {
            $this->editor->save('en', 'links', $fields, 0, 'publish');
            self::fail('Expected URL validation.');
        } catch (\InvalidArgumentException) {
            self::assertSame(0, $this->store->read('en')['revision']);
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->editor->save('en', 'obd', $this->catalogue->fields('en', 'obd') + ['unknown' => 'value'], 0, 'publish');
    }

    public function testEveryDefaultTextBelongsToExactlyOneEditorSection(): void
    {
        foreach (['en', 'pl'] as $locale) {
            $keys = [];
            foreach (array_keys($this->catalogue->sections()) as $section) {
                $fields = $this->catalogue->fields($locale, $section);
                self::assertNotEmpty($fields, $section);
                foreach (array_keys($fields) as $key) {
                    self::assertNotContains($key, $keys);
                    $keys[] = $key;
                }
            }
            self::assertCount(count($this->catalogue->defaults($locale)), $keys);
        }
    }
}
