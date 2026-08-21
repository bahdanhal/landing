(function (scope) {
  const round = value => Math.round((Number(value) + Number.EPSILON) * 100) / 100;

  const calculate = (amount, direction, rate) => {
    const value = round(Math.max(0, Number(amount) || 0));
    const percentage = Math.max(0, Math.min(100, Number(rate) || 0));
    if (direction === 'gross') {
      const tax = percentage === 0 ? 0 : round(value * percentage / (100 + percentage));
      return {net: round(value - tax), tax, gross: value};
    }
    const tax = round(value * percentage / 100);
    return {net: value, tax, gross: round(value + tax)};
  };

  const api = {calculate, round};
  scope.VatMath = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis);
