(() => {
  const root = document.querySelector('[data-vat-calculator]');
  if (!root) return;

  const amount = document.getElementById('vat-amount');
  const direction = document.getElementById('vat-direction');
  const rate = document.getElementById('vat-rate');
  const scenario = document.getElementById('vat-scenario');
  const futureRate = document.getElementById('future-rate');
  const futureDate = document.getElementById('future-date');
  const futureFields = root.querySelector('[data-future-fields]');
  const scenarioNote = root.querySelector('[data-scenario-note]');
  const outputNet = root.querySelector('[data-result-net]');
  const outputTax = root.querySelector('[data-result-tax]');
  const outputGross = root.querySelector('[data-result-gross]');
  const outputRate = root.querySelector('[data-result-rate]');
  const copy = root.querySelector('[data-copy-result]');
  const locale = root.dataset.locale === 'pl' ? 'pl-PL' : 'en-GB';
  const money = new Intl.NumberFormat(locale, {style: 'currency', currency: 'PLN'});

  const toNumber = value => {
    const normalized = String(value).trim().replace(/\s/g, '').replace(',', '.');
    const parsed = Number(normalized);
    return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
  };
  const calculate = () => {
    const value = toNumber(amount.value);
    const future = scenario.value === 'future';
    const selected = rate.value === 'exempt' ? 0 : toNumber(rate.value);
    const percentage = future ? Math.min(100, toNumber(futureRate.value)) : selected;
    const {net, tax, gross} = window.VatMath.calculate(value, direction.value, percentage);

    futureFields.hidden = !future;
    rate.disabled = future;
    scenarioNote.textContent = future ? root.dataset.futureNote : root.dataset.currentNote;
    outputNet.textContent = money.format(net);
    outputTax.textContent = money.format(tax);
    outputGross.textContent = money.format(gross);
    outputRate.textContent = future ? `${percentage}% · ${futureDate.value || '?'}` : (rate.value === 'exempt' ? 'zw.' : `${percentage}%`);
    root.dataset.copyText = `${outputNet.previousElementSibling.textContent}: ${outputNet.textContent}\n${outputTax.previousElementSibling.textContent.trim()}: ${outputTax.textContent}\n${outputGross.previousElementSibling.textContent}: ${outputGross.textContent}`;
  };

  [amount, direction, rate, scenario, futureRate, futureDate].forEach(input => input.addEventListener('input', calculate));
  copy.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(root.dataset.copyText);
      copy.textContent = root.dataset.copiedLabel;
      window.setTimeout(() => { copy.textContent = root.dataset.copyLabel; }, 1400);
    } catch (_) {}
  });
  calculate();
})();
