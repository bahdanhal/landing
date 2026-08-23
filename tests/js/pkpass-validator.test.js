const assert = require('node:assert/strict');
const PkpassInspector = require('../../public/pkpass-inspector.js');

async function runTests() {
  console.log('Running Apple Wallet .pkpass Inspector JS tests...');

  // 1. SHA-1 Cryptographic Verification
  const textVector = 'The quick brown fox jumps over the lazy dog';
  const expectedHash = '2fd4e1c67a2d28fced849ee1bb76e7391b93eb12';
  const calculatedHash = PkpassInspector.sha1Sync(textVector);
  assert.equal(calculatedHash, expectedHash, 'SHA-1 vector must match NIST test vector');

  // 2. Validate Boarding Pass Preset
  const boardingResult = PkpassInspector.validatePassJson(PkpassInspector.PRESETS.boardingPass);
  assert.equal(boardingResult.isValid, true, 'Boarding pass preset must be valid');
  assert.equal(boardingResult.passType, 'boardingPass');
  assert.equal(boardingResult.errorCount, 0);

  // 3. Validate Event Ticket Preset
  const eventResult = PkpassInspector.validatePassJson(PkpassInspector.PRESETS.eventTicket);
  assert.equal(eventResult.isValid, true, 'Event ticket preset must be valid');
  assert.equal(eventResult.passType, 'eventTicket');

  // 4. Validate Store Card & Coupon Presets
  const storeResult = PkpassInspector.validatePassJson(PkpassInspector.PRESETS.storeCard);
  assert.equal(storeResult.isValid, true);
  assert.equal(storeResult.passType, 'storeCard');

  const couponResult = PkpassInspector.validatePassJson(PkpassInspector.PRESETS.coupon);
  assert.equal(couponResult.isValid, true);
  assert.equal(couponResult.passType, 'coupon');

  // 5. Broken Pass Error Detection
  const brokenResult = PkpassInspector.validatePassJson(PkpassInspector.PRESETS.brokenPass);
  assert.equal(brokenResult.isValid, false, 'Broken pass must fail validation');
  assert.ok(brokenResult.errorCount >= 3, 'Must detect multiple critical errors');

  const errorCodes = brokenResult.findings.map(f => f.code);
  assert.ok(errorCodes.includes('ERR_INVALID_DATE_TIMEZONE'), 'Must detect floating date without timezone');
  assert.ok(errorCodes.includes('ERR_INVALID_TRANSIT_TYPE'), 'Must detect invalid transit type');
  assert.ok(errorCodes.includes('WARN_LOW_COLOR_CONTRAST'), 'Must detect unreadable color contrast');

  // 6. WCAG Color Contrast Math
  const black = [0, 0, 0];
  const white = [255, 255, 255];
  const highContrast = PkpassInspector.calculateContrastRatio(black, white);
  assert.ok(Math.abs(highContrast - 21.0) < 0.1, 'Black on white contrast must be 21:1');

  const darkGrey = [30, 30, 30];
  const nearBlack = [20, 20, 20];
  const lowContrast = PkpassInspector.calculateContrastRatio(nearBlack, darkGrey);
  assert.ok(lowContrast < 1.5, 'Near identical colors must have contrast < 1.5:1');

  // 7. Google Wallet Mapping
  const googleWalletFlight = PkpassInspector.convertToGoogleWallet(PkpassInspector.PRESETS.boardingPass);
  assert.ok(googleWalletFlight.flightObject, 'Must produce flightObject');
  assert.equal(googleWalletFlight.flightObject.state, 'ACTIVE');
  assert.equal(googleWalletFlight.flightObject.reservationInfo.confirmationCode, 'LO-027-2026');

  const googleWalletLoyalty = PkpassInspector.convertToGoogleWallet(PkpassInspector.PRESETS.storeCard);
  assert.ok(googleWalletLoyalty.loyaltyObject, 'Must produce loyaltyObject');

  // 8. Localization Strings Parsing (.lproj)
  const stringsContent = `
    /* Header Comment */
    "ORIGIN" = "San Francisco";
    "DESTINATION" = "Warszawa";
    "GATE_LABEL" = "Bramka";
  `;
  const localized = PkpassInspector.parseStringsFile(stringsContent);
  assert.equal(localized['ORIGIN'], 'San Francisco');
  assert.equal(localized['DESTINATION'], 'Warszawa');
  assert.equal(localized['GATE_LABEL'], 'Bramka');

  // 9. In-Memory ZIP Archive Creation & Parsing
  const filesToPack = {
    'pass.json': JSON.stringify(PkpassInspector.PRESETS.generic),
    'manifest.json': JSON.stringify({ 'pass.json': PkpassInspector.sha1Sync(JSON.stringify(PkpassInspector.PRESETS.generic)) }),
    'en.lproj/pass.strings': '"HELLO" = "World";'
  };

  const packedZipBytes = PkpassInspector.createZipArchive(filesToPack);
  assert.ok(packedZipBytes.length > 50, 'Packed zip must contain bytes');

  const unpackedFiles = await PkpassInspector.parseZipArchive(packedZipBytes.buffer);
  assert.ok(unpackedFiles['pass.json'], 'Must extract pass.json');
  assert.ok(unpackedFiles['manifest.json'], 'Must extract manifest.json');
  assert.ok(unpackedFiles['en.lproj/pass.strings'], 'Must extract localized strings');

  const extractedPassJson = new TextDecoder('utf-8').decode(unpackedFiles['pass.json']);
  const parsedPass = JSON.parse(extractedPassJson);
  assert.equal(parsedPass.organizationName, 'Warsaw Tech Hub');

  console.log('All Apple Wallet .pkpass JS tests passed cleanly!');
}

runTests().catch(err => {
  console.error('Test failure:', err);
  process.exit(1);
});
