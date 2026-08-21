document.querySelectorAll('[data-contact-form]').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    const status = form.querySelector('[data-contact-status]');
    button.disabled = true;
    status.textContent = 'Saving…';

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {'Accept': 'application/json'},
      });
      const result = await response.json();
      status.textContent = result.message || result.error || 'Something went wrong. Please use one of the direct contact links.';
      status.classList.toggle('lead-success', response.ok);
      if (response.ok) {
        form.querySelector('input[name="email"]').disabled = true;
        button.hidden = true;
      } else {
        button.disabled = false;
      }
    } catch (_) {
      status.textContent = 'Could not save right now. Please use email, LinkedIn, or Upwork.';
      button.disabled = false;
    }
  });
});
