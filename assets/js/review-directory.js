(() => {
  'use strict';
  const root = document.querySelector('.rv-directory');
  const form = document.getElementById('reviewDirectoryFilter');
  if (!root || !form) return;
  const en = root.dataset.locale === 'en';
  const input = document.getElementById('review-search');
  const controls = [...root.querySelectorAll('[data-review-control]')];
  const disclosure = root.querySelector('[data-review-filters]');
  const toggle = document.getElementById('reviewFilterToggle');
  const panel = document.getElementById('reviewFilterOptions');
  const heading = document.getElementById('reviewResultsHeading');
  const sortGroup = root.querySelector('[data-review-sort-group]');
  const sortSlot = root.querySelector('[data-review-sort-slot]');
  const tools = root.querySelector('[data-review-tools]');
  const results = document.getElementById('reviewResults');
  const count = document.getElementById('reviewResultCount');
  const status = document.getElementById('reviewDirectoryStatus');
  const active = document.getElementById('reviewActiveFilters');
  const badge = root.querySelector('[data-review-filter-count]');
  const loading = root.querySelector('[data-review-loading]');
  const compact = window.matchMedia('(max-width: 800px)');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  const number = new Intl.NumberFormat(en ? 'en-US' : 'vi-VN');
  const close = root.querySelector('[data-review-filter-close]');
  close.hidden = false;
  let panelAnimation = null;
  const setPanel = (open, restoreFocus = true) => {
    if (!open && restoreFocus && panel.contains(document.activeElement)) toggle.focus({preventScroll: true});
    disclosure.open = open;
    toggle.setAttribute('aria-expanded', String(open));
  };
  const arrange = () => {
    const hadFocus = sortGroup.contains(document.activeElement);
    if (compact.matches) { sortSlot.append(sortGroup); if (hadFocus) setPanel(true); }
    else tools.insertBefore(sortGroup, disclosure);
    if (hadFocus) document.getElementById('review-sort').focus({preventScroll: true});
  };
  arrange();
  compact.addEventListener('change', arrange);
  disclosure.addEventListener('toggle', () => {
    toggle.setAttribute('aria-expanded', String(disclosure.open));
    panelAnimation?.cancel();
    if (disclosure.open && !reduced.matches) panelAnimation = panel.animate(
      [{opacity: 0, transform: 'translateY(-8px) scale(.985)'}, {opacity: 1, transform: 'translateY(0) scale(1)'}],
      {duration: 300, easing: 'cubic-bezier(.16,1,.3,1)'});
    if (disclosure.open && compact.matches && panel.getBoundingClientRect().bottom > innerHeight - 12) scrollResults(false);
  });
  close.addEventListener('click', () => setPanel(false));
  document.addEventListener('pointerdown', event => { if (disclosure.open && !disclosure.contains(event.target)) setPanel(false); });
  document.addEventListener('focusin', event => { if (disclosure.open && !disclosure.contains(event.target)) setPanel(false, false); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && disclosure.open) { event.preventDefault(); setPanel(false); toggle.focus({preventScroll:true}); } });
  reduced.addEventListener('change', () => { if (reduced.matches) panelAnimation?.cancel(); });
  function scrollResults(focus = true) {
    const offset = (document.querySelector('.medical-header')?.getBoundingClientRect().height || 80) + 18;
    if (focus) heading.focus({preventScroll: true});
    window.scrollTo({top: Math.max(0, heading.getBoundingClientRect().top + scrollY - offset), behavior: reduced.matches ? 'auto' : 'smooth'});
  }
  // External form-associated controls preserve native GET submission without JS.
  if (!window.fetch || !window.AbortController) return;
  let timer, controller, requestId = 0, composing = false;
  const words = en ? {q:'Search',rating:'Rating',media:'Photos',photos:'Before / after photos',clear:'Clear all',remove:'Remove filter: ',matches:'matching articles',loading:'Updating results…',error:'Could not load results. Your current reviews are still here.',retry:'Try again',native:'Open page'}
    : {q:'Từ khóa',rating:'Điểm',media:'Ảnh',photos:'Có ảnh trước / sau',clear:'Xóa tất cả',remove:'Bỏ bộ lọc: ',matches:'bài viết phù hợp',loading:'Đang cập nhật kết quả…',error:'Chưa tải được kết quả. Danh sách hiện tại vẫn được giữ lại.',retry:'Thử lại',native:'Mở trang'};
  const urlFor = (page = 1) => {
    const url = new URL(form.action, location.origin);
    url.search = ''; url.hash = '';
    for (const [key, value] of new FormData(form)) {
      const clean = String(value).trim();
      if (clean && !(key === 'sort' && clean === 'newest')) url.searchParams.set(key, key === 'q' ? clean.slice(0,120) : clean);
    }
    if (page > 1) url.searchParams.set('page',String(page));
    return url;
  };
  const setBusy = busy => {
    results.setAttribute('aria-busy',String(busy)); loading.hidden = !busy;
    if (busy) status.textContent = words.loading;
  };
  const cancel = () => { clearTimeout(timer); controller?.abort(); requestId++; setBusy(false); };
  const syncFilters = () => {
    const values = new URLSearchParams(new FormData(form));
    const selected = ['rating','media'].filter(key => values.get(key));
    badge.textContent = String(selected.length); badge.hidden = selected.length === 0;
    active.replaceChildren();
    ['q','rating','media'].forEach(key => {
      const value = String(values.get(key) || '').trim();
      if (!value) return;
      const text = words[key] + ': ' + (key === 'rating' ? '≥ ' + value + '/5' : key === 'media' ? words.photos : value);
      const button = document.createElement('button'); button.type = 'button'; button.dataset.reviewRemove = key;
      button.setAttribute('aria-label',words.remove + text);
      const span = document.createElement('span'); span.textContent = text; button.title = text;
      const cross = document.createElement('span'); cross.textContent = '×'; cross.setAttribute('aria-hidden','true');
      button.append(span,cross); active.append(button);
    });
    active.hidden = active.childElementCount === 0;
    if (!active.hidden) { const button=document.createElement('button'); button.type='button'; button.dataset.reviewClear=''; button.className='rv-dir-clear-all'; button.textContent=words.clear; active.append(button); }
  };
  const syncControls = url => {
    input.value = (url.searchParams.get('q') || '').slice(0,120);
    controls.forEach(control => {
      const value = url.searchParams.get(control.name) || (control.name === 'sort' ? 'newest' : '');
      control.value = [...control.options].some(option => option.value === value) ? value : control.name === 'sort' ? 'newest' : '';
    });
    syncFilters();
  };
  function showError(url, options) {
    root.querySelector('.rv-dir-fetch-error')?.remove();
    const alert = document.createElement('div'); alert.className='rv-dir-fetch-error'; alert.setAttribute('role','alert');
    const message = document.createElement('p'); message.textContent=words.error;
    const retry=document.createElement('button'); retry.type='button';retry.className='rv-button';retry.textContent=words.retry;retry.addEventListener('click',()=>load(url,options));
    const native=document.createElement('a');native.className='rv-button';native.href=url.href;native.textContent=words.native;
    alert.append(message,retry,native); results.before(alert); status.textContent=words.error;
  }
  async function load(target, options = {}) {
    clearTimeout(timer);
    const url = new URL(target, location.origin);
    const canonicalPath = new URL(form.action, location.origin).pathname;
    if (url.origin !== location.origin) return;
    if (url.pathname === '/review.php' || (url.pathname === '/review' && url.searchParams.has('lang'))) {
      const legacyLocale = url.searchParams.get('lang') === 'en' ? 'en' : 'vi';
      if (legacyLocale !== root.dataset.locale) return;
      url.pathname = canonicalPath; url.searchParams.delete('lang');
    }
    if (url.pathname !== canonicalPath) return;
    controller?.abort(); controller=new AbortController(); const id=++requestId; const signal=controller.signal;
    root.querySelector('.rv-dir-fetch-error')?.remove(); syncFilters(); setBusy(true);
    const requestURL=new URL(url); requestURL.searchParams.set('partial','1');
    try {
      const response=await fetch(requestURL,{signal,credentials:'same-origin',headers:{Accept:'application/json'}});
      if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Invalid response');
      const data=await response.json();
      if (id!==requestId || signal.aborted) return;
      if (!data.ok || data.locale!==root.dataset.locale || typeof data.html!=='string' || !Number.isInteger(data.paging?.total) || !Number.isInteger(data.paging?.page)) throw new Error('Incomplete response');
      results.innerHTML=data.html;
      count.textContent=number.format(data.paging.total)+' '+words.matches;status.textContent=count.textContent;
      const normalized=new URL(url); normalized.searchParams.delete('page'); if(data.paging.page>1) normalized.searchParams.set('page',String(data.paging.page));
      syncControls(normalized);
      if (options.history !== 'none') { if(normalized.href!==location.href) history[options.history==='replace'?'replaceState':'pushState']({},'',normalized); }
      else if (normalized.href!==location.href) history.replaceState({},'',normalized);
      const canonical=document.querySelector('link[rel=canonical]');
      if(canonical) { const canonicalURL=new URL(canonical.href);canonicalURL.pathname=normalized.pathname;canonicalURL.search=normalized.search;canonical.href=canonicalURL.href; }
      const baseTitle=document.title.replace(/ — (?:Page|Trang) \d+$/,'');document.title=baseTitle+(data.paging.page>1?' — '+(en?'Page ':'Trang ')+data.paging.page:'');
      document.dispatchEvent(new CustomEvent('review:results-updated'));
      if (!reduced.matches) results.animate([{opacity:.5,transform:'translateY(6px)'},{opacity:1,transform:'translateY(0)'}],{duration:240,easing:'cubic-bezier(.16,1,.3,1)'});
      if (options.scroll) scrollResults();
    } catch(error) { if(error.name!=='AbortError' && id===requestId) showError(url,options); }
    finally { if(id===requestId) setBusy(false); }
  }
  const reset = () => { input.value='';controls.forEach(control=>{control.value=control.name==='sort'?'newest':'';});setPanel(false);input.focus({preventScroll:true});load(urlFor(1)); };
  form.addEventListener('submit',event=>{event.preventDefault();setPanel(false);load(urlFor(1),{scroll:true});});
  controls.forEach(control=>control.addEventListener('change',()=>{cancel();load(urlFor(1));}));
  const schedule = () => {cancel();if(!composing) timer=setTimeout(()=>load(urlFor(1),{history:'replace'}),380);};
  input.addEventListener('input',schedule);
  input.addEventListener('compositionstart',()=>{composing=true;cancel();});
  input.addEventListener('compositionend',()=>{composing=false;schedule();});
  root.addEventListener('click',event=>{
    const target=event.target.closest('button,a');if(!target || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button!==0) return;
    if(target.matches('[data-review-clear]')){event.preventDefault();reset();}
    else if(target.matches('[data-review-remove]')){event.preventDefault();const key=target.dataset.reviewRemove;(key==='q'?input:controls.find(control=>control.name===key)).value='';heading.focus({preventScroll:true});load(urlFor(1));}
    else if(target.closest('.rv-dir-pagination') && target.matches('a')){event.preventDefault();setPanel(false);load(target.href,{scroll:true});}
  });
  window.addEventListener('popstate',()=>{cancel();setPanel(false);const url=new URL(location.href);syncControls(url);load(url,{history:'none'});});
  syncFilters();
})();
