(() => {
const progress = document.createElement('div');
progress.className = 'progress';
progress.setAttribute('aria-hidden', 'true');
document.body.append(progress);
const back = document.createElement('button');
back.className = 'back-top';
back.textContent = '↑';
back.setAttribute('aria-label', document.querySelector('.profile-shell').dataset.topLabel);
back.onclick = () => window.scrollTo({top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
document.body.append(back);
function updateScroll() {
  progress.style.width = `${window.scrollY / Math.max(1, document.documentElement.scrollHeight - window.innerHeight) * 100}%`;
  back.classList.toggle('visible', window.scrollY > 600);
}
window.addEventListener('scroll', updateScroll, {passive: true});
updateScroll();
document.querySelectorAll('.profile-grid > div').forEach(card => {
  const details = document.createElement('details');
  details.open = true;
  const summary = document.createElement('summary');
  summary.append(card.querySelector('h3'));
  details.append(summary, card.querySelector('p'));
  card.append(details);
});
const media = document.querySelector('.profile-hero-media');
const expand = document.createElement('button');
expand.className = 'portrait-expand';
expand.textContent = '↗';
expand.setAttribute('aria-label', document.querySelector('.profile-shell').dataset.enlargeLabel);
media.append(expand);
const dialog = document.createElement('dialog');
dialog.className = 'portrait-dialog';
const portrait = document.querySelector('.profile-avatar').cloneNode();
portrait.removeAttribute('class');
const close = document.createElement('button');
close.textContent = '×';
close.setAttribute('aria-label', document.querySelector('.profile-shell').dataset.closeLabel);
close.onclick = () => dialog.close();
dialog.append(portrait, close);
document.body.append(dialog);
expand.onclick = () => dialog.showModal();
dialog.addEventListener('click', event => {if (event.target === dialog) dialog.close();});
document.querySelectorAll('.profile-grid details').forEach((item, index) => {item.open = index === 0;});
document.querySelectorAll('[data-obd]').forEach(button => {
  button.onclick = () => {
    document.querySelector('.obd-screen img').src = button.dataset.obd;
    document.querySelectorAll('[data-obd]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
  };
});

const ambient = document.createElement('div');
ambient.className = 'scroll-ambient';
ambient.setAttribute('aria-hidden', 'true');
document.body.prepend(ambient);
function updateAmbient() {
  const ratio = window.scrollY / Math.max(1, document.documentElement.scrollHeight - innerHeight);
  document.body.style.setProperty('--scroll-hue', String(15 + ratio * 260));
  document.body.style.setProperty('--orb-shift', `${ratio * 120}px`);
}
window.addEventListener('scroll', updateAmbient, {passive: true});
updateAmbient();

})();
