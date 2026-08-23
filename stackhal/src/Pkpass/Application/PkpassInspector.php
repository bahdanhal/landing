<?php

declare(strict_types=1);

namespace App\Pkpass\Application;

use App\Pkpass\Domain\Engine\PkpassValidator;
use App\Pkpass\Domain\Model\PassValidationResult;
use App\Pkpass\Domain\Model\ValidationFinding;
use App\Pkpass\Domain\Model\ValidationSeverity;

final readonly class PkpassInspector
{
    public function __construct(private PkpassValidator $validator)
    {
    }

    /**
     * Inspect and validate pass.json contents.
     *
     * @param string $passJsonContent
     * @return PassValidationResult
     */
    public function inspectJson(string $passJsonContent): PassValidationResult
    {
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($passJsonContent, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return new PassValidationResult(
                isValid: false,
                findings: [
                    new ValidationFinding(
                        'ERR_INVALID_JSON',
                        ValidationSeverity::Error,
                        'Invalid JSON Syntax',
                        $e->getMessage(),
                        null,
                        'pass.json'
                    ),
                ]
            );
        }

        return $this->validator->validate($data);
    }

    /**
     * Inspect manifest integrity against a simulated files list.
     *
     * @param array<string, string> $manifest Dictionary of [filename => sha1_hash]
     * @param array<string, string> $actualHashes Dictionary of [filename => actual_sha1_hash]
     * @return list<ValidationFinding>
     */
    public function verifyManifest(array $manifest, array $actualHashes): array
    {
        $findings = [];

        // Check for mismatches and missing files
        foreach ($manifest as $filename => $expectedHash) {
            if (!isset($actualHashes[$filename])) {
                $findings[] = new ValidationFinding(
                    'ERR_MISSING_ARCHIVE_FILE',
                    ValidationSeverity::Error,
                    'Missing Archive File',
                    "File '{$filename}' is declared in manifest.json but missing from the archive.",
                    null,
                    $filename
                );
                continue;
            }

            $actualHash = $actualHashes[$filename];
            if (strtolower($expectedHash) !== strtolower($actualHash)) {
                $findings[] = new ValidationFinding(
                    'ERR_MANIFEST_MISMATCH',
                    ValidationSeverity::Error,
                    'SHA-1 Manifest Mismatch',
                    "SHA-1 hash mismatch for '{$filename}'. Expected {$expectedHash}, computed {$actualHash}.",
                    null,
                    $filename
                );
            }
        }

        // Check for unmanifested files
        foreach ($actualHashes as $filename => $actualHash) {
            if ($filename === 'manifest.json' || $filename === 'signature') {
                continue;
            }
            if (!isset($manifest[$filename])) {
                $findings[] = new ValidationFinding(
                    'ERR_MISSING_MANIFEST_ENTRY',
                    ValidationSeverity::Error,
                    'File Not in Manifest',
                    "File '{$filename}' is present in the archive but omitted from manifest.json.",
                    null,
                    $filename
                );
            }
        }

        return $findings;
    }
}
