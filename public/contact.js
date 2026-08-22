document.querySelectorAll('[data-contact-form]').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const status = form.querySelector('[data-contact-status]');
    button.disabled = true;
    status.textContent = form.dataset.saving || 'Saving…';

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {'Accept': 'application/json'},
      });
      const result = await response.json();
      status.textContent = result.message || result.error || form.dataset.fallback;
      status.classList.toggle('lead-success', response.ok);
      if (response.ok) {
        form.querySelectorAll('input:not([type="hidden"]), textarea').forEach(field => { field.disabled = true; });
        button.hidden = true;
      } else {
        button.disabled = false;
      }
    } catch (_) {
      status.textContent = form.dataset.fallback;
      button.disabled = false;
    }
  });
});
