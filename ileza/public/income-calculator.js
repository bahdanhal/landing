(function () {
  const root = document.querySelector('[data-income-calculator]');
  if (!root || !window.PolandIncomeMath) return;
  const locale = root.dataset.locale === 'pl' ? 'pl-PL' : 'en-GB';
  const money = value => new Intl.NumberFormat(locale, { style: 'currency', currency: 'PLN' }).format(value);
  const controls = Object.fromEntries([...root.querySelectorAll('[data-control]')].map(node => [node.dataset.control, node]));
  const output = root.querySelector('[data-results]');
  const labels = JSON.parse(root.dataset.labels);

  function render() {
    const results = window.PolandIncomeMath.compare({
      budget: controls.budget.value.replace(',', '.'),
      studentUnder26: controls.student.checked,
      costs: controls.costs.value.replace(',', '.'),
      taxation: controls.taxation.value,
      zus: controls.zus.value,
      lumpRate: controls.lumpRate.value,
    });
    controls.lumpRate.closest('.field').hidden = controls.taxation.value !== 'lump';
    output.innerHTML = Object.entries(results).map(([type, item]) => `<article><div class="result-head"><span>${labels[type]}</span><strong>${money(item.net)}</strong><small>${labels.net}</small></div><dl><div><dt>${labels.budget}</dt><dd>${money(item.cost)}</dd></div><div><dt>${labels.gross}</dt><dd>${money(item.gross)}</dd></div>${item.businessCosts ? `<div><dt>${labels.costs}</dt><dd>−${money(item.businessCosts)}</dd></div>` : ''}<div><dt>${labels.social}</dt><dd>−${money(item.social)}</dd></div><div><dt>${labels.health}</dt><dd>−${money(item.health)}</dd></div><div><dt>${labels.tax}</dt><dd>−${money(item.tax)}</dd></div></dl></article>`).join('');
  }
  root.addEventListener('input', render);
  root.addEventListener('change', render);
  render();
})();
