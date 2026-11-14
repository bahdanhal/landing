(() => {
  'use strict';
  const shell = document.querySelector('.profile-shell');
  if (!shell) return;
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  const progress = document.createElement('div');
  progress.className = 'progress';
  progress.setAttribute('aria-hidden', 'true');
  const back = document.createElement('button');
  back.type = 'button';
  back.className = 'back-top';
  back.hidden = true;
  back.textContent = '↑';
  back.setAttribute('aria-label', shell.dataset.topLabel);
  back.addEventListener('click', () => window.scrollTo({top: 0, behavior: reducedMotion.matches ? 'instant' : 'smooth'}));
  document.body.append(progress, back);
  let scheduled = false;
  function updateScroll() {
    progress.style.width = `${window.scrollY / Math.max(1, document.documentElement.scrollHeight - innerHeight) * 100}%`;
    back.hidden = window.scrollY <= 600;
    back.classList.toggle('visible', !back.hidden);
    scheduled = false;
  }
  window.addEventListener('scroll', () => {
    if (!scheduled) { scheduled = true; requestAnimationFrame(updateScroll); }
  }, {passive: true});
  window.addEventListener('resize', updateScroll);
  updateScroll();

  document.querySelectorAll('[data-obd]').forEach(button => {
    button.addEventListener('click', () => {
      const screen = document.querySelector('.obd-screen img');
      screen.src = button.dataset.obd;
      screen.alt = button.textContent;
      document.querySelectorAll('[data-obd]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
      const secondary = document.querySelector('.obd-secondary-screen');
      const other = [...document.querySelectorAll('[data-obd]')].find(item => item !== button);
      if (secondary && other) { secondary.src = other.dataset.obd; secondary.alt = other.textContent; }
    });
  });

  const portraitDialog = document.querySelector('.portrait-dialog');
  const portraitOpener = document.querySelector('[data-portrait-open]');
  const portraitClose = document.querySelector('[data-portrait-close]');
  if (portraitDialog && portraitOpener && portraitClose) {
    portraitOpener.addEventListener('click', () => portraitDialog.showModal());
    portraitClose.addEventListener('click', () => portraitDialog.close());
    portraitDialog.addEventListener('click', event => {
      if (event.target !== portraitDialog) return;
      const bounds = portraitDialog.getBoundingClientRect();
      if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) portraitDialog.close();
    });
    portraitDialog.addEventListener('keydown', event => {
      if (event.key !== 'Tab') return;
      const controls = [...portraitDialog.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), [tabindex="0"]')];
      const first = controls[0];
      const last = controls[controls.length - 1];
      if (!first) return;
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault(); last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first.focus();
      }
    });
    portraitDialog.addEventListener('close', () => portraitOpener.focus({preventScroll: true}));
  }
  document.addEventListener('focusin', event => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    target.closest('.reveal.pending')?.classList.remove('pending');
    const nav = document.querySelector('.primary-nav');
    if (innerWidth <= 760 && target.matches(':focus-visible') && !nav?.contains(target) && !portraitDialog?.contains(target)) {
      const bounds = target.getBoundingClientRect();
      if (bounds.bottom > innerHeight - 92 || bounds.top < 12) target.scrollIntoView({block: 'center', behavior: 'instant'});
    }
  });
  if ('IntersectionObserver' in window && !reducedMotion.matches) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) { entry.target.classList.remove('pending'); observer.unobserve(entry.target); }
      });
    }, {threshold: .08});
    document.querySelectorAll('.timeline-item, .gateway-card').forEach(item => {
      item.classList.add('reveal', 'pending'); observer.observe(item);
    });
  }
})();
