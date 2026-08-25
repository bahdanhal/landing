<?php

declare(strict_types=1);

namespace App\Mcp\Http;

use Mcp\Server\Session\SessionStoreInterface;
use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Decorates the Symfony MCP controller to ensure single JSON-RPC requests
 * return a single JSON-RPC response object instead of a JSON array batch response wrapper,
 * while maintaining active MCP session resilience across server restarts.
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
        $requestBody = $request->getContent();
        if ($requestBody !== '' && !$this->isValidJson($requestBody)) {
            return $this->createParseErrorResponse();
        }

        $sessionLock = $this->acquireSessionLock($sessionIdHeader);
        try {
            return $this->handleLocked($request, $sessionIdHeader);
        } finally {
            $this->releaseSessionLock($sessionLock);
        }
    }

    private function handleLocked(Request $request, string $sessionIdHeader): Response
    {
        if ($this->sessionStore !== null && $sessionIdHeader !== '' && Uuid::isValid($sessionIdHeader)) {
            try {
                $uuid = Uuid::fromString($sessionIdHeader);
                $sessionData = $this->sessionStore->read($uuid);
                if ($sessionData === false || !$this->isValidJsonObject($sessionData)) {
                    $this->sessionStore->write($uuid, '{}');
                }
            } catch (\Throwable) {
                // Ignore malformed UUID here and allow protocol to validate
            }
        }

        $requestBody = $request->getContent();
        $singleRequest = null;
        $expectedId = null;
        $requestMethod = '';

        if ($requestBody !== '') {
            try {
                /** @var mixed $decodedRequest */
                $decodedRequest = json_decode($requestBody, true, 512, \JSON_THROW_ON_ERROR);
                if (is_array($decodedRequest) && !array_is_list($decodedRequest)) {
                    $singleRequest = $decodedRequest;
                    if (isset($decodedRequest['id']) && (is_string($decodedRequest['id']) || is_int($decodedRequest['id']))) {
                        $expectedId = $decodedRequest['id'];
                    }
                    if (isset($decodedRequest['method']) && is_string($decodedRequest['method'])) {
                        $requestMethod = $decodedRequest['method'];
                    }
                }
            } catch (\JsonException) {
                // Invalid JSON request, let original response pass through
            }
        }

        $response = $this->inner->handle($request);

        if (!$this->isJsonResponse($response)) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            if ($singleRequest !== null && $expectedId !== null) {
                return $this->createFallbackResponse($expectedId, $requestMethod);
            }

            return $response;
        }

        $trimmedContent = trim($content);
        if ($singleRequest === null || !str_starts_with($trimmedContent, '[') || !str_ends_with($trimmedContent, ']')) {
            return $response;
        }

        try {
            /** @var mixed $items */
            $items = json_decode($trimmedContent, true, 512, \JSON_THROW_ON_ERROR);
            if (!is_array($items)) {
                return $response;
            }

            $unwrapped = $this->extractSingleResponse($items, $expectedId);
            if ($unwrapped !== null) {
                $response->setContent(json_encode($unwrapped, \JSON_THROW_ON_ERROR));
            } elseif ($expectedId !== null) {
                return $this->createFallbackResponse($expectedId, $requestMethod);
            }
        } catch (\JsonException) {
            // Keep original response if JSON parsing fails
        }

        return $response;
    }

    private function isValidJson(string $payload): bool
    {
        try {
            json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

            return true;
        } catch (\JsonException) {
            return false;
        }
    }

    private function isValidJsonObject(string $payload): bool
    {
        try {
            $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

            return is_array($decoded) && !array_is_list($decoded);
        } catch (\JsonException) {
            return false;
        }
    }

    /** @return resource|null */
    private function acquireSessionLock(string $sessionId): mixed
    {
        if ($sessionId === '' || !Uuid::isValid($sessionId)) {
            return null;
        }

        $path = sys_get_temp_dir() . '/bahdan-mcp-session-' . hash('sha256', $sessionId) . '.lock';
        $lock = @fopen($path, 'c');
        if ($lock === false || !flock($lock, \LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return null;
        }

        return $lock;
    }

    /** @param resource|null $lock */
    private function releaseSessionLock(mixed $lock): void
    {
        if (!is_resource($lock)) {
            return;
        }

        flock($lock, \LOCK_UN);
        fclose($lock);
    }

    private function createParseErrorResponse(): Response
    {
        return new Response(
            json_encode([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32700, 'message' => 'Parse error'],
                'id' => null,
            ], \JSON_THROW_ON_ERROR),
            400,
            ['Content-Type' => 'application/json']
        );
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
    private function extractSingleResponse(array $items, string|int|null $expectedId): ?array
    {
        if (count($items) === 0) {
            return null;
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

    private function createFallbackResponse(string|int $id, string $method): Response
    {
        $result = match ($method) {
            'prompts/list' => ['prompts' => []],
            'tools/list' => ['tools' => []],
            'resources/list' => ['resources' => []],
            'resources/templates/list' => ['resourceTemplates' => []],
            default => new \stdClass(),
        };

        $payload = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];

        return new Response(
            json_encode($payload, \JSON_THROW_ON_ERROR),
            200,
            ['Content-Type' => 'application/json']
        );
    }
}
