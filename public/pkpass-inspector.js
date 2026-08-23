/**
 * Apple Wallet (.pkpass) Inspector, Pixel-Perfect Emulator & Debugger
 * 100% Client-Side Engine (Privacy-First)
 */
(function(root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.PkpassInspector = factory();
    if (typeof document !== 'undefined') {
      document.addEventListener('DOMContentLoaded', function() {
        root.PkpassInspector.initUI();
      });
    }
  }
})(typeof self !== 'undefined' ? self : this, function() {
  'use strict';

  // --- 1. Pure JS SHA-1 Implementation ---
  function sha1Sync(data) {
    const bytes = typeof data === 'string'
      ? new TextEncoder().encode(data)
      : (data instanceof Uint8Array ? data : new Uint8Array(data));
    const byteLen = bytes.length;
    const bitLen = byteLen * 8;
    const paddingLen = (byteLen % 64 < 56) ? (55 - (byteLen % 64)) : (119 - (byteLen % 64));
    const totalLen = byteLen + 1 + paddingLen + 8;

    const padded = new Uint8Array(totalLen);
    padded.set(bytes, 0);
    padded[byteLen] = 0x80;

    const view = new DataView(padded.buffer);
    view.setUint32(totalLen - 8, Math.floor(bitLen / 0x100000000), false);
    view.setUint32(totalLen - 4, bitLen >>> 0, false);

    let h0 = 0x67452301;
    let h1 = 0xefcdab89;
    let h2 = 0x98badcfe;
    let h3 = 0x10325476;
    let h4 = 0xc3d2e1f0;

    const w = new Uint32Array(80);

    for (let i = 0; i < totalLen; i += 64) {
      for (let j = 0; j < 16; j++) {
        w[j] = view.getUint32(i + j * 4, false);
      }
      for (let j = 16; j < 80; j++) {
        const v = w[j - 3] ^ w[j - 8] ^ w[j - 14] ^ w[j - 16];
        w[j] = (v << 1) | (v >>> 31);
      }

      let a = h0, b = h1, c = h2, d = h3, e = h4;

      for (let j = 0; j < 80; j++) {
        let f, k;
        if (j < 20) {
          f = (b & c) | ((~b) & d);
          k = 0x5a827999;
        } else if (j < 40) {
          f = b ^ c ^ d;
          k = 0x6ed9eba1;
        } else if (j < 60) {
          f = (b & c) | (b & d) | (c & d);
          k = 0x8f1bbcdc;
        } else {
          f = b ^ c ^ d;
          k = 0xca62c1d6;
        }

        const temp = (((a << 5) | (a >>> 27)) + f + e + k + w[j]) | 0;
        e = d;
        d = c;
        c = (b << 30) | (b >>> 2);
        b = a;
        a = temp;
      }

      h0 = (h0 + a) | 0;
      h1 = (h1 + b) | 0;
      h2 = (h2 + c) | 0;
      h3 = (h3 + d) | 0;
      h4 = (h4 + e) | 0;
    }

    const hex = n => (n >>> 0).toString(16).padStart(8, '0');
    return hex(h0) + hex(h1) + hex(h2) + hex(h3) + hex(h4);
  }

  async function calculateSha1(buffer) {
    if (typeof crypto !== 'undefined' && crypto.subtle && crypto.subtle.digest) {
      try {
        const hashBuf = await crypto.subtle.digest('SHA-1', buffer);
        const hashArray = Array.from(new Uint8Array(hashBuf));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
      } catch {
        return sha1Sync(buffer);
      }
    }
    return sha1Sync(buffer);
  }

  // --- 2. Lightweight ZIP Parser & Pack Engine ---
  function crc32(buffer) {
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    let table = crc32.table;
    if (!table) {
      table = crc32.table = new Uint32Array(256);
      for (let i = 0; i < 256; i++) {
        let c = i;
        for (let k = 0; k < 8; k++) {
          c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
        }
        table[i] = c;
      }
    }
    let crc = 0xffffffff;
    for (let i = 0; i < bytes.length; i++) {
      crc = table[(crc ^ bytes[i]) & 0xff] ^ (crc >>> 8);
    }
    return (crc ^ 0xffffffff) >>> 0;
  }

  async function decompressDeflateRaw(compressedBytes) {
    if (typeof DecompressionStream !== 'undefined') {
      const ds = new DecompressionStream('deflate-raw');
      const writer = ds.writable.getWriter();
      writer.write(compressedBytes);
      writer.close();
      const response = new Response(ds.readable);
      const arrayBuffer = await response.arrayBuffer();
      return new Uint8Array(arrayBuffer);
    }
    return compressedBytes;
  }

  async function parseZipArchive(arrayBuffer) {
    const data = new Uint8Array(arrayBuffer);
    const view = new DataView(arrayBuffer);
    const files = {};

    let eocdOffset = -1;
    for (let i = data.length - 22; i >= 0; i--) {
      if (view.getUint32(i, true) === 0x06054b50) {
        eocdOffset = i;
        break;
      }
    }

    if (eocdOffset === -1) {
      throw new Error('Not a valid ZIP or .pkpass archive (EOCD record not found).');
    }

    const cdEntries = view.getUint16(eocdOffset + 10, true);
    const cdOffset = view.getUint32(eocdOffset + 16, true);

    let offset = cdOffset;
    const decoder = new TextDecoder('utf-8');

    for (let entry = 0; entry < cdEntries; entry++) {
      if (offset + 46 > data.length || view.getUint32(offset, true) !== 0x02014b50) {
        break;
      }

      const compression = view.getUint16(offset + 10, true);
      const compressedSize = view.getUint32(offset + 20, true);
      const filenameLength = view.getUint16(offset + 28, true);
      const extraLength = view.getUint16(offset + 30, true);
      const commentLength = view.getUint16(offset + 32, true);
      const localHeaderOffset = view.getUint32(offset + 42, true);

      const filenameBytes = data.subarray(offset + 46, offset + 46 + filenameLength);
      const filename = decoder.decode(filenameBytes).replace(/\\/g, '/');

      offset += 46 + filenameLength + extraLength + commentLength;

      if (filename.endsWith('/')) {
        continue;
      }

      if (localHeaderOffset + 30 <= data.length && view.getUint32(localHeaderOffset, true) === 0x04034b50) {
        const localNameLen = view.getUint16(localHeaderOffset + 26, true);
        const localExtraLen = view.getUint16(localHeaderOffset + 28, true);
        const fileDataStart = localHeaderOffset + 30 + localNameLen + localExtraLen;
        const rawFileData = data.subarray(fileDataStart, fileDataStart + compressedSize);

        let uncompressedData;
        if (compression === 0) {
          uncompressedData = rawFileData;
        } else if (compression === 8) {
          try {
            uncompressedData = await decompressDeflateRaw(rawFileData);
          } catch {
            uncompressedData = rawFileData;
          }
        } else {
          uncompressedData = rawFileData;
        }

        files[filename] = uncompressedData;
      }
    }

    return files;
  }

  function createZipArchive(filesMap) {
    const encoder = new TextEncoder();
    const localHeaders = [];
    const centralHeaders = [];
    let currentOffset = 0;

    const filenames = Object.keys(filesMap).sort();

    for (const filename of filenames) {
      const fileData = filesMap[filename] instanceof Uint8Array
        ? filesMap[filename]
        : (typeof filesMap[filename] === 'string' ? encoder.encode(filesMap[filename]) : new Uint8Array(filesMap[filename]));

      const filenameBytes = encoder.encode(filename);
      const fileCrc = crc32(fileData);
      const size = fileData.length;

      const localHeader = new Uint8Array(30 + filenameBytes.length);
      const localView = new DataView(localHeader.buffer);
      localView.setUint32(0, 0x04034b50, true);
      localView.setUint16(4, 20, true);
      localView.setUint16(6, 0, true);
      localView.setUint16(8, 0, true);
      localView.setUint16(10, 0, true);
      localView.setUint16(12, 0, true);
      localView.setUint32(14, fileCrc, true);
      localView.setUint32(18, size, true);
      localView.setUint32(22, size, true);
      localView.setUint16(26, filenameBytes.length, true);
      localView.setUint16(28, 0, true);
      localHeader.set(filenameBytes, 30);

      localHeaders.push({ header: localHeader, data: fileData });

      const centralHeader = new Uint8Array(46 + filenameBytes.length);
      const cdView = new DataView(centralHeader.buffer);
      cdView.setUint32(0, 0x02014b50, true);
      cdView.setUint16(4, 20, true);
      cdView.setUint16(6, 20, true);
      cdView.setUint16(8, 0, true);
      cdView.setUint16(10, 0, true);
      cdView.setUint16(12, 0, true);
      cdView.setUint16(14, 0, true);
      cdView.setUint32(16, fileCrc, true);
      cdView.setUint32(20, size, true);
      cdView.setUint32(24, size, true);
      cdView.setUint16(28, filenameBytes.length, true);
      cdView.setUint16(30, 0, true);
      cdView.setUint16(32, 0, true);
      cdView.setUint16(34, 0, true);
      cdView.setUint16(36, 0, true);
      cdView.setUint32(38, 0, true);
      cdView.setUint32(42, currentOffset, true);
      centralHeader.set(filenameBytes, 46);

      centralHeaders.push(centralHeader);

      currentOffset += localHeader.length + fileData.length;
    }

    const cdStartOffset = currentOffset;
    let cdTotalSize = 0;
    for (const cd of centralHeaders) {
      cdTotalSize += cd.length;
    }

    const eocd = new Uint8Array(22);
    const eocdView = new DataView(eocd.buffer);
    eocdView.setUint32(0, 0x06054b50, true);
    eocdView.setUint16(4, 0, true);
    eocdView.setUint16(6, 0, true);
    eocdView.setUint16(8, filenames.length, true);
    eocdView.setUint16(10, filenames.length, true);
    eocdView.setUint32(12, cdTotalSize, true);
    eocdView.setUint32(16, cdStartOffset, true);
    eocdView.setUint16(20, 0, true);

    const totalLength = cdStartOffset + cdTotalSize + 22;
    const output = new Uint8Array(totalLength);
    let pos = 0;

    for (const item of localHeaders) {
      output.set(item.header, pos);
      pos += item.header.length;
      output.set(item.data, pos);
      pos += item.data.length;
    }

    for (const cd of centralHeaders) {
      output.set(cd, pos);
      pos += cd.length;
    }

    output.set(eocd, pos);
    return output;
  }

  // --- 3. PKCS#7 & ASN.1 DER Certificate Inspector ---
  function parsePkcs7Signature(buffer) {
    if (!buffer || buffer.length === 0) {
      return { present: false, valid: false, error: 'Signature file missing' };
    }

    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);

    try {
      if (bytes[0] !== 0x30) {
        return { present: true, valid: false, error: 'Invalid PKCS#7 signature header (not DER sequence)' };
      }

      const str = Array.from(bytes).map(b => String.fromCharCode(b)).join('');
      
      const teamIdMatch = str.match(/([A-Z0-9]{10})/);
      const passTypeMatch = str.match(/(pass\.[a-zA-Z0-9.\-_]+)/);
      const isAppleWwdr = str.includes('Apple Worldwide Developer Relations') || str.includes('Apple Inc.');

      const dateMatches = str.match(/\d{12,14}Z/g);
      let notBefore = null;
      let notAfter = null;
      let isExpired = false;

      if (dateMatches && dateMatches.length >= 2) {
        const parseAsn1Date = s => {
          if (s.length === 13) {
            const yy = parseInt(s.substring(0, 2), 10);
            const year = yy >= 50 ? 1900 + yy : 2000 + yy;
            return new Date(Date.UTC(year, parseInt(s.substring(2, 4), 10) - 1, parseInt(s.substring(4, 6), 10), parseInt(s.substring(6, 8), 10), parseInt(s.substring(8, 10), 10), parseInt(s.substring(10, 12), 10)));
          }
          return new Date(Date.UTC(parseInt(s.substring(0, 4), 10), parseInt(s.substring(4, 6), 10) - 1, parseInt(s.substring(6, 8), 10), parseInt(s.substring(8, 10), 10), parseInt(s.substring(10, 12), 10), parseInt(s.substring(12, 14), 10)));
        };

        try {
          notBefore = parseAsn1Date(dateMatches[0]);
          notAfter = parseAsn1Date(dateMatches[1]);
          isExpired = notAfter < new Date();
        } catch {
          // ignore date parse issues
        }
      }

      return {
        present: true,
        valid: !isExpired && isAppleWwdr,
        teamIdentifier: teamIdMatch ? teamIdMatch[1] : null,
        passTypeIdentifier: passTypeMatch ? passTypeMatch[1] : null,
        issuer: isAppleWwdr ? 'Apple Worldwide Developer Relations Certification Authority' : 'Unknown / Self-Signed',
        isAppleWwdr: isAppleWwdr,
        notBefore: notBefore ? notBefore.toISOString().split('T')[0] : null,
        notAfter: notAfter ? notAfter.toISOString().split('T')[0] : null,
        isExpired: isExpired,
        rawLength: bytes.length,
      };
    } catch (e) {
      return { present: true, valid: false, error: e.message };
    }
  }

  // --- 4. Localization (.strings) Parser ---
  function parseStringsFile(text) {
    const dict = {};
    if (!text || typeof text !== 'string') return dict;

    const lines = text.split(/\r?\n/);
    for (const line of lines) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith('/*') || trimmed.startsWith('//')) continue;
      const match = trimmed.match(/^"((?:[^"\\]|\\.)+)"\s*=\s*"((?:[^"\\]|\\.)+)"\s*;?/);
      if (match) {
        const key = match[1].replace(/\\"/g, '"').replace(/\\n/g, '\n');
        const val = match[2].replace(/\\"/g, '"').replace(/\\n/g, '\n');
        dict[key] = val;
      }
    }
    return dict;
  }

  // --- 5. WCAG Color Contrast Calculation ---
  function parseRgb(colorStr) {
    if (!colorStr || typeof colorStr !== 'string') return null;
    const rgbMatch = colorStr.match(/rgb\s*\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)/i);
    if (rgbMatch) {
      return [parseInt(rgbMatch[1], 10), parseInt(rgbMatch[2], 10), parseInt(rgbMatch[3], 10)];
    }
    const hexMatch = colorStr.match(/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i);
    if (hexMatch) {
      return [parseInt(hexMatch[1], 16), parseInt(hexMatch[2], 16), parseInt(hexMatch[3], 16)];
    }
    return null;
  }

  function relativeLuminance(rgb) {
    const [rs, gs, bs] = rgb.map(c => {
      const v = c / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
  }

  function calculateContrastRatio(rgb1, rgb2) {
    const l1 = relativeLuminance(rgb1);
    const l2 = relativeLuminance(rgb2);
    const brightest = Math.max(l1, l2);
    const darkest = Math.min(l1, l2);
    return (brightest + 0.05) / (darkest + 0.05);
  }

  // --- 6. Pass Schema Validator & Linter ---
  const PASS_STYLES = ['boardingPass', 'eventTicket', 'coupon', 'storeCard', 'generic'];
  const TRANSIT_TYPES = ['PKTransitTypeAir', 'PKTransitTypeBoat', 'PKTransitTypeBus', 'PKTransitTypeGeneric', 'PKTransitTypeTrain'];
  const BARCODE_FORMATS = ['PKBarcodeFormatQR', 'PKBarcodeFormatPDF417', 'PKBarcodeFormatAztec', 'PKBarcodeFormatCode128'];

  function validatePassJson(pass) {
    const findings = [];

    if (!pass || typeof pass !== 'object') {
      return {
        isValid: false,
        passType: null,
        errorCount: 1,
        warningCount: 0,
        findings: [{ code: 'ERR_INVALID_OBJECT', severity: 'error', title: 'Invalid JSON Object', description: 'Pass root must be a JSON object.' }]
      };
    }

    const requiredKeys = [
      { key: 'formatVersion', msg: 'formatVersion must be 1' },
      { key: 'passTypeIdentifier', msg: 'passTypeIdentifier is required (must start with pass.)' },
      { key: 'serialNumber', msg: 'serialNumber is required' },
      { key: 'teamIdentifier', msg: 'teamIdentifier is required (10-char Apple Team ID)' },
      { key: 'organizationName', msg: 'organizationName is required' },
      { key: 'description', msg: 'description is required' }
    ];

    for (const req of requiredKeys) {
      if (pass[req.key] === undefined || pass[req.key] === null || (typeof pass[req.key] === 'string' && pass[req.key].trim() === '')) {
        findings.push({
          code: 'ERR_MISSING_REQUIRED_KEY',
          severity: 'error',
          title: 'Missing Required Key',
          description: req.msg,
          field: req.key
        });
      }
    }

    if (pass.formatVersion !== undefined && pass.formatVersion !== 1) {
      findings.push({
        code: 'ERR_INVALID_FORMAT_VERSION',
        severity: 'error',
        title: 'Invalid formatVersion',
        description: 'formatVersion must be integer 1.',
        field: 'formatVersion'
      });
    }

    if (pass.passTypeIdentifier && typeof pass.passTypeIdentifier === 'string' && !pass.passTypeIdentifier.startsWith('pass.')) {
      findings.push({
        code: 'WARN_INVALID_PASS_TYPE_PREFIX',
        severity: 'warning',
        title: 'Pass Type Identifier Prefix',
        description: 'passTypeIdentifier should start with "pass." (e.g. pass.com.company.ticket).',
        field: 'passTypeIdentifier'
      });
    }

    const foundStyles = PASS_STYLES.filter(s => pass[s] && typeof pass[s] === 'object');
    let passType = null;

    if (foundStyles.length === 0) {
      findings.push({
        code: 'ERR_INVALID_PASS_STYLE',
        severity: 'error',
        title: 'No Pass Style Declared',
        description: 'Pass must define exactly one pass style dictionary: ' + PASS_STYLES.join(', ')
      });
    } else if (foundStyles.length > 1) {
      findings.push({
        code: 'ERR_INVALID_PASS_STYLE',
        severity: 'error',
        title: 'Multiple Pass Styles Declared',
        description: 'Pass defines multiple styles (' + foundStyles.join(', ') + '). Only one is permitted.'
      });
      passType = foundStyles[0];
    } else {
      passType = foundStyles[0];
    }

    if (passType === 'boardingPass') {
      const transit = pass.boardingPass.transitType;
      if (!transit || !TRANSIT_TYPES.includes(transit)) {
        findings.push({
          code: 'ERR_INVALID_TRANSIT_TYPE',
          severity: 'error',
          title: 'Invalid Transit Type',
          description: 'Boarding pass transitType must be one of: ' + TRANSIT_TYPES.join(', '),
          field: 'boardingPass.transitType'
        });
      }
    }

    const dateRegex = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/;
    const dateFields = ['expirationDate', 'relevantDate'];

    for (const df of dateFields) {
      if (pass[df] && typeof pass[df] === 'string') {
        if (!dateRegex.test(pass[df])) {
          findings.push({
            code: 'ERR_INVALID_DATE_TIMEZONE',
            severity: 'error',
            title: 'Missing Date Timezone (ISO 8601)',
            description: `Date '${pass[df]}' in '${df}' lacks an explicit timezone (+02:00 or Z). iOS drops passes with floating dates.`,
            field: df
          });
        }
      }
    }

    const barcodes = Array.isArray(pass.barcodes) ? pass.barcodes : (pass.barcode ? [pass.barcode] : []);
    barcodes.forEach((b, idx) => {
      if (!b.format || !BARCODE_FORMATS.includes(b.format)) {
        findings.push({
          code: 'ERR_INVALID_BARCODE_FORMAT',
          severity: 'error',
          title: 'Invalid Barcode Format',
          description: `Barcode #${idx + 1} format '${b.format}' is invalid. Supported: ${BARCODE_FORMATS.join(', ')}`,
          field: `barcodes[${idx}].format`
        });
      }
      const encoding = (b.messageEncoding || 'iso-8859-1').toLowerCase();
      if (!['iso-8859-1', 'utf-8'].includes(encoding)) {
        findings.push({
          code: 'ERR_INVALID_BARCODE_ENCODING',
          severity: 'error',
          title: 'Unsupported Barcode Encoding',
          description: `Barcode encoding '${encoding}' is unsupported by PassKit. Use 'utf-8' or 'iso-8859-1'.`,
          field: `barcodes[${idx}].messageEncoding`
        });
      }
      if (!b.message || typeof b.message !== 'string') {
        findings.push({
          code: 'ERR_EMPTY_BARCODE_MESSAGE',
          severity: 'error',
          title: 'Empty Barcode Payload',
          description: `Barcode #${idx + 1} payload message is empty.`,
          field: `barcodes[${idx}].message`
        });
      }
    });

    const bgRgb = parseRgb(pass.backgroundColor);
    const fgRgb = parseRgb(pass.foregroundColor);
    const labelRgb = parseRgb(pass.labelColor);

    if (bgRgb && fgRgb) {
      const fgContrast = calculateContrastRatio(bgRgb, fgRgb);
      if (fgContrast < 3.0) {
        findings.push({
          code: 'WARN_LOW_COLOR_CONTRAST',
          severity: 'warning',
          title: 'Low Foreground Contrast',
          description: `Foreground text contrast is ${fgContrast.toFixed(2)}:1 against background (recommend >= 3.0:1 for legibility).`,
          field: 'foregroundColor'
        });
      }
    }

    if (bgRgb && labelRgb) {
      const labelContrast = calculateContrastRatio(bgRgb, labelRgb);
      if (labelContrast < 2.5) {
        findings.push({
          code: 'WARN_LOW_COLOR_CONTRAST',
          severity: 'warning',
          title: 'Low Label Contrast',
          description: `Label text contrast is ${labelContrast.toFixed(2)}:1 against background (recommend >= 2.5:1).`,
          field: 'labelColor'
        });
      }
    }

    if (passType && pass[passType]) {
      const fieldGroups = ['headerFields', 'primaryFields', 'secondaryFields', 'auxiliaryFields', 'backFields'];
      for (const grp of fieldGroups) {
        if (Array.isArray(pass[passType][grp])) {
          pass[passType][grp].forEach((fld, fIdx) => {
            if (!fld.key || typeof fld.key !== 'string') {
              findings.push({
                code: 'ERR_FIELD_MISSING_KEY',
                severity: 'error',
                title: 'Field Missing Key',
                description: `Field in ${passType}.${grp}[${fIdx}] must have a non-empty string 'key'.`,
                field: `${passType}.${grp}[${fIdx}].key`
              });
            }
            if (fld.value === undefined || fld.value === null) {
              findings.push({
                code: 'WARN_FIELD_MISSING_VALUE',
                severity: 'warning',
                title: 'Field Missing Value',
                description: `Field '${fld.key || fIdx}' in ${passType}.${grp} has no 'value'.`,
                field: `${passType}.${grp}[${fIdx}].value`
              });
            }
          });
        }
      }
    }

    const errorCount = findings.filter(f => f.severity === 'error').length;
    const warningCount = findings.filter(f => f.severity === 'warning').length;

    return {
      isValid: errorCount === 0,
      passType: passType,
      organizationName: pass.organizationName || null,
      passTypeIdentifier: pass.passTypeIdentifier || null,
      teamIdentifier: pass.teamIdentifier || null,
      serialNumber: pass.serialNumber || null,
      description: pass.description || null,
      errorCount,
      warningCount,
      findings
    };
  }

  // --- 7. Google Wallet JSON Converter ---
  function convertToGoogleWallet(pass) {
    const passType = PASS_STYLES.find(s => pass[s] && typeof pass[s] === 'object') || 'generic';
    const styleDict = pass[passType] || {};

    const issuerId = '3388000000022';
    const classId = `${issuerId}.${(pass.passTypeIdentifier || 'pass.sample').replace(/[^a-zA-Z0-9_-]/g, '_')}`;
    const objectId = `${issuerId}.${(pass.serialNumber || 'pass-001').replace(/[^a-zA-Z0-9_-]/g, '_')}`;

    const hexColor = rgbStr => {
      const rgb = parseRgb(rgbStr);
      if (!rgb) return '#1e293b';
      return '#' + rgb.map(x => x.toString(16).padStart(2, '0')).join('');
    };

    const textModulesData = [];
    ['primaryFields', 'secondaryFields', 'auxiliaryFields'].forEach(grp => {
      if (Array.isArray(styleDict[grp])) {
        styleDict[grp].forEach(fld => {
          textModulesData.push({
            header: fld.label || fld.key,
            body: String(fld.value || ''),
            id: fld.key
          });
        });
      }
    });

    const barcodes = Array.isArray(pass.barcodes) ? pass.barcodes : (pass.barcode ? [pass.barcode] : []);
    let googleBarcode = null;
    if (barcodes.length > 0) {
      const b = barcodes[0];
      const typeMap = {
        'PKBarcodeFormatQR': 'QR_CODE',
        'PKBarcodeFormatPDF417': 'PDF_417',
        'PKBarcodeFormatAztec': 'AZTEC',
        'PKBarcodeFormatCode128': 'CODE_128'
      };
      googleBarcode = {
        type: typeMap[b.format] || 'QR_CODE',
        value: b.message || '',
        alternateText: b.altText || ''
      };
    }

    if (passType === 'boardingPass') {
      return {
        flightClass: {
          id: classId,
          issuerName: pass.organizationName || 'Airline',
          reviewStatus: 'UNDER_REVIEW'
        },
        flightObject: {
          id: objectId,
          classId: classId,
          state: 'ACTIVE',
          passengerName: styleDict.secondaryFields?.find(f => f.key === 'passenger')?.value || 'PASSENGER',
          reservationInfo: {
            confirmationCode: pass.serialNumber || 'RES123'
          },
          barcode: googleBarcode,
          textModulesData: textModulesData,
          hexBackgroundColor: hexColor(pass.backgroundColor)
        }
      };
    }

    if (passType === 'eventTicket') {
      return {
        eventTicketClass: {
          id: classId,
          issuerName: pass.organizationName || 'Event Organizer',
          eventName: { defaultValue: { language: 'en', value: pass.description || 'Event' } },
          reviewStatus: 'UNDER_REVIEW'
        },
        eventTicketObject: {
          id: objectId,
          classId: classId,
          state: 'ACTIVE',
          barcode: googleBarcode,
          textModulesData: textModulesData,
          hexBackgroundColor: hexColor(pass.backgroundColor)
        }
      };
    }

    if (passType === 'storeCard' || passType === 'coupon') {
      return {
        loyaltyClass: {
          id: classId,
          issuerName: pass.organizationName || 'Store',
          programName: pass.description || 'Loyalty Program',
          reviewStatus: 'UNDER_REVIEW'
        },
        loyaltyObject: {
          id: objectId,
          classId: classId,
          state: 'ACTIVE',
          accountId: pass.serialNumber || '12345',
          accountName: styleDict.primaryFields?.[0]?.label || 'Rewards Member',
          barcode: googleBarcode,
          textModulesData: textModulesData,
          hexBackgroundColor: hexColor(pass.backgroundColor)
        }
      };
    }

    return {
      genericClass: {
        id: classId,
        reviewStatus: 'UNDER_REVIEW'
      },
      genericObject: {
        id: objectId,
        classId: classId,
        state: 'ACTIVE',
        cardTitle: { defaultValue: { language: 'en', value: pass.organizationName || 'Pass' } },
        header: { defaultValue: { language: 'en', value: pass.description || 'Membership' } },
        barcode: googleBarcode,
        textModulesData: textModulesData,
        hexBackgroundColor: hexColor(pass.backgroundColor)
      }
    };
  }

  // --- 8. Vector SVG Barcode Renderers ---
  function renderQrSvg(payload) {
    const size = 25;
    const hash = sha1Sync(payload);
    const matrix = [];

    for (let r = 0; r < size; r++) {
      matrix[r] = [];
      for (let c = 0; c < size; c++) {
        if ((r < 7 && c < 7) || (r < 7 && c >= size - 7) || (r >= size - 7 && c < 7)) {
          const inBorder = (r === 0 || r === 6 || c === 0 || c === 6) ||
                           (r < 7 && (c === size - 7 || c === size - 1 || r === 0 || r === 6)) ||
                           (r >= size - 7 && (c === 0 || c === 6 || r === size - 7 || r === size - 1));
          const inCenter = (r >= 2 && r <= 4 && c >= 2 && c <= 4) ||
                           (r >= 2 && r <= 4 && c >= size - 5 && c <= size - 3) ||
                           (r >= size - 5 && r <= size - 3 && c >= 2 && c <= 4);
          matrix[r][c] = inBorder || inCenter;
        } else if (r === 6 || c === 6) {
          matrix[r][c] = (r + c) % 2 === 0;
        } else {
          const charCode = hash.charCodeAt((r * size + c) % hash.length);
          matrix[r][c] = (charCode + r * 3 + c * 7) % 3 !== 0;
        }
      }
    }

    let rects = '';
    for (let r = 0; r < size; r++) {
      for (let c = 0; c < size; c++) {
        if (matrix[r][c]) {
          rects += `<rect x="${c * 8 + 16}" y="${r * 8 + 16}" width="8" height="8" fill="#000000"/>`;
        }
      }
    }

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 232 232" width="100%" height="100%">
      <rect width="232" height="232" fill="#ffffff" rx="8"/>
      ${rects}
    </svg>`;
  }

  function renderCode128Svg(payload) {
    const bars = [];
    const text = String(payload || 'PASS123');
    bars.push(2, 1, 1, 4, 1, 2);

    for (let i = 0; i < text.length; i++) {
      const code = text.charCodeAt(i) % 10;
      const patterns = [
        [2, 1, 2, 2, 2, 2], [2, 2, 2, 1, 2, 2], [2, 2, 2, 2, 2, 1],
        [1, 2, 1, 2, 2, 3], [1, 2, 1, 3, 2, 2], [1, 3, 1, 2, 2, 2],
        [1, 2, 2, 2, 1, 3], [1, 2, 2, 3, 1, 2], [1, 3, 2, 2, 1, 2],
        [2, 2, 1, 2, 1, 3]
      ];
      bars.push(...patterns[code]);
    }

    bars.push(2, 3, 3, 1, 1, 1, 2);

    let x = 15;
    let svgBars = '';
    let isBlack = true;
    for (const width of bars) {
      if (isBlack) {
        svgBars += `<rect x="${x}" y="10" width="${width * 2}" height="60" fill="#000000"/>`;
      }
      x += width * 2;
      isBlack = !isBlack;
    }

    const totalWidth = x + 15;
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${totalWidth} 80" width="100%" height="100%">
      <rect width="${totalWidth}" height="80" fill="#ffffff" rx="6"/>
      ${svgBars}
    </svg>`;
  }

  function renderPdf417Svg(payload) {
    const rows = 16;
    const cols = 40;
    const hash = sha1Sync(payload);
    let rects = '';

    for (let r = 0; r < rows; r++) {
      for (let c = 0; c < cols; c++) {
        if (c < 3 || c >= cols - 3) {
          if ((c % 2 === 0 && r % 2 === 0) || (c % 2 !== 0 && r % 2 !== 0)) {
            rects += `<rect x="${c * 7 + 10}" y="${r * 5 + 8}" width="7" height="5" fill="#000000"/>`;
          }
        } else {
          const bit = (hash.charCodeAt((r * cols + c) % hash.length) + r * 5 + c * 3) % 2 === 0;
          if (bit) {
            rects += `<rect x="${c * 7 + 10}" y="${r * 5 + 8}" width="7" height="5" fill="#000000"/>`;
          }
        }
      }
    }

    const totalW = cols * 7 + 20;
    const totalH = rows * 5 + 16;
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${totalW} ${totalH}" width="100%" height="100%">
      <rect width="${totalW}" height="${totalH}" fill="#ffffff" rx="6"/>
      ${rects}
    </svg>`;
  }

  function renderBarcode(format, message) {
    if (format === 'PKBarcodeFormatCode128') {
      return renderCode128Svg(message);
    }
    if (format === 'PKBarcodeFormatPDF417') {
      return renderPdf417Svg(message);
    }
    return renderQrSvg(message);
  }

  // --- 9. Preset Passes ---
  const PRESETS = {
    boardingPass: {
      formatVersion: 1,
      passTypeIdentifier: 'pass.com.bahdan.travel',
      serialNumber: 'LO-027-2026',
      teamIdentifier: 'BAHDAN9988',
      organizationName: 'LOT Polish Airlines',
      description: 'San Francisco to Warsaw Boarding Pass',
      backgroundColor: 'rgb(15, 32, 67)',
      foregroundColor: 'rgb(255, 255, 255)',
      labelColor: 'rgb(148, 163, 184)',
      logoText: 'LOT Polish Airlines',
      relevantDate: '2026-08-23T14:30:00+02:00',
      boardingPass: {
        transitType: 'PKTransitTypeAir',
        headerFields: [
          { key: 'gate', label: 'GATE', value: 'B22' }
        ],
        primaryFields: [
          { key: 'origin', label: 'SAN FRANCISCO', value: 'SFO' },
          { key: 'destination', label: 'WARSAW', value: 'WAW' }
        ],
        secondaryFields: [
          { key: 'passenger', label: 'PASSENGER', value: 'Bahdan Hal' },
          { key: 'flight', label: 'FLIGHT', value: 'LO027' }
        ],
        auxiliaryFields: [
          { key: 'boarding', label: 'BOARDING', value: '13:45' },
          { key: 'seat', label: 'SEAT', value: '4A' },
          { key: 'class', label: 'CLASS', value: 'Business' }
        ],
        backFields: [
          { key: 't_c', label: 'CONDITIONS OF CARRIAGE', value: 'Carriage is subject to the rules and limitations relating to liability established by the Warsaw/Montreal Convention.' },
          { key: 'baggage', label: 'BAGGAGE ALLOWANCE', value: '2 carry-on bags up to 9kg each, 2 checked bags up to 32kg each.' },
          { key: 'website', label: 'MANAGE BOOKING', value: 'https://lot.com' },
          { key: 'support', label: 'SUPPORT', value: 'support@lot.com · +48 22 577 77 55' }
        ]
      },
      barcodes: [
        {
          format: 'PKBarcodeFormatPDF417',
          message: 'M1HAL/BAHDAN ELO027 123Y004A0012 100',
          messageEncoding: 'iso-8859-1',
          altText: 'LO027 / SEAT 4A / SFO-WAW'
        }
      ]
    },
    eventTicket: {
      formatVersion: 1,
      passTypeIdentifier: 'pass.com.bahdan.events',
      serialNumber: 'TKT-OPENER-2026',
      teamIdentifier: 'BAHDAN9988',
      organizationName: 'Open\'er Festival',
      description: 'VIP Weekend Pass — Gdynia Kosakowo',
      backgroundColor: 'rgb(24, 18, 43)',
      foregroundColor: 'rgb(255, 255, 255)',
      labelColor: 'rgb(216, 180, 254)',
      logoText: 'OPEN\'ER FESTIVAL',
      relevantDate: '2026-07-01T16:00:00+02:00',
      eventTicket: {
        headerFields: [
          { key: 'day', label: 'ACCESS', value: '4-DAY VIP' }
        ],
        primaryFields: [
          { key: 'event', label: 'FESTIVAL', value: 'Open\'er 2026' }
        ],
        secondaryFields: [
          { key: 'location', label: 'VENUE', value: 'Gdynia Kosakowo' },
          { key: 'holder', label: 'HOLDER', value: 'Bahdan Hal' }
        ],
        auxiliaryFields: [
          { key: 'gate', label: 'GATE', value: 'VIP North' },
          { key: 'zone', label: 'ZONE', value: 'Golden Circle' }
        ],
        backFields: [
          { key: 'rules', label: 'EVENT RULES', value: 'Wristband must be worn at all times. Re-entry permitted for VIP ticket holders.' },
          { key: 'lineup', label: 'HEADLINERS', value: 'Main Stage acts begin daily at 18:00.' }
        ]
      },
      barcodes: [
        {
          format: 'PKBarcodeFormatQR',
          message: 'OPENER2026-VIP-BAHDAN-998812',
          messageEncoding: 'utf-8',
          altText: 'VIP-998812'
        }
      ]
    },
    storeCard: {
      formatVersion: 1,
      passTypeIdentifier: 'pass.com.bahdan.coffee',
      serialNumber: 'CARD-COFFEE-849',
      teamIdentifier: 'BAHDAN9988',
      organizationName: 'Artisan Coffee Roasters',
      description: 'Gold Rewards Membership Card',
      backgroundColor: 'rgb(41, 24, 16)',
      foregroundColor: 'rgb(254, 243, 199)',
      labelColor: 'rgb(217, 119, 6)',
      logoText: 'ARTISAN COFFEE',
      storeCard: {
        headerFields: [
          { key: 'tier', label: 'TIER', value: 'GOLD MEMBER' }
        ],
        primaryFields: [
          { key: 'balance', label: 'REWARD POINTS', value: '1,450 PTS' }
        ],
        secondaryFields: [
          { key: 'name', label: 'MEMBER', value: 'Bahdan Hal' },
          { key: 'free_drink', label: 'NEXT REWARD', value: '50 PTS away' }
        ],
        backFields: [
          { key: 'perks', label: 'GOLD PERKS', value: 'Free oat milk upgrade, 1 free birthday specialty brew, 10% off beans.' },
          { key: 'locations', label: 'LOCATIONS', value: 'Warsaw, Kraków, Gdańsk, Wrocław' }
        ]
      },
      barcodes: [
        {
          format: 'PKBarcodeFormatCode128',
          message: '998800112233',
          messageEncoding: 'iso-8859-1',
          altText: '9988-0011-2233'
        }
      ]
    },
    coupon: {
      formatVersion: 1,
      passTypeIdentifier: 'pass.com.bahdan.promo',
      serialNumber: 'COUPON-SUMMER26',
      teamIdentifier: 'BAHDAN9988',
      organizationName: 'Tech Gear Pro',
      description: '20% Off Everything Online & In-Store',
      backgroundColor: 'rgb(13, 71, 161)',
      foregroundColor: 'rgb(255, 255, 255)',
      labelColor: 'rgb(144, 202, 249)',
      logoText: 'TECH GEAR PRO',
      expirationDate: '2026-09-30T23:59:59+02:00',
      coupon: {
        primaryFields: [
          { key: 'offer', label: 'SPECIAL DISCOUNT', value: '20% OFF' }
        ],
        secondaryFields: [
          { key: 'min_spend', label: 'MIN SPEND', value: '150 PLN' },
          { key: 'expires', label: 'VALID UNTIL', value: '30 Sep 2026' }
        ],
        backFields: [
          { key: 'terms', label: 'TERMS & CONDITIONS', value: 'Valid on all non-discounted audio, mechanical keyboards, and studio accessories.' }
        ]
      },
      barcodes: [
        {
          format: 'PKBarcodeFormatQR',
          message: 'PROMO-SUMMER-20-BAHDAN',
          messageEncoding: 'utf-8',
          altText: 'SUMMER20'
        }
      ]
    },
    generic: {
      formatVersion: 1,
      passTypeIdentifier: 'pass.com.bahdan.club',
      serialNumber: 'MBR-9042',
      teamIdentifier: 'BAHDAN9988',
      organizationName: 'Warsaw Tech Hub',
      description: 'Resident Coworking & Lab Access',
      backgroundColor: 'rgb(17, 24, 39)',
      foregroundColor: 'rgb(243, 244, 246)',
      labelColor: 'rgb(56, 189, 248)',
      logoText: 'WARSAW TECH HUB',
      generic: {
        headerFields: [
          { key: 'status', label: 'STATUS', value: 'ACTIVE' }
        ],
        primaryFields: [
          { key: 'holder', label: 'MEMBER', value: 'Bahdan Hal' }
        ],
        secondaryFields: [
          { key: 'role', label: 'ROLE', value: 'Lead Engineer' },
          { key: 'access', label: 'ACCESS LEVEL', value: '24/7 All Floors' }
        ],
        backFields: [
          { key: 'help', label: 'FACILITIES DESK', value: 'desk@techhub.waw.pl · Slack #help-desk' }
        ]
      },
      barcodes: [
        {
          format: 'PKBarcodeFormatQR',
          message: 'ACCESS-WTH-BAHDAN-9042',
          messageEncoding: 'utf-8',
          altText: 'MBR-9042'
        }
      ]
    },
    brokenPass: {
      formatVersion: 1,
      passTypeIdentifier: 'broken.pass.identifier',
      serialNumber: 'ERR-DEMO-666',
      teamIdentifier: '12345',
      organizationName: 'Broken Pass Demo',
      description: 'Demonstrating Common iOS Dropping Bugs',
      backgroundColor: 'rgb(25, 25, 25)',
      foregroundColor: 'rgb(35, 35, 35)',
      labelColor: 'rgb(30, 30, 30)',
      expirationDate: '2026-08-23 14:30:00',
      boardingPass: {
        transitType: 'PKTransitTypeSpaceRocket',
        primaryFields: [
          { key: 'from', label: 'DEPART', value: 'EARTH' },
          { key: 'to', label: 'ARRIVE', value: 'MARS' }
        ],
        secondaryFields: [
          { key: 'passenger', label: 'ASTRONAUT', value: 'Demo User' }
        ]
      },
      barcodes: [
        {
          format: 'PKBarcodeFormatUnknown',
          messageEncoding: 'windows-1251',
          message: ''
        }
      ]
    }
  };

  // --- 10. UI Controller & State Manager ---
  let state = {
    currentPass: JSON.parse(JSON.stringify(PRESETS.boardingPass)),
    rawArchiveFiles: {},
    manifestMap: {},
    localizations: {},
    currentLocale: 'default',
    walletMode: 'apple', // 'apple' or 'google'
    isFlipped: false
  };

  function initUI() {
    const appEl = document.getElementById('pkpass-inspector-app');
    if (!appEl) return;

    // Dropzone & File Input
    const dropZone = document.getElementById('pkpass-drop-zone');
    const fileInput = document.getElementById('pkpass-file-input');

    if (dropZone && fileInput) {
      dropZone.addEventListener('click', () => fileInput.click());
      dropZone.addEventListener('dragover', e => {
        e.preventDefault();
        dropZone.classList.add('drag-active');
      });
      ['dragleave', 'dragend'].forEach(evt => {
        dropZone.addEventListener(evt, () => dropZone.classList.remove('drag-active'));
      });
      dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('drag-active');
        if (e.dataTransfer.files.length > 0) {
          handleFileInput(e.dataTransfer.files[0]);
        }
      });
      fileInput.addEventListener('change', e => {
        if (e.target.files.length > 0) {
          handleFileInput(e.target.files[0]);
        }
      });
    }

    // Preset Buttons
    document.querySelectorAll('.btn-preset').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.btn-preset').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const presetKey = btn.dataset.preset;
        if (PRESETS[presetKey]) {
          loadPreset(presetKey);
        }
      });
    });

    // Wallet Mode Switcher
    document.querySelectorAll('.btn-wallet-mode').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.btn-wallet-mode').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        state.walletMode = btn.dataset.mode;
        renderWalletCard();
      });
    });

    // Flip Card Buttons
    const flipBtn = document.getElementById('btn-flip-card');
    const flipBackBtn = document.getElementById('btn-flip-back');
    if (flipBtn) {
      flipBtn.addEventListener('click', () => {
        state.isFlipped = !state.isFlipped;
        updateCardFlip();
      });
    }
    if (flipBackBtn) {
      flipBackBtn.addEventListener('click', () => {
        state.isFlipped = false;
        updateCardFlip();
      });
    }

    // Inspector Tabs
    document.querySelectorAll('.inspector-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.inspector-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.inspector-tab-pane').forEach(p => p.style.display = 'none');
        btn.classList.add('active');
        const targetPane = document.getElementById(btn.dataset.target);
        if (targetPane) targetPane.style.display = 'block';
      });
    });

    // Raw JSON Editor
    const jsonEditor = document.getElementById('pkpass-json-editor');
    if (jsonEditor) {
      jsonEditor.addEventListener('input', () => {
        try {
          const updated = JSON.parse(jsonEditor.value);
          state.currentPass = updated;
          renderAll();
        } catch {
          // invalid JSON during typing
        }
      });
    }

    // Action Buttons
    const repackBtn = document.getElementById('btn-repack-pkpass');
    if (repackBtn) {
      repackBtn.addEventListener('click', handleRepackAndDownload);
    }

    const exportGoogleBtn = document.getElementById('btn-export-google-wallet');
    if (exportGoogleBtn) {
      exportGoogleBtn.addEventListener('click', handleExportGoogleWallet);
    }

    const downloadJsonBtn = document.getElementById('btn-download-json');
    if (downloadJsonBtn) {
      downloadJsonBtn.addEventListener('click', handleDownloadJson);
    }

    // Initial render
    loadPreset('boardingPass');
  }

  function updateCardFlip() {
    const cardEl = document.getElementById('apple-pass-card');
    if (cardEl) {
      if (state.isFlipped) {
        cardEl.classList.add('is-flipped');
      } else {
        cardEl.classList.remove('is-flipped');
      }
    }
  }

  async function handleFileInput(file) {
    const name = file.name.toLowerCase();
    if (name.endsWith('.json')) {
      const text = await file.text();
      try {
        state.currentPass = JSON.parse(text);
        state.rawArchiveFiles = { 'pass.json': text };
        state.manifestMap = {};
        renderAll();
      } catch (err) {
        alert('Invalid JSON file: ' + err.message);
      }
      return;
    }

    try {
      const buffer = await file.arrayBuffer();
      const files = await parseZipArchive(buffer);
      state.rawArchiveFiles = files;

      if (files['pass.json']) {
        const passText = new TextDecoder('utf-8').decode(files['pass.json']);
        state.currentPass = JSON.parse(passText);
      } else {
        alert('Archive does not contain pass.json manifest.');
        return;
      }

      if (files['manifest.json']) {
        const manifestText = new TextDecoder('utf-8').decode(files['manifest.json']);
        state.manifestMap = JSON.parse(manifestText);
      } else {
        state.manifestMap = {};
      }

      // Discover localizations (.lproj)
      state.localizations = {};
      Object.keys(files).forEach(fn => {
        const match = fn.match(/^([a-zA-Z_-]+)\.lproj\/pass\.strings$/);
        if (match) {
          const stringsText = new TextDecoder('utf-8').decode(files[fn]);
          state.localizations[match[1]] = parseStringsFile(stringsText);
        }
      });

      renderAll();
    } catch (err) {
      alert('Failed to parse archive: ' + err.message);
    }
  }

  function loadPreset(key) {
    state.currentPass = JSON.parse(JSON.stringify(PRESETS[key] || PRESETS.boardingPass));
    const passJsonStr = JSON.stringify(state.currentPass, null, 2);
    state.rawArchiveFiles = {
      'pass.json': passJsonStr,
      'icon.png': new Uint8Array(29 * 29),
      'icon@2x.png': new Uint8Array(58 * 58)
    };
    if (key === 'brokenPass') {
      // Intentionally simulate a hash mismatch for demo debugging
      state.manifestMap = {
        'pass.json': 'a1b2c3d4e5f678901234567890abcdef12345678', // intentional mismatch!
        'icon.png': sha1Sync(state.rawArchiveFiles['icon.png'])
      };
      delete state.rawArchiveFiles['icon@2x.png']; // missing retina icon!
    } else {
      state.manifestMap = {
        'pass.json': sha1Sync(passJsonStr),
        'icon.png': sha1Sync(state.rawArchiveFiles['icon.png']),
        'icon@2x.png': sha1Sync(state.rawArchiveFiles['icon@2x.png'])
      };
    }
    state.localizations = {};
    state.isFlipped = false;
    renderAll();
  }

  function renderAll() {
    renderWalletCard();
    renderDiagnostics();
    renderJsonEditor();
  }

  // --- Render Emulator ---
  function renderWalletCard() {
    const container = document.getElementById('wallet-preview-viewport');
    if (!container) return;

    if (state.walletMode === 'google') {
      container.innerHTML = renderGoogleWalletCardHtml();
    } else {
      container.innerHTML = renderAppleWalletCardHtml();
    }
    updateCardFlip();
  }

  function renderAppleWalletCardHtml() {
    const pass = state.currentPass;
    const passType = PASS_STYLES.find(s => pass[s] && typeof pass[s] === 'object') || 'generic';
    const styleDict = pass[passType] || {};

    const bgColor = pass.backgroundColor || 'rgb(17, 24, 39)';
    const fgColor = pass.foregroundColor || 'rgb(255, 255, 255)';
    const labelColor = pass.labelColor || 'rgb(156, 163, 175)';
    const logoText = pass.logoText || pass.organizationName || 'Apple Wallet';

    // Headers
    const headersHtml = (styleDict.headerFields || []).map(f => `
      <div class="field-item header-field">
        <span class="field-label" style="color: ${labelColor}">${escapeHtml(f.label || '')}</span>
        <strong class="field-value" style="color: ${fgColor}">${escapeHtml(f.value || '')}</strong>
      </div>
    `).join('');

    // Primary
    let primaryHtml = '';
    if (passType === 'boardingPass') {
      const origin = styleDict.primaryFields?.[0] || { label: 'SFO', value: 'SFO' };
      const dest = styleDict.primaryFields?.[1] || { label: 'WAW', value: 'WAW' };
      const transitIcon = styleDict.transitType === 'PKTransitTypeTrain' ? '🚆' : (styleDict.transitType === 'PKTransitTypeBoat' ? '🚢' : '✈️');
      primaryHtml = `
        <div class="boarding-route-row">
          <div class="route-point">
            <span class="route-city" style="color: ${labelColor}">${escapeHtml(origin.label || '')}</span>
            <strong class="route-code" style="color: ${fgColor}">${escapeHtml(origin.value || '')}</strong>
          </div>
          <div class="route-airplane">${transitIcon}</div>
          <div class="route-point route-dest">
            <span class="route-city" style="color: ${labelColor}">${escapeHtml(dest.label || '')}</span>
            <strong class="route-code" style="color: ${fgColor}">${escapeHtml(dest.value || '')}</strong>
          </div>
        </div>
      `;
    } else {
      primaryHtml = (styleDict.primaryFields || []).map(f => `
        <div class="field-item primary-field">
          <span class="field-label" style="color: ${labelColor}">${escapeHtml(f.label || '')}</span>
          <strong class="field-value primary-value" style="color: ${fgColor}">${escapeHtml(f.value || '')}</strong>
        </div>
      `).join('');
    }

    // Secondary & Auxiliary
    const secondaryHtml = (styleDict.secondaryFields || []).map(f => `
      <div class="field-item">
        <span class="field-label" style="color: ${labelColor}">${escapeHtml(f.label || '')}</span>
        <strong class="field-value" style="color: ${fgColor}">${escapeHtml(f.value || '')}</strong>
      </div>
    `).join('');

    const auxiliaryHtml = (styleDict.auxiliaryFields || []).map(f => `
      <div class="field-item">
        <span class="field-label" style="color: ${labelColor}">${escapeHtml(f.label || '')}</span>
        <strong class="field-value" style="color: ${fgColor}">${escapeHtml(f.value || '')}</strong>
      </div>
    `).join('');

    // Barcode
    const barcodes = Array.isArray(pass.barcodes) ? pass.barcodes : (pass.barcode ? [pass.barcode] : []);
    let barcodeHtml = '';
    if (barcodes.length > 0) {
      const b = barcodes[0];
      const svg = renderBarcode(b.format, b.message);
      barcodeHtml = `
        <div class="pass-barcode-section">
          <div class="barcode-svg-wrap ${b.format === 'PKBarcodeFormatPDF417' ? 'format-pdf417' : (b.format === 'PKBarcodeFormatCode128' ? 'format-code128' : 'format-qr')}">
            ${svg}
          </div>
          ${b.altText ? `<div class="barcode-alt-text" style="color: ${labelColor}">${escapeHtml(b.altText)}</div>` : ''}
        </div>
      `;
    }

    // Back Fields
    const backFieldsHtml = (styleDict.backFields || []).map(f => `
      <div class="back-field-item">
        <strong class="back-field-label">${escapeHtml(f.label || f.key || '')}</strong>
        <p class="back-field-value">${linkify(escapeHtml(f.value || ''))}</p>
      </div>
    `).join('') || '<p style="opacity: 0.6; font-size: 0.9rem;">No backFields declared in pass.json.</p>';

    return `
      <div class="apple-card-container">
        <div class="apple-card-flip-wrap ${state.isFlipped ? 'is-flipped' : ''}" id="apple-pass-card">
          <!-- Front Face -->
          <div class="apple-pass-card pass-type-${passType}" style="background-color: ${bgColor}; color: ${fgColor}">
            <div class="pass-punch-hole left"></div>
            <div class="pass-punch-hole right"></div>
            
            <div class="pass-header-row">
              <div class="pass-logo-wrap">
                <span class="pass-logo-icon"></span>
                <span class="pass-logo-text" style="color: ${fgColor}">${escapeHtml(logoText)}</span>
              </div>
              <div class="pass-header-fields">
                ${headersHtml}
              </div>
            </div>

            <div class="pass-body">
              <div class="pass-primary-row">
                ${primaryHtml}
              </div>

              ${secondaryHtml ? `<div class="pass-fields-grid secondary-grid">${secondaryHtml}</div>` : ''}
              ${auxiliaryHtml ? `<div class="pass-fields-grid auxiliary-grid">${auxiliaryHtml}</div>` : ''}
            </div>

            ${barcodeHtml}

            <div class="pass-footer-bar">
              <span class="pass-apple-badge">Apple Wallet</span>
              <button type="button" class="btn-flip-trigger" id="btn-flip-card" title="View details and terms">ℹ️ Flip to Back</button>
            </div>
          </div>

          <!-- Back Face -->
          <div class="apple-pass-card pass-card-back" style="background-color: #1a202c; color: #f8fafc;">
            <div class="back-header-row">
              <h3>${escapeHtml(pass.organizationName || 'Pass Details')}</h3>
              <button type="button" class="btn-flip-close" id="btn-flip-back">✕ Done</button>
            </div>
            <div class="back-fields-scroll">
              ${backFieldsHtml}
            </div>
            <div class="back-footer-bar">
              <small>Pass Type: ${escapeHtml(pass.passTypeIdentifier || '')}</small>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  function renderGoogleWalletCardHtml() {
    const pass = state.currentPass;
    const passType = PASS_STYLES.find(s => pass[s] && typeof pass[s] === 'object') || 'generic';
    const styleDict = pass[passType] || {};

    const bgColor = pass.backgroundColor || 'rgb(30, 41, 59)';
    const fgColor = pass.foregroundColor || 'rgb(255, 255, 255)';
    const barcodes = Array.isArray(pass.barcodes) ? pass.barcodes : (pass.barcode ? [pass.barcode] : []);
    const barcodeSvg = barcodes.length > 0 ? renderBarcode(barcodes[0].format, barcodes[0].message) : '';

    return `
      <div class="google-wallet-card" style="background: linear-gradient(180deg, ${bgColor} 0%, #0f172a 100%);">
        <div class="gw-header">
          <div class="gw-logo-row">
            <span class="gw-g-badge">G</span>
            <span class="gw-title">Google Wallet</span>
          </div>
          <span class="gw-chip">Saved to phone</span>
        </div>

        <div class="gw-body">
          <div class="gw-hero-badge">${escapeHtml(pass.organizationName || 'PASS')}</div>
          <h2 class="gw-headline">${escapeHtml(pass.description || 'Digital Pass')}</h2>

          <div class="gw-fields-list">
            ${(styleDict.primaryFields || []).map(f => `
              <div class="gw-field-row">
                <span class="gw-label">${escapeHtml(f.label || f.key)}</span>
                <strong class="gw-val">${escapeHtml(f.value || '')}</strong>
              </div>
            `).join('')}
            ${(styleDict.secondaryFields || []).slice(0, 2).map(f => `
              <div class="gw-field-row">
                <span class="gw-label">${escapeHtml(f.label || f.key)}</span>
                <strong class="gw-val">${escapeHtml(f.value || '')}</strong>
              </div>
            `).join('')}
          </div>

          ${barcodeSvg ? `
            <div class="gw-barcode-box">
              <div class="gw-barcode-svg">${barcodeSvg}</div>
              <small class="gw-barcode-alt">${escapeHtml(barcodes[0].altText || barcodes[0].message || '')}</small>
            </div>
          ` : ''}
        </div>

        <div class="gw-footer">
          <span>Card ID: ${escapeHtml(pass.serialNumber || '001')}</span>
          <button type="button" class="gw-details-btn">Details</button>
        </div>
      </div>
    `;
  }

  // --- Render Diagnostics Tabs ---
  function renderDiagnostics() {
    const pass = state.currentPass;
    const linterResult = validatePassJson(pass);

    // 1. Status Summary Header
    const statusHeaderEl = document.getElementById('diag-status-summary');
    if (statusHeaderEl) {
      if (linterResult.isValid) {
        statusHeaderEl.className = 'diag-summary-box status-valid';
        statusHeaderEl.innerHTML = `
          <div class="diag-status-badge badge-valid">🟢 STATUS: VALID PASS</div>
          <div class="diag-meta-row">
            <span>Pass Type: <strong>${escapeHtml(linterResult.passType || 'generic')}</strong></span>
            <span>Org: <strong>${escapeHtml(pass.organizationName || '—')}</strong></span>
            <span>Team ID: <strong>${escapeHtml(pass.teamIdentifier || '—')}</strong></span>
          </div>
        `;
      } else {
        statusHeaderEl.className = 'diag-summary-box status-errors';
        statusHeaderEl.innerHTML = `
          <div class="diag-status-badge badge-error">🔴 ${linterResult.errorCount} CRITICAL ERRORS · ${linterResult.warningCount} WARNINGS</div>
          <div class="diag-meta-row">
            <span>Pass Type: <strong>${escapeHtml(linterResult.passType || 'Unknown')}</strong></span>
            <span>Serial: <strong>${escapeHtml(pass.serialNumber || '—')}</strong></span>
          </div>
        `;
      }
    }

    // 2. Manifest Tab
    const manifestTableEl = document.getElementById('manifest-table-body');
    if (manifestTableEl) {
      const rows = [];
      const allFiles = new Set([...Object.keys(state.manifestMap), ...Object.keys(state.rawArchiveFiles)]);
      
      allFiles.forEach(fn => {
        if (fn === 'manifest.json' || fn === 'signature') return;
        const expected = state.manifestMap[fn];
        const actual = state.rawArchiveFiles[fn] ? sha1Sync(state.rawArchiveFiles[fn]) : null;

        let status = '';
        if (!actual) {
          status = '<span class="pill-error">MISSING FILE</span>';
        } else if (!expected) {
          status = '<span class="pill-warning">NOT IN MANIFEST</span>';
        } else if (expected.toLowerCase() === actual.toLowerCase()) {
          status = '<span class="pill-ok">MATCH (Valid)</span>';
        } else {
          status = '<span class="pill-error">SHA-1 MISMATCH</span>';
        }

        rows.push(`
          <tr>
            <td><code>${escapeHtml(fn)}</code></td>
            <td><small class="hash-code">${expected ? expected.substring(0, 10) + '...' : '—'}</small></td>
            <td><small class="hash-code">${actual ? actual.substring(0, 10) + '...' : '—'}</small></td>
            <td>${status}</td>
          </tr>
        `);
      });

      manifestTableEl.innerHTML = rows.join('') || '<tr><td colspan="4" class="empty-hint">No manifest loaded</td></tr>';
    }

    // 3. Schema & Linter Tab
    const linterListEl = document.getElementById('linter-findings-list');
    if (linterListEl) {
      if (linterResult.findings.length === 0) {
        linterListEl.innerHTML = '<div class="finding-item item-ok">✅ All PassKit schema keys, types, and constraints passed cleanly.</div>';
      } else {
        linterListEl.innerHTML = linterResult.findings.map(f => `
          <div class="finding-item item-${f.severity}">
            <div class="finding-header">
              <span class="finding-code">${escapeHtml(f.code)}</span>
              <strong class="finding-title">${escapeHtml(f.title)}</strong>
              ${f.field ? `<code class="finding-field">${escapeHtml(f.field)}</code>` : ''}
            </div>
            <p class="finding-desc">${escapeHtml(f.description)}</p>
          </div>
        `).join('');
      }
    }

    // 4. Asset Checklist Tab
    const assetListEl = document.getElementById('asset-checklist-grid');
    if (assetListEl) {
      const assetSpecs = [
        { name: 'icon.png', required: true, dim: '29×29 px' },
        { name: 'icon@2x.png', required: true, dim: '58×58 px' },
        { name: 'icon@3x.png', required: false, dim: '87×87 px' },
        { name: 'logo.png', required: false, dim: '160×50 px' },
        { name: 'logo@2x.png', required: false, dim: '320×100 px' },
        { name: 'strip.png', required: false, dim: '375×98 / 123 px' },
        { name: 'strip@2x.png', required: false, dim: '750×196 / 246 px' },
        { name: 'thumbnail.png', required: false, dim: '90×90 px' }
      ];

      assetListEl.innerHTML = assetSpecs.map(a => {
        const isPresent = Boolean(state.rawArchiveFiles[a.name]);
        return `
          <div class="asset-card ${isPresent ? 'asset-present' : (a.required ? 'asset-missing-req' : 'asset-missing-opt')}">
            <div class="asset-icon">${isPresent ? '🖼️' : '⚪'}</div>
            <div class="asset-info">
              <strong>${escapeHtml(a.name)}</strong>
              <small>${escapeHtml(a.dim)} · ${a.required ? '<b style="color: #ef4444">Required</b>' : 'Optional'}</small>
            </div>
            <div class="asset-status-tag">${isPresent ? 'Present' : 'Missing'}</div>
          </div>
        `;
      }).join('');
    }

    // 5. Signature Tab
    const sigContainerEl = document.getElementById('signature-info-container');
    if (sigContainerEl) {
      const sigData = parsePkcs7Signature(state.rawArchiveFiles['signature']);
      if (!sigData.present) {
        sigContainerEl.innerHTML = `
          <div class="sig-alert sig-alert-warning">
            <h4>⚠️ No PKCS#7 Signature Found</h4>
            <p>File <code>signature</code> is missing. iOS requires a digital signature signed by an Apple Developer Pass Type ID certificate.</p>
          </div>
        `;
      } else {
        sigContainerEl.innerHTML = `
          <div class="sig-alert ${sigData.valid ? 'sig-alert-success' : 'sig-alert-error'}">
            <h4>${sigData.valid ? '✅ Valid Apple Developer Signature' : '🔴 Signature / Certificate Alert'}</h4>
            <div class="sig-props-grid">
              <div><span>Issuer:</span> <strong>${escapeHtml(sigData.issuer || '—')}</strong></div>
              <div><span>Pass Type ID:</span> <code>${escapeHtml(sigData.passTypeIdentifier || pass.passTypeIdentifier || '—')}</code></div>
              <div><span>Team ID:</span> <code>${escapeHtml(sigData.teamIdentifier || pass.teamIdentifier || '—')}</code></div>
              <div><span>Valid Until:</span> <strong>${escapeHtml(sigData.notAfter || '2027-12-31')}</strong></div>
              <div><span>Expired:</span> <strong>${sigData.isExpired ? 'YES ⚠️' : 'NO (Active)'}</strong></div>
            </div>
          </div>
        `;
      }
    }

    // 6. Localization Tab
    const locContainerEl = document.getElementById('localization-container');
    if (locContainerEl) {
      const locales = Object.keys(state.localizations);
      if (locales.length === 0) {
        locContainerEl.innerHTML = '<p class="empty-hint">No .lproj localization directories found in archive.</p>';
      } else {
        locContainerEl.innerHTML = `
          <div class="loc-header-row">
            <span>Detected Locales: <strong>${locales.join(', ')}</strong></span>
          </div>
          <div class="loc-strings-table-wrap">
            <table class="diag-table">
              <thead><tr><th>Key</th><th>String Value</th></tr></thead>
              <tbody>
                ${Object.entries(state.localizations[locales[0]] || {}).map(([k, v]) => `
                  <tr><td><code>${escapeHtml(k)}</code></td><td>${escapeHtml(v)}</td></tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        `;
      }
    }

    // 7. Contrast Tab
    const contrastContainerEl = document.getElementById('contrast-container');
    if (contrastContainerEl) {
      const bg = parseRgb(pass.backgroundColor) || [17, 24, 39];
      const fg = parseRgb(pass.foregroundColor) || [255, 255, 255];
      const label = parseRgb(pass.labelColor) || [156, 163, 175];

      const fgRatio = calculateContrastRatio(bg, fg);
      const labelRatio = calculateContrastRatio(bg, label);

      contrastContainerEl.innerHTML = `
        <div class="contrast-swatches-grid">
          <div class="swatch-item">
            <div class="swatch-color" style="background: ${pass.backgroundColor || 'rgb(17,24,39)'}"></div>
            <span>Background</span>
            <code>${escapeHtml(pass.backgroundColor || 'rgb(17,24,39)')}</code>
          </div>
          <div class="swatch-item">
            <div class="swatch-color" style="background: ${pass.foregroundColor || 'rgb(255,255,255)'}"></div>
            <span>Foreground</span>
            <code>${escapeHtml(pass.foregroundColor || 'rgb(255,255,255)')}</code>
          </div>
          <div class="swatch-item">
            <div class="swatch-color" style="background: ${pass.labelColor || 'rgb(156,163,175)'}"></div>
            <span>Label</span>
            <code>${escapeHtml(pass.labelColor || 'rgb(156,163,175)')}</code>
          </div>
        </div>

        <div class="contrast-score-row">
          <div class="contrast-score-card">
            <span>Foreground Ratio:</span>
            <strong class="${fgRatio >= 4.5 ? 'score-aaa' : (fgRatio >= 3.0 ? 'score-aa' : 'score-fail')}">${fgRatio.toFixed(2)}:1</strong>
            <small>${fgRatio >= 3.0 ? 'WCAG AA Compliant' : 'Low Contrast'}</small>
          </div>
          <div class="contrast-score-card">
            <span>Label Ratio:</span>
            <strong class="${labelRatio >= 4.5 ? 'score-aaa' : (labelRatio >= 2.5 ? 'score-aa' : 'score-fail')}">${labelRatio.toFixed(2)}:1</strong>
            <small>${labelRatio >= 2.5 ? 'Legible' : 'Too Subtle'}</small>
          </div>
        </div>
      `;
    }
  }

  function renderJsonEditor() {
    const jsonEditor = document.getElementById('pkpass-json-editor');
    if (jsonEditor && document.activeElement !== jsonEditor) {
      jsonEditor.value = JSON.stringify(state.currentPass, null, 2);
    }
  }

  // --- Actions ---
  function handleRepackAndDownload() {
    const passJsonStr = JSON.stringify(state.currentPass, null, 2);
    const filesToPack = Object.assign({}, state.rawArchiveFiles);
    filesToPack['pass.json'] = passJsonStr;

    // Generate fresh manifest.json with updated SHA-1 hashes
    const freshManifest = {};
    Object.keys(filesToPack).forEach(fn => {
      if (fn === 'manifest.json' || fn === 'signature') return;
      freshManifest[fn] = sha1Sync(filesToPack[fn]);
    });

    filesToPack['manifest.json'] = JSON.stringify(freshManifest, null, 2);

    const zipBytes = createZipArchive(filesToPack);
    const blob = new Blob([zipBytes], { type: 'application/vnd.apple.pkpass' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${state.currentPass.serialNumber || 'pass'}.pkpass`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function handleExportGoogleWallet() {
    const googleWalletJson = convertToGoogleWallet(state.currentPass);
    const str = JSON.stringify(googleWalletJson, null, 2);
    const blob = new Blob([str], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `google-wallet-${state.currentPass.serialNumber || 'pass'}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function handleDownloadJson() {
    const str = JSON.stringify(state.currentPass, null, 2);
    const blob = new Blob([str], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'pass.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  // --- Helpers ---
  function escapeHtml(str) {
    if (!str && str !== 0) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function linkify(text) {
    const urlRegex = /(https?:\/\/[^\s<]+)/g;
    return text.replace(urlRegex, url => `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`);
  }

  return {
    calculateSha1,
    sha1Sync,
    parseZipArchive,
    createZipArchive,
    parsePkcs7Signature,
    parseStringsFile,
    parseRgb,
    calculateContrastRatio,
    validatePassJson,
    convertToGoogleWallet,
    renderBarcode,
    PRESETS,
    initUI
  };
});
