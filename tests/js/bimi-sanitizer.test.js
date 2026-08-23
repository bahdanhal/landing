const assert = require('node:assert/strict');

function buildDnsTxtRecord(domain, svgUrl, vmcUrl) {
  let tag = `v=BIMI1; l=${svgUrl};`;
  if (vmcUrl) {
    tag += ` a=${vmcUrl};`;
  }
  return `default._bimi.${domain} IN TXT "${tag}"`;
}

function validateSvgTinyPsAttributes(svgString) {
  assert.ok(svgString.includes('version="1.2"'), 'Must contain version 1.2');
  assert.ok(svgString.includes('baseProfile="tiny-ps"'), 'Must contain baseProfile tiny-ps');
  assert.ok(svgString.includes('xmlns="http://www.w3.org/2000/svg"'), 'Must contain standard SVG namespace');
  assert.ok(svgString.includes('viewBox="0 0 512 512"'), 'Must have 1:1 square viewBox');
  assert.ok(!svgString.includes('<script>'), 'Must not contain script tags');
  assert.ok(!svgString.includes('onload='), 'Must not contain event handlers');
}

// 1. Test DNS TXT formatting
const defaultDns = buildDnsTxtRecord('example.com', 'https://example.com/logo-bimi.svg', '');
assert.equal(defaultDns, 'default._bimi.example.com IN TXT "v=BIMI1; l=https://example.com/logo-bimi.svg;"');

const vmcDns = buildDnsTxtRecord('stripe.com', 'https://stripe.com/logo.svg', 'https://stripe.com/cert.pem');
assert.equal(vmcDns, 'default._bimi.stripe.com IN TXT "v=BIMI1; l=https://stripe.com/logo.svg; a=https://stripe.com/cert.pem;"');

// 2. Test SVG Tiny 1.2 PS Template Compliance
const sampleBimiSvg = `<?xml version="1.0" encoding="utf-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.2" baseProfile="tiny-ps" viewBox="0 0 512 512" width="100%" height="100%">
  <title>BIMI Logo</title>
  <rect width="512" height="512" fill="#2563eb"/>
  <circle cx="256" cy="256" r="120" fill="#ffffff"/>
</svg>`;

validateSvgTinyPsAttributes(sampleBimiSvg);

console.log('BIMI sanitizer & DNS tests passed');
