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
        self::assertArrayHasKey('public_tools', $data);
        self::assertArrayHasKey('send_message', $data['public_tools']);
        self::assertArrayNotHasKey('submit_contact_lead', $data['public_tools']);
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
        self::assertSame('send_message', $data['contact']['send_message_mcp_tool']);
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
        self::assertSame('Web24 sp. z o.o.', $data['experience'][0]['company']);
        self::assertSame('Sep 2026 - Present', $data['experience'][0]['period']);
        self::assertSame([], $data['experience'][0]['highlights']);
        self::assertSame('2026 - Sep 2026', $data['experience'][1]['period']);
        self::assertNotEmpty($data['skills']['backend_and_languages']);
        self::assertNotEmpty($data['languages']);

        $languageNames = array_column($data['languages'], 'language');
        self::assertContains('English', $languageNames);
        self::assertContains('Polish', $languageNames);
        self::assertContains('Belarusian', $languageNames);
        self::assertContains('Russian', $languageNames);
        self::assertSame('send_message', $data['contact']['send_message_tool']);
    }

    public function testSendMessageSavesValidInquiry(): void
    {
        $repo = $this->createMock(LeadRepository::class);
        $repo->expects(self::once())->method('save');

        $captureLead = new CaptureLead($repo, 'test-secret');
        $tools = new PortfolioPublicTools($captureLead);

        $json = $tools->sendMessage('client@example.com', '+48123456789', 'Looking for backend consulting');
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertTrue($data['success']);
    }

    public function testSendMessageRejectsEmptyContact(): void
    {
        $repo = $this->createStub(LeadRepository::class);
        $captureLead = new CaptureLead($repo, 'test-secret');
        $tools = new PortfolioPublicTools($captureLead);

        $json = $tools->sendMessage('', '', 'Hello');
        $data = json_decode($json, true);

        self::assertIsArray($data);
        self::assertFalse($data['success']);
        self::assertStringContainsString('Please provide at least an email', $data['error']);
    }
}
