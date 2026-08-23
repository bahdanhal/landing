(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  root.PolandIncomeMath = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  const round = value => Math.round((value + Number.EPSILON) * 100) / 100;
  const progressiveAnnualTax = base => round(Math.max(0, base <= 120000 ? base * 0.12 - 3600 : 10800 + (base - 120000) * 0.32));
  const monthlyProgressiveTax = monthlyBase => round(progressiveAnnualTax(Math.max(0, monthlyBase) * 12) / 12);
  const employeeSocial = gross => round(gross * 0.1371);
  const health = base => round(Math.max(0, base) * 0.09);

  function employment(budget) {
    const gross = round(budget / 1.2048);
    const social = employeeSocial(gross);
    const healthContribution = health(gross - social);
    const tax = monthlyProgressiveTax(gross - social - 250);
    return result(budget, gross, social, healthContribution, tax, 0);
  }

  function mandate(budget, studentUnder26) {
    if (studentUnder26) {
      const taxableAnnual = Math.max(0, budget * 12 - 85528);
      const tax = round(progressiveAnnualTax(taxableAnnual * 0.8) / 12);
      return result(budget, budget, 0, 0, tax, 0);
    }
    const gross = round(budget / 1.2048);
    const social = employeeSocial(gross);
    const baseAfterSocial = gross - social;
    const tax = monthlyProgressiveTax(baseAfterSocial * 0.8);
    return result(budget, gross, social, health(baseAfterSocial), tax, 0);
  }

  function workContract(budget) {
    const tax = monthlyProgressiveTax(budget * 0.8);
    return result(budget, budget, 0, 0, tax, 0);
  }

  function b2b(budget, options) {
    const costs = Math.max(0, Number(options.costs) || 0);
    const social = options.zus === 'start' ? 0 : options.zus === 'preferential' ? 456.18 : 1926.76;
    const income = Math.max(0, budget - costs - social);
    let healthContribution;
    let tax;
    if (options.taxation === 'linear') {
      healthContribution = Math.max(432.54, round(income * 0.049));
      tax = round(Math.max(0, income - Math.min(healthContribution, 1175)) * 0.19);
    } else if (options.taxation === 'lump') {
      const annualRevenue = budget * 12;
      healthContribution = annualRevenue <= 60000 ? 498.35 : annualRevenue <= 300000 ? 830.58 : 1495.04;
      const rate = Math.max(0, Number(options.lumpRate) || 12) / 100;
      tax = round(Math.max(0, budget - social - healthContribution * 0.5) * rate);
    } else {
      healthContribution = Math.max(432.54, round(income * 0.09));
      tax = monthlyProgressiveTax(income);
    }
    return result(budget, budget, social, healthContribution, tax, costs);
  }

  function result(cost, gross, social, healthContribution, tax, businessCosts) {
    return { cost: round(cost), gross: round(gross), social: round(social), health: round(healthContribution), tax: round(tax), businessCosts: round(businessCosts), net: round(gross - social - healthContribution - tax - businessCosts) };
  }

  function compare(options) {
    const budget = Math.max(0, Number(options.budget) || 0);
    return { employment: employment(budget), mandate: mandate(budget, !!options.studentUnder26), work: workContract(budget), b2b: b2b(budget, options) };
  }

  return { compare, progressiveAnnualTax };
});
