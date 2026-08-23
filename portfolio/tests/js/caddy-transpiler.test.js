const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

// Load spec
const specPath = path.resolve(__dirname, '../../specs/caddy-transpiler.spec.json');
const spec = JSON.parse(fs.readFileSync(specPath, 'utf8'));

assert.ok(spec.supported_source_types.includes('nginx'));
assert.ok(spec.supported_source_types.includes('apache'));
assert.ok(spec.test_vectors.length >= 3);

// Minimal verification of test vectors
for (const vector of spec.test_vectors) {
  assert.ok(vector.input);
  assert.ok(vector.expected_caddyfile_snippets.length > 0);
}

console.log('Caddyfile transpiler JS test spec verification passed');
