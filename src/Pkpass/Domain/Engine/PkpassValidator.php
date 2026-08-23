<?php

declare(strict_types=1);

namespace App\Pkpass\Domain\Engine;

use App\Pkpass\Domain\Model\PassType;
use App\Pkpass\Domain\Model\PassValidationResult;
use App\Pkpass\Domain\Model\ValidationFinding;
use App\Pkpass\Domain\Model\ValidationSeverity;

final class PkpassValidator
{
    private const array VALID_TRANSIT_TYPES = [
        'PKTransitTypeAir',
        'PKTransitTypeBoat',
        'PKTransitTypeBus',
        'PKTransitTypeGeneric',
        'PKTransitTypeTrain',
    ];

    private const array VALID_BARCODE_FORMATS = [
        'PKBarcodeFormatQR',
        'PKBarcodeFormatPDF417',
        'PKBarcodeFormatAztec',
        'PKBarcodeFormatCode128',
    ];

    private const array VALID_BARCODE_ENCODINGS = [
        'iso-8859-1',
        'utf-8',
    ];

    /**
     * @param array<string, mixed> $pass
     */
    public function validate(array $pass): PassValidationResult
    {
        $findings = [];

        // 1. Required top-level keys
        $requiredKeys = [
            'formatVersion' => 'formatVersion must be 1',
            'passTypeIdentifier' => 'passTypeIdentifier is required (e.g. pass.com.example.ticket)',
            'serialNumber' => 'serialNumber is required',
            'teamIdentifier' => 'teamIdentifier is required (10-character Apple Team ID)',
            'organizationName' => 'organizationName is required',
            'description' => 'description is required',
        ];

        foreach ($requiredKeys as $key => $message) {
            if (!isset($pass[$key]) || (is_string($pass[$key]) && trim($pass[$key]) === '')) {
                $findings[] = new ValidationFinding(
                    'ERR_MISSING_REQUIRED_KEY',
                    ValidationSeverity::Error,
                    'Missing Required Key',
                    $message,
                    $key
                );
            }
        }

        if (isset($pass['formatVersion']) && $pass['formatVersion'] !== 1) {
            $findings[] = new ValidationFinding(
                'ERR_INVALID_FORMAT_VERSION',
                ValidationSeverity::Error,
                'Invalid Format Version',
                'formatVersion must be integer 1',
                'formatVersion'
            );
        }

        if (isset($pass['passTypeIdentifier']) && is_string($pass['passTypeIdentifier'])) {
            if (!str_starts_with($pass['passTypeIdentifier'], 'pass.')) {
                $findings[] = new ValidationFinding(
                    'ERR_INVALID_PASS_TYPE_ID',
                    ValidationSeverity::Warning,
                    'Pass Type Identifier Prefix',
                    'passTypeIdentifier should start with "pass." prefix',
                    'passTypeIdentifier'
                );
            }
        }

        // 2. Pass Style dictionary detection
        $styleKeys = ['boardingPass', 'eventTicket', 'coupon', 'storeCard', 'generic'];
        $foundStyles = [];
        foreach ($styleKeys as $styleKey) {
            if (isset($pass[$styleKey]) && is_array($pass[$styleKey])) {
                $foundStyles[] = $styleKey;
            }
        }

        $passType = null;
        if (count($foundStyles) === 0) {
            $findings[] = new ValidationFinding(
                'ERR_INVALID_PASS_STYLE',
                ValidationSeverity::Error,
                'No Pass Style Defined',
                'Pass must declare exactly one pass style dictionary (boardingPass, eventTicket, coupon, storeCard, generic).'
            );
        } elseif (count($foundStyles) > 1) {
            $findings[] = new ValidationFinding(
                'ERR_INVALID_PASS_STYLE',
                ValidationSeverity::Error,
                'Multiple Pass Styles Defined',
                'Pass declares multiple styles (' . implode(', ', $foundStyles) . '). Only one style is allowed.'
            );
            $passType = PassType::tryFromKey($foundStyles[0]);
        } else {
            $passType = PassType::tryFromKey($foundStyles[0]);
        }

        // 3. Transit type for Boarding Pass
        if ($passType === PassType::BoardingPass) {
            $boardingDict = is_array($pass['boardingPass']) ? $pass['boardingPass'] : [];
            $transitType = $boardingDict['transitType'] ?? null;
            if (!is_string($transitType) || !in_array($transitType, self::VALID_TRANSIT_TYPES, true)) {
                $findings[] = new ValidationFinding(
                    'ERR_INVALID_TRANSIT_TYPE',
                    ValidationSeverity::Error,
                    'Invalid Transit Type',
                    'Boarding pass requires a valid transitType: ' . implode(', ', self::VALID_TRANSIT_TYPES),
                    'boardingPass.transitType'
                );
            }
        }

        // 4. Date validation with mandatory ISO 8601 timezone
        $dateKeys = ['expirationDate', 'relevantDate'];
        foreach ($dateKeys as $dateKey) {
            if (isset($pass[$dateKey]) && is_string($pass[$dateKey])) {
                $this->validateIsoDateWithTimezone($pass[$dateKey], $dateKey, $findings);
            }
        }

        // 5. Barcode validations
        $this->validateBarcodes($pass, $findings);

        // 6. Color validations and contrast calculation
        $this->validateColorsAndContrast($pass, $findings);

        // 7. Field structure checks
        if ($passType !== null && isset($pass[$passType->value]) && is_array($pass[$passType->value])) {
            $this->validateFields($pass[$passType->value], $passType->value, $findings);
        }

        $hasErrors = false;
        foreach ($findings as $finding) {
            if ($finding->severity === ValidationSeverity::Error) {
                $hasErrors = true;
                break;
            }
        }

        return new PassValidationResult(
            isValid: !$hasErrors,
            findings: $findings,
            passType: $passType,
            organizationName: is_string($pass['organizationName'] ?? null) ? (string) $pass['organizationName'] : null,
            passTypeIdentifier: is_string($pass['passTypeIdentifier'] ?? null)
                ? (string) $pass['passTypeIdentifier'] : null,
            teamIdentifier: is_string($pass['teamIdentifier'] ?? null) ? (string) $pass['teamIdentifier'] : null,
            serialNumber: is_string($pass['serialNumber'] ?? null) ? (string) $pass['serialNumber'] : null,
            description: is_string($pass['description'] ?? null) ? (string) $pass['description'] : null,
            metadata: [
                'has_barcodes' => isset($pass['barcodes']) || isset($pass['barcode']),
                'has_web_service' => isset($pass['webServiceURL']),
                'has_nfc' => isset($pass['nfc']),
                'has_locations' => isset($pass['locations']) && is_array($pass['locations']),
            ]
        );
    }

