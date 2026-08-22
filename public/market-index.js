document.querySelectorAll('[data-market-family]').forEach((family) => {
  const select = family.querySelector('[data-market-select]');
  const link = family.querySelector('[data-market-link]');
  const price = family.querySelector('[data-market-price]');
  const note = family.querySelector('[data-market-note]');
  if (!select || !link || !price || !note) return;

  const sync = () => {
    const option = select.options[select.selectedIndex];
    link.href = option.value;
    price.textContent = option.dataset.price || '';
    note.textContent = option.dataset.note || '';
  };
  select.addEventListener('change', sync);
  sync();
});
