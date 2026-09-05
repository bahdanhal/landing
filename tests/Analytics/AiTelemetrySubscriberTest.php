<?php

declare(strict_types=1);

namespace App\Tests\Analytics;

use App\Analytics\Domain\AiInteraction;
use App\Analytics\Domain\AiInteractionRepository;
use App\Analytics\Infrastructure\AiTelemetrySubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AiTelemetrySubscriberTest extends TestCase
{
    public function testRecordsAiDocumentVisit(): void
    {
        $repo = $this->createMock(AiInteractionRepository::class);
        $repo->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (AiInteraction $interaction): bool {
                return $interaction->type === AiInteraction::TYPE_AI_DOCUMENT
                    && $interaction->identifier === '/llms.txt'
                    && $interaction->path === '/llms.txt';
            }));

        $subscriber = new AiTelemetrySubscriber($repo, 'test-secret');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/llms.txt', 'GET');
        $response = new Response('# llms.txt', 200, ['Content-Type' => 'text/plain']);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onResponse($event);
    }

    public function testRecordsAiCrawlerVisitWithPath(): void
    {
        $repo = $this->createMock(AiInteractionRepository::class);
        $repo->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (AiInteraction $interaction): bool {
                return $interaction->type === AiInteraction::TYPE_AI_CRAWLER
                    && $interaction->identifier === 'claudebot'
                    && $interaction->path === '/services';
            }));

        $subscriber = new AiTelemetrySubscriber($repo, 'test-secret');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/services', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
        ]);
        $response = new Response('<html>services</html>', 200, ['Content-Type' => 'text/html']);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onResponse($event);
    }

    public function testIgnoresStandardHumanBrowser(): void
    {
        $repo = $this->createMock(AiInteractionRepository::class);
        $repo->expects(self::never())->method('save');

        $subscriber = new AiTelemetrySubscriber($repo, 'test-secret');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        ]);
        $response = new Response('<html>home</html>', 200, ['Content-Type' => 'text/html']);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onResponse($event);
    }
}