    /**
     * @param list<ValidationFinding> $findings
     */
    private function validateIsoDateWithTimezone(string $dateString, string $field, array &$findings): void
    {
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/';
        if (!preg_match($pattern, $dateString)) {
            $findings[] = new ValidationFinding(
                'ERR_INVALID_DATE_TIMEZONE',
                ValidationSeverity::Error,
                'Missing Date Timezone (ISO 8601)',
                "The date '{$dateString}' in '{$field}' does not contain an explicit timezone offset (e.g. +02:00 or Z). iOS drops passes with floating dates.",
                $field
            );
        }
    }

    /**
     * @param array<string, mixed> $pass
     * @param list<ValidationFinding> $findings
     */
    private function validateBarcodes(array $pass, array &$findings): void
    {
        $barcodes = [];
        if (isset($pass['barcodes']) && is_array($pass['barcodes'])) {
            $barcodes = $pass['barcodes'];
        } elseif (isset($pass['barcode']) && is_array($pass['barcode'])) {
            $barcodes = [$pass['barcode']];
        }

        foreach ($barcodes as $idx => $barcode) {
            if (!is_array($barcode)) {
                continue;
            }
            $format = $barcode['format'] ?? null;
            if (!is_string($format) || !in_array($format, self::VALID_BARCODE_FORMATS, true)) {
                $findings[] = new ValidationFinding(
                    'ERR_INVALID_BARCODE_FORMAT',
                    ValidationSeverity::Error,
                    'Invalid Barcode Format',
                    "Barcode index {$idx} has unsupported format: " . ($format ?? 'null'),
                    "barcodes[{$idx}].format"
                );
            }

            $encoding = strtolower((string) ($barcode['messageEncoding'] ?? 'iso-8859-1'));
            if (!in_array($encoding, self::VALID_BARCODE_ENCODINGS, true)) {
                $findings[] = new ValidationFinding(
                    'ERR_INVALID_BARCODE_ENCODING',
                    ValidationSeverity::Error,
                    'Unsupported Barcode Encoding',
                    "Barcode messageEncoding '{$encoding}' is unsupported. Use 'utf-8' or 'iso-8859-1'.",
                    "barcodes[{$idx}].messageEncoding"
                );
            }

            if (!isset($barcode['message']) || !is_string($barcode['message']) || $barcode['message'] === '') {
                $findings[] = new ValidationFinding(
                    'ERR_EMPTY_BARCODE_MESSAGE',
                    ValidationSeverity::Error,
                    'Empty Barcode Message',
                    "Barcode index {$idx} has an empty payload message.",
                    "barcodes[{$idx}].message"
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $pass
     * @param list<ValidationFinding> $findings
     */
    private function validateColorsAndContrast(array $pass, array &$findings): void
    {
        $bgColor = $pass['backgroundColor'] ?? null;
        $fgColor = $pass['foregroundColor'] ?? null;
        $labelColor = $pass['labelColor'] ?? null;

        $bgRgb = is_string($bgColor) ? $this->parseRgb($bgColor) : null;
        $fgRgb = is_string($fgColor) ? $this->parseRgb($fgColor) : null;
        $labelRgb = is_string($labelColor) ? $this->parseRgb($labelColor) : null;

        if ($bgRgb !== null && $fgRgb !== null) {
            $contrast = $this->calculateContrastRatio($bgRgb, $fgRgb);
            if ($contrast < 3.0) {
                $findings[] = new ValidationFinding(
                    'WARN_LOW_COLOR_CONTRAST',
                    ValidationSeverity::Warning,
                    'Low Foreground Contrast Ratio',
                    sprintf('Foreground contrast ratio against background is %.2f:1 (minimum 3.0:1 recommended for legibility).', $contrast),
                    'foregroundColor'
                );
            }
        }

        if ($bgRgb !== null && $labelRgb !== null) {
            $contrast = $this->calculateContrastRatio($bgRgb, $labelRgb);
            if ($contrast < 2.5) {
                $findings[] = new ValidationFinding(
                    'WARN_LOW_COLOR_CONTRAST',
                    ValidationSeverity::Warning,
                    'Low Label Contrast Ratio',
                    sprintf('Label contrast ratio against background is %.2f:1.', $contrast),
                    'labelColor'
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $styleDict
     * @param list<ValidationFinding> $findings
     */
    private function validateFields(array $styleDict, string $styleName, array &$findings): void
    {
        $fieldGroups = ['headerFields', 'primaryFields', 'secondaryFields', 'auxiliaryFields', 'backFields'];
        foreach ($fieldGroups as $group) {
            if (!isset($styleDict[$group]) || !is_array($styleDict[$group])) {
                continue;
            }
            foreach ($styleDict[$group] as $idx => $field) {
                if (!is_array($field)) {
                    continue;
                }
                if (!isset($field['key']) || !is_string($field['key']) || trim($field['key']) === '') {
                    $findings[] = new ValidationFinding(
                        'ERR_FIELD_MISSING_KEY',
                        ValidationSeverity::Error,
                        'Field Missing Key',
                        "Field at {$styleName}.{$group}[{$idx}] must have a non-empty string 'key'.",
                        "{$styleName}.{$group}[{$idx}].key"
                    );
                }
                if (!array_key_exists('value', $field)) {
                    $findings[] = new ValidationFinding(
                        'ERR_FIELD_MISSING_VALUE',
                        ValidationSeverity::Warning,
                        'Field Missing Value',
                        "Field at {$styleName}.{$group}[{$idx}] has no 'value' defined.",
                        "{$styleName}.{$group}[{$idx}].value"
                    );
                }
            }
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int}|null
     */
    private function parseRgb(string $color): ?array
    {
        if (preg_match('/^rgb\s*\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', trim($color), $matches)) {
            return [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
        }
        if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', trim($color), $matches)) {
            return [(int) hexdec($matches[1]), (int) hexdec($matches[2]), (int) hexdec($matches[3])];
        }
        return null;
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb1
     * @param array{0: int, 1: int, 2: int} $rgb2
     */
    public function calculateContrastRatio(array $rgb1, array $rgb2): float
    {
        $lum1 = $this->relativeLuminance($rgb1);
        $lum2 = $this->relativeLuminance($rgb2);

        $brightest = max($lum1, $lum2);
        $darkest = min($lum1, $lum2);

        return ($brightest + 0.05) / ($darkest + 0.05);
    }

    /**
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private function relativeLuminance(array $rgb): float
    {
        $rs = $rgb[0] / 255.0;
        $gs = $rgb[1] / 255.0;
        $bs = $rgb[2] / 255.0;

        $r = ($rs <= 0.03928) ? $rs / 12.92 : pow(($rs + 0.055) / 1.055, 2.4);
        $g = ($gs <= 0.03928) ? $gs / 12.92 : pow(($gs + 0.055) / 1.055, 2.4);
        $b = ($bs <= 0.03928) ? $bs / 12.92 : pow(($bs + 0.055) / 1.055, 2.4);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
