<?php

declare(strict_types=1);

namespace App\Mcp\Http;

use Mcp\Server\Session\SessionStoreInterface;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Decorates the Symfony MCP controller to ensure all MCP requests return valid JSON
 * objects with application/json content-type, prevents empty-body decoder issues,
 * and maintains active MCP session resilience across server restarts.
 */
final class McpControllerDecorator
{
    public function __construct(
        private readonly McpController $inner,
        private readonly ?SessionStoreInterface $sessionStore = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $sessionIdHeader = (string) $request->headers->get('Mcp-Session-Id', '');
        if ($this->sessionStore !== null && $sessionIdHeader !== '' && Uuid::isValid($sessionIdHeader)) {
            try {
                $uuid = Uuid::fromString($sessionIdHeader);
                if (!$this->sessionStore->exists($uuid)) {
                    $this->sessionStore->write($uuid, '{}');
                }
            } catch (\Throwable) {
                // Ignore malformed UUID here and allow protocol to validate
            }
        }

        $response = $this->inner->handle($request);

        if ($request->getMethod() === 'DELETE') {
            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent('{}');

            return $response;
        }

        $content = $response->getContent();
        if ($content === false || trim($content) === '') {
            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent('{}');

            return $response;
        }

        if (!$this->isJsonResponse($response)) {
            $response->setStatusCode(200);
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent('{"jsonrpc":"2.0","error":{"code":-32603,"message":"Internal Error"}}');

            return $response;
        }

        $trimmedContent = trim($content);
        if (!str_starts_with($trimmedContent, '[') || !str_ends_with($trimmedContent, ']')) {
            return $response;
        }

        $requestBody = $request->getContent();
        if ($requestBody !== '') {
            try {
                /** @var mixed $decodedRequest */
                $decodedRequest = json_decode($requestBody, true, 512, \JSON_THROW_ON_ERROR);
                if (is_array($decodedRequest) && array_is_list($decodedRequest)) {
                    return $response;
                }
            } catch (\JsonException) {
                // Invalid JSON request, let original response pass through
            }
        }

        try {
            /** @var mixed $items */
            $items = json_decode($trimmedContent, true, 512, \JSON_THROW_ON_ERROR);
            if (!is_array($items)) {
                return $response;
            }

            $unwrapped = $this->extractSingleResponse($items, $requestBody);
            if ($unwrapped !== null) {
                $response->setContent(json_encode($unwrapped, \JSON_THROW_ON_ERROR));
            }
        } catch (\JsonException) {
            // Keep original response if JSON parsing fails
        }

        return $response;
    }

    private function isJsonResponse(Response $response): bool
    {
        $contentType = (string) $response->headers->get('Content-Type', '');

        return str_contains(strtolower($contentType), 'application/json');
    }

    /**
     * @param array<mixed> $items
     * @return array<string, mixed>|null
     */
    private function extractSingleResponse(array $items, string $requestBody): ?array
    {
        if (count($items) === 0) {
            return null;
        }

        $expectedId = null;
        if ($requestBody !== '') {
            try {
                /** @var mixed $decodedRequest */
                $decodedRequest = json_decode($requestBody, true, 512, \JSON_THROW_ON_ERROR);
                if (is_array($decodedRequest) && isset($decodedRequest['id']) && (is_string($decodedRequest['id']) || is_int($decodedRequest['id']))) {
                    $expectedId = $decodedRequest['id'];
                }
            } catch (\JsonException) {
                // Ignore invalid request JSON
            }
        }

        if ($expectedId !== null) {
            foreach ($items as $item) {
                if (is_array($item) && isset($item['id']) && $item['id'] === $expectedId) {
                    /** @var array<string, mixed> $item */
                    return $item;
                }
            }
        }

        foreach ($items as $item) {
            if (is_array($item) && (isset($item['result']) || isset($item['error']) || isset($item['jsonrpc']))) {
                /** @var array<string, mixed> $item */
                return $item;
            }
        }

        $first = reset($items);

        /** @var array<string, mixed>|null */
        return is_array($first) ? $first : null;
    }
}
