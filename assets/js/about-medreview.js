(() => {
  const page = document.querySelector('.mr-about');
  if (!page || !('IntersectionObserver' in window)) return;
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const reveal = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.remove('is-awaiting');
        reveal.unobserve(entry.target);
      });
    }, { threshold: 0.05 });
    page.querySelectorAll('[data-about-reveal]').forEach((element) => {
      // Default HTML stays visible if scripts are disabled or unavailable.
      if (element.getBoundingClientRect().top > window.innerHeight) element.classList.add('is-awaiting');
      reveal.observe(element);
    });
  }
  const links = [...page.querySelectorAll('.mr-about__nav-items a')];
  const sections = links.map((link) => page.querySelector(link.getAttribute('href'))).filter(Boolean);
  const setCurrent = (id) => links.forEach((link) => {
    if (link.hash === `#${id}`) link.setAttribute('aria-current', 'location');
    else link.removeAttribute('aria-current');
  });
  links.forEach((link) => link.addEventListener('click', () => setCurrent(link.hash.slice(1))));
  const locationObserver = new IntersectionObserver((entries) => {
    const visible = entries.filter((entry) => entry.isIntersecting);
    if (visible.length) setCurrent(visible[visible.length - 1].target.id);
  }, { rootMargin: '-15% 0px -50% 0px', threshold: 0 });
  sections.forEach((section) => locationObserver.observe(section));
})();
