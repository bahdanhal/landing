const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

// Load CIDR matrix spec
const specPath = path.resolve(__dirname, '../../specs/cidr-matrix.spec.json');
const spec = JSON.parse(fs.readFileSync(specPath, 'utf8'));

assert.ok(spec.supported_ip_versions.includes('v4'));
assert.ok(spec.supported_ip_versions.includes('v6'));
assert.ok(spec.presets.length >= 3);
assert.ok(spec.test_vectors.length >= 4);

// Verify spec test vectors
for (const vector of spec.test_vectors) {
  assert.ok(Array.isArray(vector.cidrs));
  assert.ok(vector.cidrs.length > 0);
}

console.log('Visual CIDR & Subnet Overlap Matrix JS spec test passed');
