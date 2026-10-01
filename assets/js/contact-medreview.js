(() => {
  const form = document.querySelector('[data-contact-form]');
  if (!form) return;
  const topics = [...form.querySelectorAll('input[name="topic"]')];
  const hints = [...form.querySelectorAll('[data-contact-hint]')];
  const syncTopic = () => {
    const selected = topics.find((radio) => radio.checked)?.value;
    hints.forEach((hint) => { hint.hidden = hint.dataset.contactHint !== selected; });
  };
  topics.forEach((radio) => radio.addEventListener('change', syncTopic));
  document.querySelector('[data-contact-provider]')?.addEventListener('click', (event) => {
    const provider = topics.find((radio) => radio.value === 'provider');
    if (!provider) return;
    event.preventDefault();
    provider.checked = true;
    syncTopic();
    document.getElementById('gui-lien-he')?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  });
  const message = form.querySelector('textarea[name="message"]');
  const count = form.querySelector('[data-contact-count]');
  const syncCount = () => { if (message && count) count.textContent = `${[...message.value].length} / 4.000`; };
  message?.addEventListener('input', syncCount);
  syncCount();
  const button = form.querySelector('button[type="submit"]');
  const label = button?.querySelector('span');
  const originalLabel = label?.textContent;
  let submitting = false;
  form.addEventListener('submit', (event) => {
    if (submitting) { event.preventDefault(); return; }
    submitting = true;
    if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
    if (label) label.textContent = form.dataset.submitting;
  });
  window.addEventListener('pageshow', () => {
    submitting = false;
    if (button) { button.disabled = false; button.removeAttribute('aria-busy'); }
    if (label) label.textContent = originalLabel;
  });
})();
