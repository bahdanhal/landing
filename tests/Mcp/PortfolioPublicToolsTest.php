<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Lead\Application\CaptureLead;
use App\Lead\Domain\LeadRepository;
use App\Mcp\PortfolioPublicTools;
use PHPUnit\Framework\TestCase;

final class PortfolioPublicToolsTest extends TestCase
{
    public function testOverviewReturnsValidJson(): void
    {
        $repo = $this->createStub(LeadRepository::class);
        $captureLead = new CaptureLead($repo, 'test-secret');
        $tools = new PortfolioPublicTools($captureLead);

        $json = $tools->overview();
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertSame('Bahdan Hal', $data['engineer']);
        self::assertArrayHasKey('ecosystem_projects', $data);
        self::assertArrayHasKey('pricing', $data);
        self::assertSame('€35/hour', $data['pricing']['standard_contract_rate']);
        self::assertSame('EUR', $data['pricing']['currency']);
    }

    public function testServicesAndPricingReturnsCatalogWithRates(): void
    {
        $repo = $this->createStub(LeadRepository::class);
        $captureLead = new CaptureLead($repo, 'test-secret');
        $tools = new PortfolioPublicTools($captureLead);

        $json = $tools->servicesAndPricing();
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertSame('Bahdan Hal', $data['engineer']);
        self::assertSame('€35/hour', $data['rates']['standard_contract_rate']);
        self::assertSame('EUR', $data['rates']['currency']);
        self::assertNotEmpty($data['services']);
        self::assertSame('turnkey_websites', $data['services'][0]['id']);
    }

    public function testCvAndSkillsReturnsStructuredCv(): void
    {
        $repo = $this->createStub(LeadRepository::class);
        $captureLead = new CaptureLead($repo, 'test-secret');
        $tools = new PortfolioPublicTools($captureLead);

        $json = $tools->cvAndSkills();
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertSame('Bahdan Hal', $data['engineer']);
        self::assertNotEmpty($data['experience']);
        self::assertNotEmpty($data['skills']['backend_and_languages']);
        self::assertNotEmpty($data['languages']);

        $languageNames = array_column($data['languages'], 'language');
        self::assertContains('English', $languageNames);
        self::assertContains('Polish', $languageNames);
        self::assertContains('Belarusian', $languageNames);
        self::assertContains('Russian', $languageNames);
    }

    public function testSubmitLeadSavesValidInquiry(): void
    {
        $repo = $this->createMock(LeadRepository::class);
        $repo->expects(self::once())->method('save');

        $captureLead = new CaptureLead($repo, 'test-secret');
        $tools = new PortfolioPublicTools($captureLead);

        $json = $tools->submitLead('client@example.com', '+48123456789', 'Looking for backend consulting');
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertTrue($data['success']);
    }

    public function testSubmitLeadRejectsEmptyContact(): void
    {
        $repo = $this->createStub(LeadRepository::class);
        $captureLead = new CaptureLead($repo, 'test-secret');
        $tools = new PortfolioPublicTools($captureLead);

        $json = $tools->submitLead('', '', 'Hello');
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertFalse($data['success']);
        self::assertStringContainsString('Please provide at least an email', $data['error']);
    }
}
