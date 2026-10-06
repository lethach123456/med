(() => {
  'use strict';
  const root = document.querySelector('.tl-directory');
  const form = document.getElementById('toplistSearchForm');
  const list = document.getElementById('toplistList');
  if (!root || !form || !list) return;
  const input = document.getElementById('toplistSearch');
  const type = document.getElementById('toplistType');
  const sort = document.getElementById('toplistSort');
  const empty = document.getElementById('toplistEmpty');
  const count = document.getElementById('toplistResultCount');
  const status = document.getElementById('toplistStatus');
  const cards = [...list.querySelectorAll('[data-toplist-card]')];
  const en = root.dataset.locale === 'en';
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const fold = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/gi,'d').toLowerCase().replace(/\s+/g,' ').trim();
  const format = value => new Intl.NumberFormat(en ? 'en-US' : 'vi-VN').format(value);
  const results = () => root.querySelector('.tl-results-top')?.scrollIntoView({behavior:reduced ? 'auto' : 'smooth',block:'start'});
  const compare = (a,b) => {
    const recent = Number(b.dataset.updated) - Number(a.dataset.updated);
    let order = recent;
    if (sort.value === 'members') order = Number(b.dataset.members) - Number(a.dataset.members);
    if (sort.value === 'title') order = a.dataset.title < b.dataset.title ? -1 : a.dataset.title > b.dataset.title ? 1 : 0;
    return order || recent || Number(b.dataset.id) - Number(a.dataset.id);
  };
  const render = (updateUrl = true, animate = true) => {
    const query = fold(input.value);
    let visible = 0;
    const fragment = document.createDocumentFragment();
    cards.sort(compare).forEach(card => {
      card.hidden = Boolean((type.value && card.dataset.type !== type.value) || (query && !card.dataset.search.includes(query)));
      card.classList.remove('is-entering');
      if (!card.hidden) { card.style.setProperty('--tl-order',String(visible++)); }
      fragment.append(card);
    });
    list.append(fragment);
    count.querySelector('strong').textContent = format(visible);
    empty.hidden = visible > 0;
    status.textContent = format(visible) + (en ? ' matching lists' : ' danh sách phù hợp');
    root.querySelectorAll('[data-toplist-type]').forEach(link => {
      if (link.dataset.toplistType === type.value) link.setAttribute('aria-current','true');
      else link.removeAttribute('aria-current');
      const url = new URL(link.href,location.origin);
      url.search = '';
      if (input.value.trim()) url.searchParams.set('q',input.value.trim());
      if (link.dataset.toplistType) url.searchParams.set('type',link.dataset.toplistType);
      if (sort.value !== 'updated') url.searchParams.set('sort',sort.value);
      link.href = url.href;
    });
    if (animate && !reduced) requestAnimationFrame(() => cards.filter(card=>!card.hidden).slice(0,6).forEach(card=>card.classList.add('is-entering')));
    if (updateUrl) {
      const url = new URL(location.href);
      for (const key of ['q','type','sort']) url.searchParams.delete(key);
      if (input.value.trim()) url.searchParams.set('q',input.value.trim());
      if (type.value) url.searchParams.set('type',type.value);
      if (sort.value !== 'updated') url.searchParams.set('sort',sort.value);
      history.replaceState({},'',url);
    }
  };
  let timer;
  input.addEventListener('input',()=>{ clearTimeout(timer); timer = setTimeout(()=>render(true,false),220); });
  form.addEventListener('submit',event=>{event.preventDefault();clearTimeout(timer);render();results();});
  sort.addEventListener('change',()=>render());
  root.querySelectorAll('[data-toplist-type],[data-toplist-clear]').forEach(link=>link.addEventListener('click',event=>{
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();clearTimeout(timer);
    if (link.hasAttribute('data-toplist-clear')) { input.value='';type.value='';sort.value='updated'; }
    else type.value=link.dataset.toplistType;
    render();results();
  }));
  window.addEventListener('popstate',()=>{
    clearTimeout(timer);
    const params=new URLSearchParams(location.search);
    input.value=(params.get('q') || '').slice(0,120);
    type.value=['facility','doctor','mixed'].includes(params.get('type')) ? params.get('type') : '';
    sort.value=['members','title'].includes(params.get('sort')) ? params.get('sort') : 'updated';
    render(false,false);
  });
  list.querySelectorAll('img').forEach(image=>{
    const recover=()=>{
      const collage=image.closest('.tl-collage');
      image.closest('.tl-collage-cell')?.remove();
      if (!collage) return;
      const remaining=collage.querySelectorAll('.tl-collage-cell').length;
      if (remaining) collage.dataset.count=String(remaining);
      else collage.remove();
    };
    image.addEventListener('error',recover,{once:true});
    if (image.complete && !image.naturalWidth) recover();
  });
  render(false);
})();
