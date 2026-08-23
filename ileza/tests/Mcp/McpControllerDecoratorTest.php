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
}
