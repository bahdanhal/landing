<?php

declare(strict_types=1);

namespace App\Tests\Pkpass;

use App\Pkpass\Application\PkpassInspector;
use App\Pkpass\Domain\Engine\PkpassValidator;
use App\Pkpass\Domain\Model\PassType;
use App\Pkpass\Domain\Model\ValidationSeverity;
use PHPUnit\Framework\TestCase;

final class PkpassValidatorTest extends TestCase
{
    private PkpassValidator $validator;
    private PkpassInspector $inspector;

    protected function setUp(): void
    {
        $this->validator = new PkpassValidator();
        $this->inspector = new PkpassInspector($this->validator);
    }

    public function testValidBoardingPassPassesValidation(): void
    {
        $pass = [
            'formatVersion' => 1,
            'passTypeIdentifier' => 'pass.com.acme.travel',
            'serialNumber' => 'LOT-89421',
            'teamIdentifier' => 'ABCDE12345',
            'organizationName' => 'Acme Airlines',
            'description' => 'San Francisco to Warsaw Flight Pass',
            'backgroundColor' => 'rgb(15, 32, 67)',
            'foregroundColor' => 'rgb(255, 255, 255)',
            'labelColor' => 'rgb(148, 163, 184)',
            'boardingPass' => [
                'transitType' => 'PKTransitTypeAir',
                'headerFields' => [
                    ['key' => 'gate', 'label' => 'GATE', 'value' => 'B22'],
                ],
                'primaryFields' => [
                    ['key' => 'origin', 'label' => 'SAN FRANCISCO', 'value' => 'SFO'],
                    ['key' => 'destination', 'label' => 'WARSAW', 'value' => 'WAW'],
                ],
                'secondaryFields' => [
                    ['key' => 'passenger', 'label' => 'PASSENGER', 'value' => 'Bahdan Hal'],
                    ['key' => 'flight', 'label' => 'FLIGHT', 'value' => 'LO027'],
                ],
                'auxiliaryFields' => [
                    ['key' => 'boarding', 'label' => 'BOARDING', 'value' => '13:45'],
                    ['key' => 'seat', 'label' => 'SEAT', 'value' => '4A'],
                ],
            ],
            'barcodes' => [
                [
                    'format' => 'PKBarcodeFormatPDF417',
                    'message' => 'M1HAL/BAHDAN ELO027 123Y004A0012 100',
                    'messageEncoding' => 'iso-8859-1',
                ],
            ],
        ];

        $result = $this->validator->validate($pass);

        self::assertTrue($result->isValid);
        self::assertSame(0, $result->errorCount());
        self::assertSame(PassType::BoardingPass, $result->passType);
        self::assertSame('Acme Airlines', $result->organizationName);
        self::assertSame('LOT-89421', $result->serialNumber);
    }

    public function testMissingMandatoryKeysProducesErrors(): void
    {
        $pass = [
            'formatVersion' => 1,
        ];

        $result = $this->validator->validate($pass);

        self::assertFalse($result->isValid);
        self::assertGreaterThanOrEqual(4, $result->errorCount());
    }

    public function testInvalidDateTimezoneIsFlagged(): void
    {
        $pass = [
            'formatVersion' => 1,
            'passTypeIdentifier' => 'pass.com.acme.store',
            'serialNumber' => 'LOYALTY-1',
            'teamIdentifier' => 'ABCDE12345',
            'organizationName' => 'Store',
            'description' => 'Loyalty',
            'expirationDate' => '2026-08-23 14:30:00', // Missing T and timezone offset!
            'storeCard' => [
                'primaryFields' => [
                    ['key' => 'balance', 'label' => 'POINTS', 'value' => 500],
                ],
            ],
        ];

        $result = $this->validator->validate($pass);

        self::assertFalse($result->isValid);
        $codes = array_map(static fn ($f) => $f->code, $result->findings);
        self::assertContains('ERR_INVALID_DATE_TIMEZONE', $codes);
    }

    public function testLowColorContrastProducesWarning(): void
    {
        $pass = [
            'formatVersion' => 1,
            'passTypeIdentifier' => 'pass.com.acme.coupon',
            'serialNumber' => 'COUPON-1',
            'teamIdentifier' => 'ABCDE12345',
            'organizationName' => 'Retail Store',
            'description' => '20% Off Coupon',
            'backgroundColor' => 'rgb(30, 30, 30)',
            'foregroundColor' => 'rgb(40, 40, 40)', // Extremely low contrast!
            'coupon' => [
                'primaryFields' => [
                    ['key' => 'offer', 'label' => 'DISCOUNT', 'value' => '20% OFF'],
                ],
            ],
        ];

        $result = $this->validator->validate($pass);

        self::assertTrue($result->isValid); // Warnings don't make isValid false
        self::assertGreaterThanOrEqual(1, $result->warningCount());
        $codes = array_map(static fn ($f) => $f->code, $result->findings);
        self::assertContains('WARN_LOW_COLOR_CONTRAST', $codes);
    }

    public function testManifestIntegrityVerification(): void
    {
        $manifest = [
            'pass.json' => 'a1b2c3d4e5f6',
            'icon.png' => '112233445566',
            'logo.png' => '998877665544',
        ];

        $actual = [
            'pass.json' => 'a1b2c3d4e5f6', // Match
            'icon.png' => 'ffffffffffff', // Mismatch!
            // logo.png is missing from archive!
            'extra.png' => '1234567890ab', // Unmanifested file!
        ];

        $findings = $this->inspector->verifyManifest($manifest, $actual);

        self::assertCount(3, $findings);
        $codes = array_map(static fn ($f) => $f->code, $findings);
        self::assertContains('ERR_MANIFEST_MISMATCH', $codes);
        self::assertContains('ERR_MISSING_ARCHIVE_FILE', $codes);
        self::assertContains('ERR_MISSING_MANIFEST_ENTRY', $codes);
    }
}
