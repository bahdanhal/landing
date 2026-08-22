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

document.querySelectorAll('[data-product-request-form]').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const status = form.querySelector('[data-contact-status]');
    button.disabled = true;
    status.textContent = form.dataset.saving;
    try {
      const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json'}});
      const result = await response.json();
      status.textContent = result.message || result.error || form.dataset.fallback;
      status.classList.toggle('lead-success', response.ok);
      if (response.ok) form.reset();
      button.disabled = false;
    } catch (_) {
      status.textContent = form.dataset.fallback;
      button.disabled = false;
    }
  });
});
