(() => {
  'use strict';
  const home = document.querySelector('.hn-home');
  if (!home) return;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const shell = home.querySelector('.hero-search-shell');
  const input = shell?.querySelector('[data-medical-search-input]');
  const backdrop = document.createElement('div');
  backdrop.className = 'home-search-backdrop';
  backdrop.hidden = true;
  document.body.append(backdrop);
  const sync = () => {
    // A response can arrive after keyboard focus has left the search. Never
    // reopen a full-page backdrop for that stale interaction.
    if (shell?.classList.contains('is-open') && !shell.contains(document.activeElement)) {
      input?.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
      shell.dataset.homeSearchAutoScrolled = 'false';
    }
    backdrop.hidden = !shell?.classList.contains('is-open');
  };
  if (shell) new MutationObserver(sync).observe(shell, {attributes:true,attributeFilter:['class']});
  const commitSearch = value => {
    if (!input) return;
    input.value = value.trim() ? value.trim() + ' ' : '';
    input.focus({preventScroll:true});
    input.dispatchEvent(new Event('input',{bubbles:true}));
  };
  shell?.querySelector('.send-button')?.addEventListener('click',event => { event.preventDefault();commitSearch(input?.value || ''); });
  shell?.querySelector('form')?.addEventListener('submit',event => {
    // Enter without an active result commits the query in-place, like Send.
    // The shared search still handles Enter on an already selected result.
    event.preventDefault();
    commitSearch(input?.value || '');
  });
  input?.addEventListener('keydown',event => {
    if (event.key === 'Escape') shell.dataset.homeSearchAutoScrolled = 'false';
  });
  home.querySelectorAll('[data-home-query]').forEach(button => button.addEventListener('click',() => commitSearch(button.dataset.homeQuery || '')));
  let closeTimer;
  shell?.addEventListener('focusout',() => {
    clearTimeout(closeTimer);
    closeTimer = setTimeout(() => {
      if (shell.contains(document.activeElement)) return;
      input?.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
      shell.dataset.homeSearchAutoScrolled = 'false';
      sync();
    },100);
  });
  backdrop.addEventListener('click',() => {
    input?.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
    input?.blur();
    sync();
  });
  // Do not blur the page on focus or loading; only the shared search's rendered
  // results state opens the backdrop. Its keyboard/API behavior stays shared.
  const grid = home.querySelector('[data-home-next-facilities]');
  const cards = [...home.querySelectorAll('.hn-facility[data-region]')];
  const filters = [...home.querySelectorAll('[data-home-next-city]')];
  const empty = grid?.querySelector('[data-home-next-empty]');
  const applyCity = city => {
    let shown = 0;
    cards.forEach(card => {
      const match = city === 'all' || card.dataset.region === city;
      card.hidden = !match || shown >= 3;
      if (match) shown++;
    });
    if (empty) empty.hidden = shown > 0;
    filters.forEach(button => button.setAttribute('aria-pressed',String(button.dataset.homeNextCity === city)));
  };
  filters.forEach(button => button.addEventListener('click',() => applyCity(button.dataset.homeNextCity || 'all')));
  applyCity('all');
  cards.forEach(card => {
    const button = card.querySelector('.hn-save');
    if (!button) return;
    const key = 'medreview:saved-facility:' + card.dataset.facilityId;
    try { button.setAttribute('aria-pressed',String(localStorage.getItem(key) === '1')); } catch (_) {}
    button.addEventListener('click',() => {
      const saved = button.getAttribute('aria-pressed') !== 'true';
      button.setAttribute('aria-pressed',String(saved));
      try { if (saved) localStorage.setItem(key,'1'); else localStorage.removeItem(key); } catch (_) {}
    });
  });
  home.querySelectorAll('[data-hn-image]').forEach(image => {
    const recover = () => image.remove();
    image.addEventListener('error',recover,{once:true});
    if (image.complete && !image.naturalWidth) recover();
  });
  if (!reduced && 'IntersectionObserver' in window) {
    home.classList.add('hn-motion-ready');
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    }),{threshold:.05});
    home.querySelectorAll('.hn-reveal').forEach(item => observer.observe(item));
  }
})();
