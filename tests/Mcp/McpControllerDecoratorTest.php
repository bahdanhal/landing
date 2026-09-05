<?php

declare(strict_types=1);

namespace App\Tests\Mcp;

use App\Mcp\Http\McpControllerDecorator;
use Mcp\Server;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\AI\McpBundle\Http\MiddlewareFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class McpControllerDecoratorTest extends TestCase
{
    public function testUnwrapsArrayResponseForSingleRequest(): void
    {
        $server = Server::builder()->setServerInfo('test', '1.0.0')->build();
        $psr17Factory = new Psr17Factory();
        $httpMessageFactory = $this->createStub(HttpMessageFactoryInterface::class);
        $httpFoundationFactory = $this->createStub(HttpFoundationFactoryInterface::class);
        $middlewareFactory = new MiddlewareFactory([]);

        $psrRequest = $this->createStub(ServerRequestInterface::class);
        $psrRequest->method('getHeader')->willReturnCallback(static function (string $name): array {
            if ($name === 'Mcp-Session-Id') {
                return [];
            }
            if ($name === 'Host') {
                return ['localhost'];
            }
            return [];
        });
        $psrRequest->method('getMethod')->willReturn('POST');
        $psrRequest->method('getBody')->willReturn($psr17Factory->createStream(''));
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $innerResponse = new Response((string) json_encode([
            [
                'jsonrpc' => '2.0',
                'id' => 18,
                'result' => ['prompts' => []],
            ],
        ]), 200, ['Content-Type' => 'application/json']);

        $httpFoundationFactory->method('createResponse')->willReturn($innerResponse);

        $inner = new McpController(
            $server,
            $httpMessageFactory,
            $httpFoundationFactory,
            $psr17Factory,
            $psr17Factory,
            $middlewareFactory
        );

        $request = Request::create('/mcp', 'POST', [], [], [], [], (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 18,
            'method' => 'prompts/list',
            'params' => new \stdClass(),
        ]));

        $decorator = new McpControllerDecorator($inner);
        $response = $decorator->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 18, 'result' => ['prompts' => []]]),
            $response->getContent()
        );
    }

    public function testPreservesBatchArrayResponseForBatchRequest(): void
    {
        $server = Server::builder()->setServerInfo('test', '1.0.0')->build();
        $psr17Factory = new Psr17Factory();
        $httpMessageFactory = $this->createStub(HttpMessageFactoryInterface::class);
        $httpFoundationFactory = $this->createStub(HttpFoundationFactoryInterface::class);
        $middlewareFactory = new MiddlewareFactory([]);

        $psrRequest = $this->createStub(ServerRequestInterface::class);
        $psrRequest->method('getHeader')->willReturnCallback(static function (string $name): array {
            if ($name === 'Mcp-Session-Id') {
                return [];
            }
            if ($name === 'Host') {
                return ['localhost'];
            }
            return [];
        });
        $psrRequest->method('getMethod')->willReturn('POST');
        $psrRequest->method('getBody')->willReturn($psr17Factory->createStream(''));
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $batchResponsePayload = (string) json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'result' => []],
            ['jsonrpc' => '2.0', 'id' => 2, 'result' => []],
        ]);
        $innerResponse = new Response($batchResponsePayload, 200, ['Content-Type' => 'application/json']);

        $httpFoundationFactory->method('createResponse')->willReturn($innerResponse);

        $inner = new McpController(
            $server,
            $httpMessageFactory,
            $httpFoundationFactory,
            $psr17Factory,
            $psr17Factory,
            $middlewareFactory
        );

        $batchRequestPayload = (string) json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'prompts/list'],
        ]);
        $request = Request::create('/mcp', 'POST', [], [], [], [], $batchRequestPayload);

        $decorator = new McpControllerDecorator($inner);
        $response = $decorator->handle($request);

        self::assertSame($batchResponsePayload, $response->getContent());
    }

    public function testPassesThroughNonJsonResponse(): void
    {
        $server = Server::builder()->setServerInfo('test', '1.0.0')->build();
        $psr17Factory = new Psr17Factory();
        $httpMessageFactory = $this->createStub(HttpMessageFactoryInterface::class);
        $httpFoundationFactory = $this->createStub(HttpFoundationFactoryInterface::class);
        $middlewareFactory = new MiddlewareFactory([]);

        $psrRequest = $this->createStub(ServerRequestInterface::class);
        $psrRequest->method('getHeader')->willReturnCallback(static function (string $name): array {
            if ($name === 'Mcp-Session-Id') {
                return [];
            }
            if ($name === 'Host') {
                return ['localhost'];
            }
            return [];
        });
        $psrRequest->method('getMethod')->willReturn('GET');
        $psrRequest->method('getBody')->willReturn($psr17Factory->createStream(''));
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $innerResponse = new Response('event: message', 200, ['Content-Type' => 'text/event-stream']);
        $httpFoundationFactory->method('createResponse')->willReturn($innerResponse);

        $inner = new McpController(
            $server,
            $httpMessageFactory,
            $httpFoundationFactory,
            $psr17Factory,
            $psr17Factory,
            $middlewareFactory
        );

        $request = Request::create('/mcp', 'GET');
        $decorator = new McpControllerDecorator($inner);
        $response = $decorator->handle($request);

        self::assertSame('event: message', $response->getContent());
    }

    public function testReturnsFallbackResponseOnEmptyBodyForSingleRequest(): void
    {
        $server = Server::builder()->setServerInfo('test', '1.0.0')->build();
        $psr17Factory = new Psr17Factory();
        $httpMessageFactory = $this->createStub(HttpMessageFactoryInterface::class);
        $httpFoundationFactory = $this->createStub(HttpFoundationFactoryInterface::class);
        $middlewareFactory = new MiddlewareFactory([]);

        $psrRequest = $this->createStub(ServerRequestInterface::class);
        $psrRequest->method('getHeader')->willReturn([]);
        $psrRequest->method('getMethod')->willReturn('POST');
        $psrRequest->method('getBody')->willReturn($psr17Factory->createStream(''));
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $innerResponse = new Response('', 202, ['Content-Type' => 'application/json']);
        $httpFoundationFactory->method('createResponse')->willReturn($innerResponse);

        $inner = new McpController(
            $server,
            $httpMessageFactory,
            $httpFoundationFactory,
            $psr17Factory,
            $psr17Factory,
            $middlewareFactory
        );

        $request = Request::create('/mcp', 'POST', [], [], [], [], (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 99,
            'method' => 'prompts/list',
        ]));

        $decorator = new McpControllerDecorator($inner);
        $response = $decorator->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 99, 'result' => ['prompts' => []]]),
            $response->getContent()
        );
    }

    public function testReturnsFallbackResponseOnEmptyArrayForSingleRequest(): void
    {
        $server = Server::builder()->setServerInfo('test', '1.0.0')->build();
        $psr17Factory = new Psr17Factory();
        $httpMessageFactory = $this->createStub(HttpMessageFactoryInterface::class);
        $httpFoundationFactory = $this->createStub(HttpFoundationFactoryInterface::class);
        $middlewareFactory = new MiddlewareFactory([]);

        $psrRequest = $this->createStub(ServerRequestInterface::class);
        $psrRequest->method('getHeader')->willReturn([]);
        $psrRequest->method('getMethod')->willReturn('POST');
        $psrRequest->method('getBody')->willReturn($psr17Factory->createStream(''));
        $httpMessageFactory->method('createRequest')->willReturn($psrRequest);

        $innerResponse = new Response('[]', 200, ['Content-Type' => 'application/json']);
        $httpFoundationFactory->method('createResponse')->willReturn($innerResponse);

        $inner = new McpController(
            $server,
            $httpMessageFactory,
            $httpFoundationFactory,
            $psr17Factory,
            $psr17Factory,
            $middlewareFactory
        );

        $request = Request::create('/mcp', 'POST', [], [], [], [], (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 101,
            'method' => 'tools/list',
        ]));

        $decorator = new McpControllerDecorator($inner);
        $response = $decorator->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 101, 'result' => ['tools' => []]]),
            $response->getContent()
        );
    }

    public function testReturnsJsonRpcParseErrorForMalformedJson(): void
    {
        $server = Server::builder()->setServerInfo('test', '1.0.0')->build();
        $psr17Factory = new Psr17Factory();
        $inner = new McpController(
            $server,
            $this->createStub(HttpMessageFactoryInterface::class),
            $this->createStub(HttpFoundationFactoryInterface::class),
            $psr17Factory,
            $psr17Factory,
            new MiddlewareFactory([])
        );

        $response = (new McpControllerDecorator($inner))->handle(
            Request::create('/mcp', 'POST', [], [], [], [], '{invalid')
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            [
                'jsonrpc' => '2.0',
                'error' => ['code' => -32700, 'message' => 'Parse error'],
                'id' => null,
            ],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testRecordsNonAdminMcpToolCall(): void
    {
        $server = Server::builder()->setServerInfo('test', '1.0.0')->build();
        $psr17Factory = new Psr17Factory();
        $inner = new McpController(
            $server,
            $this->createStub(HttpMessageFactoryInterface::class),
            $this->createStub(HttpFoundationFactoryInterface::class),
            $psr17Factory,
            $psr17Factory,
            new MiddlewareFactory([])
        );

        $aiTelemetry = $this->createMock(\App\Analytics\Domain\AiInteractionRepository::class);
        $aiTelemetry->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (\App\Analytics\Domain\AiInteraction $interaction): bool {
                return $interaction->type === \App\Analytics\Domain\AiInteraction::TYPE_MCP_TOOL
                    && $interaction->identifier === 'get_services_and_pricing'
                    && $interaction->path === '/mcp';
            }));

        $decorator = new McpControllerDecorator($inner, null, $aiTelemetry, 'test-secret');
        $payload = (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'get_services_and_pricing'],
        ]);

        $decorator->handle(Request::create('/mcp', 'POST', [], [], [], [], $payload));
    }

    public function testDoesNotRecordAdminToolCalls(): void
    {
        $server = Server::builder()->setServerInfo('test', '1.0.0')->build();
        $psr17Factory = new Psr17Factory();
        $inner = new McpController(
            $server,
            $this->createStub(HttpMessageFactoryInterface::class),
            $this->createStub(HttpFoundationFactoryInterface::class),
            $psr17Factory,
            $psr17Factory,
            new MiddlewareFactory([])
        );

        $aiTelemetry = $this->createMock(\App\Analytics\Domain\AiInteractionRepository::class);
        $aiTelemetry->expects(self::never())->method('save');

        $decorator = new McpControllerDecorator($inner, null, $aiTelemetry, 'test-secret');

        // Admin call 1: get_admin_dashboard_statistics
        $payload1 = (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'get_admin_dashboard_statistics'],
        ]);
        $decorator->handle(Request::create('/mcp', 'POST', [], [], [], [], $payload1));

        // Admin call 2: list_admin_contact_leads
        $payload2 = (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'list_admin_contact_leads'],
        ]);
        $decorator->handle(Request::create('/mcp', 'POST', [], [], [], [], $payload2));
    }
}
