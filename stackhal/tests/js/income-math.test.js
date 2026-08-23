const assert = require('node:assert/strict');
const { compare, progressiveAnnualTax } = require('../../public/income-math.js');

assert.equal(progressiveAnnualTax(30000), 0);
assert.equal(progressiveAnnualTax(120000), 10800);
assert.equal(progressiveAnnualTax(130000), 14000);

const regular = compare({ budget: 12000, studentUnder26: false, costs: 500, taxation: 'linear', zus: 'standard', lumpRate: 12 });
assert.equal(regular.employment.cost, 12000);
assert.ok(regular.employment.net > 0 && regular.employment.net < regular.employment.gross);
assert.ok(regular.work.net > regular.employment.net);
assert.equal(regular.b2b.businessCosts, 500);

const student = compare({ budget: 6000, studentUnder26: true, costs: 0, taxation: 'scale', zus: 'start', lumpRate: 12 });
assert.equal(student.mandate.net, 6000);
assert.equal(student.mandate.social, 0);
assert.equal(student.mandate.health, 0);

console.log('Income comparison tests passed');
