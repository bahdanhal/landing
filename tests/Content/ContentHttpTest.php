<?php

declare(strict_types=1);

namespace App\Tests\Content;

use App\Content\Infrastructure\ContentDefaults;
use App\Entity\SiteDocumentEntity;
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
        $manager->getConnection()->executeStatement('DROP TABLE IF EXISTS site_document');
        (new SchemaTool($manager))->createSchema([$manager->getClassMetadata(SiteDocumentEntity::class)]);
    }

    public function testEditorRequiresSignInAndCsrf(): void
    {
        $this->client->request('GET', '/admin/content');
        self::assertResponseRedirects('/admin');

        $this->client->request('GET', '/admin/content', server: ['HTTP_X_ADMIN_TOKEN' => $this->secret]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#ed-boot');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');

        $this->client->request('POST', '/admin/content', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(401);

        $this->client->request('POST', '/admin/content', server: [
            'HTTP_X_ADMIN_TOKEN' => $this->secret, 'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode(['action' => 'publish', 'revision' => 0, 'document' => $this->document()]));
        self::assertResponseStatusCodeSame(403);
    }

    public function testDraftIsPrivateUntilPublished(): void
    {
        $document = $this->document();
        $jobs = $document['collections']['experience'];
        $consulting = array_pop($jobs);
        $consulting['role']['en'] = 'Principal <script>alert(1)</script> Consultant';
        array_unshift($jobs, $consulting);
        $document['collections']['experience'] = $jobs;
        $document['collections']['faq'][] = [
            'id' => 'faq-new', 'question' => ['en' => 'Brand new question?', 'pl' => 'Nowe pytanie?'], 'answer' => ['en' => 'Yes.', 'pl' => 'Tak.'],
        ];
        $document['texts']['about.heading'] = ['en' => 'Edited heading', 'pl' => 'Edytowany nagłówek'];

        $state = $this->save('draft', $document, 0);
        self::assertSame(1, $state['revision']);

        $this->client->request('GET', '/resume');
        self::assertStringNotContainsString('Principal', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/resume?_content_preview=1');
        self::assertStringNotContainsString('Principal', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/resume?_content_preview=1', server: ['HTTP_X_ADMIN_TOKEN' => $this->secret]);
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Draft preview', $html);
        self::assertStringContainsString('Principal &lt;script&gt;alert(1)&lt;/script&gt; Consultant', $html);
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');

        $state = $this->save('publish', $state['draft'], 1);
        self::assertSame($state['draft'], $state['published']);

        $this->client->request('GET', '/resume');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>alert(1)', $html);
        self::assertLessThan(strpos($html, 'Web24'), strpos($html, 'Principal'), 'The reordered job comes first.');

        $this->client->request('GET', '/pl/uslugi');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Nowe pytanie?', $html);
        self::assertStringContainsString('"name":"Nowe pytanie?"', $html, 'FAQ structured data follows the list.');

        $this->client->request('GET', '/');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Edited heading', $html);
        self::assertStringNotContainsString('Principal', $html, 'Jobs without “Show on home page” stay off the home page.');
    }

    public function testStaleRevisionInvalidContentAndDiscard(): void
    {
        $document = $this->document();
        $this->save('draft', $document, 0);

        $this->post(['action' => 'draft', 'revision' => 0, 'document' => $document]);
        self::assertResponseStatusCodeSame(409);

        $document['collections']['projects'][0]['url'] = 'ftp://example.com';
        $response = $this->post(['action' => 'draft', 'revision' => 1, 'document' => $document]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Projects › project 1 › Website', (string) $response['error']);

        $document = $this->document();
        $document['collections']['facts'] = [];
        $state = $this->save('draft', $document, 1);
        self::assertSame([], $state['draft']['collections']['facts']);
        $state = $this->save('discard', [], 2);
        self::assertCount(4, $state['draft']['collections']['facts']);
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        return (new ContentDefaults(dirname(__DIR__, 2)))->document();
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array{draft: array<string, mixed>, published: array<string, mixed>, revision: int}
     */
    private function save(string $action, array $document, int $revision): array
    {
        $response = $this->post(['action' => $action, 'revision' => $revision, 'document' => $document]);
        self::assertResponseIsSuccessful();
        self::assertIsArray($response['state'] ?? null);

        /** @var array{draft: array<string, mixed>, published: array<string, mixed>, revision: int} */
        return $response['state'];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function post(array $payload): array
    {
        $this->client->request('POST', '/admin/content', server: [
            'HTTP_X_ADMIN_TOKEN' => $this->secret,
            'HTTP_X_CSRF_TOKEN' => hash_hmac('sha256', 'csrf:site_content', $this->secret),
            'CONTENT_TYPE' => 'application/json',
        ], content: (string) json_encode($payload));
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
