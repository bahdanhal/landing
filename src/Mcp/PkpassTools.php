<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Pkpass\Application\PkpassInspector;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

final readonly class PkpassTools
{
    public function __construct(private PkpassInspector $inspector)
    {
    }

    #[McpTool(
        name: 'inspect_apple_pkpass',
        // phpcs:ignore Generic.Files.LineLength
        description: 'Inspect and validate Apple Wallet .pkpass JSON structure (pass.json) or package manifests: checks required keys, pass styles, dates, ISO 8601 timezones, transit types, barcodes, and color contrast.'
    )]
    public function inspectApplePkpass(
        #[Schema(description: 'The raw pass.json JSON string to inspect and validate.')]
        string $pass_json,
    ): string {
        try {
            $result = $this->inspector->inspectJson($pass_json);

            return $this->json([
                'status' => 'completed',
                'result' => $result->toArray(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
