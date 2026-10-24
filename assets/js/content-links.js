(() => {
  'use strict';

  // Presentation only: stored URLs, navigation behavior and editorial text stay intact.
  const rootSelector = [
    '.facility-detail .section-copy',
    '.facility-detail .facility-price-table',
    '.doctor-profile .dp-article',
    '.doctor-toplist .td-prose',
    '.doctor-toplist .td-article-content',
    '.page.site-typo .intro',
    '.page.site-typo .facility-content',
    '.page.site-typo .price-box',
    '.hb-article-content',
    '.ra-prose',
    '.article .body',
    '.facility-detail .facility-source-grid',
    '.doctor-toplist .td-sources',
    '.ra-source-list',
    '.intro-text',
  ].join(',');
  const excludedContext = [
    'nav', '[role="navigation"]', 'button', '[role="button"]',
    '[role="tab"]', '[role="menuitem"]', 'code', 'pre',
    '.hb-toc', '[download]',
  ].join(',');
  const blockExcludedContext = [
    'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption',
    'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'sup', '[role="doc-noteref"]',
    '.ref', '.dp-ref', '.td-ref', '.ra-ref', '.source-ref',
  ].join(',');
  const decorativeContent = [
    'svg', 'picture', 'img', 'video', 'canvas', 'iframe', 'object', 'embed',
    '[aria-hidden="true"]', '[hidden]', '[data-lucide]',
    '.ph', '.fa', '.fas', '.far', '.fab', '.fa-solid', '.fa-regular', '.fa-brands',
    '.material-icons', '[class*="material-symbols"]',
    '.sr-only', '.visually-hidden', '.rv-sr-only', '.td-sr-only',
  ].join(',');

  const meaningfulNodes = element => [...element.childNodes].filter(node => (
    node.nodeType !== Node.COMMENT_NODE
    && !(node.nodeType === Node.TEXT_NODE && node.textContent.trim() === '')
  ));
  const onlyMeaningfulChild = element => {
    const nodes = meaningfulNodes(element);
    return nodes.length === 1 ? nodes[0] : null;
  };
  const isControl = anchor => {
    if (anchor.closest(excludedContext)) return true;
    // Rich text may carry normal classes; exclude only recognizable UI controls.
    if ([...anchor.classList].some(name => /^(?:btn|button|control|toggle|tab)(?:-|$)/i.test(name))) return true;
    for (let element = anchor; element; element = element.parentElement) {
      if ([...element.classList].some(name => name.startsWith('hb-suggest'))) return true;
    }
    return false;
  };
  const hasReadableText = anchor => {
    // Do not turn gallery/image links into article cards, including linked figures.
    if (anchor.querySelector('img,picture,video,canvas,iframe,object,embed')) return false;
    const copy = anchor.cloneNode(true);
    copy.querySelectorAll(decorativeContent).forEach(element => element.remove());
    return /[\p{L}\p{N}]/u.test(copy.textContent || '');
  };
  const textLinkUrl = anchor => {
    if (isControl(anchor) || !hasReadableText(anchor)) return null;
    const href = (anchor.getAttribute('href') || '').trim();
    if (href === '' || href.startsWith('#') || /[\\\u0000-\u001f\u007f]/u.test(href)) return null;
    try {
      const url = new URL(href, document.baseURI);
      return url.protocol === 'http:' || url.protocol === 'https:' ? url : null;
    } catch (_) {
      return null;
    }
  };
  const makeBlock = anchor => {
    anchor.classList.add('med-link-block');
    const existingLabel = anchor.children.length === 1
      && anchor.firstElementChild.classList.contains('med-link-label');
    if (existingLabel) return;
    const label = document.createElement('span');
    label.className = 'med-link-label';
    // Move the real nodes; retain emphasis, event handlers and accessible link text.
    while (anchor.firstChild) label.appendChild(anchor.firstChild);
    anchor.appendChild(label);
  };

  const enhance = () => {
    const anchors = new Set();
    document.querySelectorAll(rootSelector).forEach(root => {
      root.classList.add('med-prose-links');
      root.querySelectorAll('a[href]').forEach(anchor => anchors.add(anchor));
    });
    const eligible = new Set();
    const lists = new Set();
    anchors.forEach(anchor => {
      const url = textLinkUrl(anchor);
      if (!url) return;
      eligible.add(anchor);
      anchor.classList.add('med-content-link');
      const hostname = url.hostname.toLowerCase().replace(/\.$/, '');
      const medreviewHost = hostname === 'medreview.vn' || hostname === 'www.medreview.vn';
      if (url.origin !== window.location.origin && !medreviewHost) anchor.classList.add('is-external-link');
      if (anchor.closest(blockExcludedContext)) return;
      const item = anchor.closest('li');
      if (item) {
        if (item.parentElement?.tagName === 'UL') lists.add(item.parentElement);
      } else if (anchor.parentElement?.tagName === 'P' && onlyMeaningfulChild(anchor.parentElement) === anchor) {
        makeBlock(anchor);
      }
    });

    const itemAnchor = item => {
      const child = onlyMeaningfulChild(item);
      const anchor = child?.nodeType === Node.ELEMENT_NODE && child.tagName === 'P'
        ? onlyMeaningfulChild(child) : child;
      return anchor?.nodeType === Node.ELEMENT_NODE && anchor.tagName === 'A'
        && eligible.has(anchor) && !anchor.closest(blockExcludedContext) ? anchor : null;
    };
    lists.forEach(list => {
      const items = meaningfulNodes(list);
      if (items.length === 0 || items.some(item => item.nodeType !== Node.ELEMENT_NODE || item.tagName !== 'LI')) return;
      const links = items.map(itemAnchor);
      // Keep numbered lists and mixed prose lists intact; only pure UL lists become cards.
      if (links.some(anchor => anchor === null)) return;
      list.classList.add('med-link-list');
      items.forEach((item, index) => {
        item.classList.add('med-link-item');
        makeBlock(links[index]);
      });
    });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', enhance, {once: true});
  else enhance();
})();
