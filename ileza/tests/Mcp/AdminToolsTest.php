<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Analytics\Application\TrafficAnalytics;
use App\Analytics\Infrastructure\JsonlPageViewRepository;
use App\Lead\Domain\Lead;
use App\Lead\Domain\LeadRepository;
use App\Lead\Infrastructure\JsonlLeadRepository;
use App\Market\Application\ProductCatalog;
use App\Market\Domain\PriceObservation;
use App\Market\Domain\PriceObservationRepository;
use App\Market\Infrastructure\JsonPriceObservationRepository;
use App\Market\Infrastructure\JsonPriceTipRepository;
use App\Market\Infrastructure\JsonProductRequestStore;
use App\Market\Domain\PriceTipRepository;
use App\Market\Domain\ProductRequestStore;
use App\Mcp\AdminAccess;
use App\Mcp\AdminTools;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminToolsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/admin-mcp-test-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);
    }

    public function testAllAdminToolsFailClosedWithoutBearerToken(): void
    {
        $tools = $this->tools(false);

        $responses = [
            $tools->statistics(),
            $tools->contactLeads(),
            $tools->productRequests(),
            $tools->priceTips(),
        ];
        foreach ($responses as $json) {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            self::assertStringContainsString('Unauthorized', $data['error']);
        }
    }

    public function testAdminCanReadSubmissionsAndAggregateStatistics(): void
    {
        $leadRepository = new JsonlLeadRepository($this->directory . '/leads');
        $leadRepository->save(Lead::create(
            'person@example.com',
            '+48 500 000 000',
            'Please review my workflow.',
            'hashed-ip',
            'landing',
        ));

        $productRequests = new JsonProductRequestStore($this->directory . '/market', 'secret');
        $productRequests->save('PlayStation 5 Slim', 'person@example.com', '198.51.100.5');

        $priceTips = new JsonPriceTipRepository($this->directory . '/market', 'secret');
        $priceTips->submit(
            'iphone-13-128gb',
            'https://example.com/listing/123?tracking=removed',
            'person@example.com',
            '198.51.100.5',
        );

        $observations = new JsonPriceObservationRepository($this->directory . '/market');
        $observations->save(new PriceObservation(
            'iphone-13-128gb',
            new \DateTimeImmutable('now'),
            95000,
            83000,
            108000,
            8,
            'high',
            'Manual review',
            PriceObservation::METHODOLOGY_MANUAL,
        ));

        $tools = $this->tools(true, $leadRepository, $productRequests, $priceTips, $observations);
        $statistics = json_decode($tools->statistics(), true, flags: JSON_THROW_ON_ERROR);
        $leads = json_decode($tools->contactLeads(10), true, flags: JSON_THROW_ON_ERROR);
        $requests = json_decode($tools->productRequests(10), true, flags: JSON_THROW_ON_ERROR);
        $tips = json_decode($tools->priceTips(10), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $statistics['submissions']['contact_leads']['total']);
        self::assertSame(1, $statistics['submissions']['product_requests']['last_7_days']);
        self::assertSame(1, $statistics['submissions']['active_price_tips']['total']);
        self::assertGreaterThan(0, $statistics['market_coverage']['tracked_products']);
        self::assertSame(1, $statistics['market_coverage']['products_with_history']);
        self::assertSame(0, $statistics['traffic']['last_30_days']['page_views']);
        self::assertSame('person@example.com', $leads['items'][0]['email']);
        self::assertSame('PlayStation 5 Slim', $requests['items'][0]['product']);
        self::assertSame('https://example.com/listing/123', $tips['items'][0]['listing_url']);
        self::assertArrayNotHasKey('ip_hash', $leads['items'][0]);
        self::assertArrayNotHasKey('ip_hash', $tips['items'][0]);
    }

    public function testAdminListsRejectUnsafeResultLimits(): void
    {
        $tools = $this->tools(true);
        $data = json_decode($tools->contactLeads(101), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('Limit must be between 1 and 100.', $data['error']);
    }

    private function tools(
        bool $authenticated,
        ?LeadRepository $leads = null,
        ?ProductRequestStore $requests = null,
        ?PriceTipRepository $tips = null,
        ?PriceObservationRepository $observations = null,
    ): AdminTools {
        $requestStack = new RequestStack();
        $request = new Request();
        if ($authenticated) {
            $request->headers->set('Authorization', 'Bearer admin-test-token');
        }
        $requestStack->push($request);

        return new AdminTools(
            new AdminAccess($requestStack, 'admin-test-token'),
            $leads ?? new JsonlLeadRepository($this->directory . '/leads'),
            $requests ?? new JsonProductRequestStore($this->directory . '/market', 'secret'),
            $tips ?? new JsonPriceTipRepository($this->directory . '/market', 'secret'),
            new \App\Market\Application\GetMarketStatistics(
                new ProductCatalog(),
                $observations ?? new JsonPriceObservationRepository($this->directory . '/market')
            ),
            new TrafficAnalytics(new JsonlPageViewRepository($this->directory . '/analytics', 90)),
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}
