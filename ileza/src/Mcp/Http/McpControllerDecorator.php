<?php

declare(strict_types=1);

namespace App\Mcp\Http;

use Symfony\AI\McpBundle\Controller\McpController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decorates the Symfony MCP controller to ensure single JSON-RPC requests
 * return a single JSON-RPC response object instead of a JSON array batch response wrapper.
 */
final class McpControllerDecorator
{
    public function __construct(
        private readonly McpController $inner,
    ) {
    }

    public function handle(Request $request): Response
    {
        $response = $this->inner->handle($request);

        if (!$this->isJsonResponse($response)) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
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
