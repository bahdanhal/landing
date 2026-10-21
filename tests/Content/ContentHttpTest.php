<?php

declare(strict_types=1);

namespace App\Tests\Content;

use App\Content\Application\ContentCatalogue;
use App\Content\Application\EditContent;
use App\Content\Infrastructure\ContentStore;
use App\Entity\SiteContentEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContentHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $secret;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        $this->client = self::createClient(['debug' => false]);
        $this->secret = (string) $_ENV['APP_SECRET'];
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        $databasePath = $manager->getConnection()->getParams()['path'] ?? null;
        if (is_string($databasePath) && !is_dir(dirname($databasePath))) {
            mkdir(dirname($databasePath), 0777, true);
        }
        $manager->getConnection()->executeStatement('DROP TABLE IF EXISTS site_content');
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(SiteContentEntity::class)]);
    }

    public function testEditorRequiresAuthenticationAndCsrf(): void
    {
        $this->client->request('GET', '/admin/content');
        self::assertResponseRedirects('/admin');
        $this->client->request('GET', '/admin/content/en/obd', server: ['HTTP_X_ADMIN_TOKEN' => $this->secret]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('textarea[name="fields[showcase.obd.description]"]');
        $this->client->request('POST', '/admin/content/en/obd', ['action' => 'publish', 'revision' => 0, 'fields' => []], server: [
            'HTTP_X_ADMIN_TOKEN' => $this->secret,
        ]);
        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
    }

    public function testDraftPreviewIsPrivateAndPublicationEscapesHtml(): void
    {
        $catalogue = new ContentCatalogue(dirname(__DIR__, 2));
        $fields = $catalogue->fields('en', 'obd');
        $fields['showcase.obd.description'] = '<script>alert("test")</script> Safe car app';
        $this->save($fields, 0, 'draft');
        self::assertResponseRedirects('/admin/content/en/obd?saved=draft', 303);
        $this->client->request('GET', '/?_content_preview=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('#obd h2', 'Safe car app');
        $this->client->request('GET', '/?_content_preview=1', server: ['HTTP_X_ADMIN_TOKEN' => $this->secret]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#obd h2', 'Safe car app');
        self::assertSelectorNotExists('#obd h2 script');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        $this->save($fields, 1, 'publish');
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#obd h2', 'Safe car app');
        self::assertSelectorNotExists('#obd h2 script');
        $this->client->request('GET', '/pl/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('#obd h2', 'Safe car app');
    }

    public function testConflictPreservesSubmittedTextAndPreventsResubmission(): void
    {
        $fields = (new ContentCatalogue(dirname(__DIR__, 2)))->fields('en', 'obd');
        $this->save($fields, 0, 'draft');
        $fields['showcase.obd.description'] = 'Keep my unsaved text';
        $this->save($fields, 0, 'publish');
        self::assertResponseStatusCodeSame(409);
        self::assertSelectorTextContains('textarea[name="fields[showcase.obd.description]"]', 'Keep my unsaved text');
        self::assertSelectorExists('button[value="publish"][disabled]');
    }

    public function testEditedMetaAndProjectLinksRenderOnPublicPages(): void
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        $catalogue = new ContentCatalogue(dirname(__DIR__, 2));
        $editor = new EditContent($catalogue, new ContentStore($manager->getConnection()));
        $meta = $catalogue->fields('pl', 'home-seo');
        $meta['about.meta.title'] = 'My updated Polish title';
        $editor->save('pl', 'home-seo', $meta, 0, 'publish');
        $links = $catalogue->fields('pl', 'links');
        $links['links.obd_website'] = 'https://obd-vault.com/pl/';
        $editor->save('pl', 'links', $links, 1, 'publish');
        $this->client->request('GET', '/pl/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'My updated Polish title');
        self::assertSelectorExists('#obd a[href="https://obd-vault.com/pl/"]');
    }

    /** @param array<string, string> $fields */
    private function save(array $fields, int $revision, string $action): void
    {
        $this->client->request('POST', '/admin/content/en/obd', [
            '_token' => hash_hmac('sha256', 'csrf:content_en', $this->secret),
            'revision' => $revision, 'action' => $action, 'fields' => $fields,
        ], server: ['HTTP_X_ADMIN_TOKEN' => $this->secret]);
    }
}
