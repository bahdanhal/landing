const assert = require('node:assert/strict');
const {calculate} = require('../../public/vat-math.js');

assert.deepEqual(calculate(1000, 'net', 23), {net: 1000, tax: 230, gross: 1230});
assert.deepEqual(calculate(1230, 'gross', 23), {net: 1000, tax: 230, gross: 1230});
assert.deepEqual(calculate(100, 'net', 8), {net: 100, tax: 8, gross: 108});
assert.deepEqual(calculate(10.01, 'net', 5), {net: 10.01, tax: 0.5, gross: 10.51});
assert.deepEqual(calculate(99.99, 'gross', 0), {net: 99.99, tax: 0, gross: 99.99});
assert.deepEqual(calculate(-20, 'net', 23), {net: 0, tax: 0, gross: 0});

console.log('VAT math tests passed');
