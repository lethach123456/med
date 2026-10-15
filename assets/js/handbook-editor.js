/* Language and translation controls are scoped to the handbook add form. */
(function () {
  "use strict";

  function syncLanguage(language, resetLink) {
    var form = language.closest("form");
    if (!form) return;
    var target = language.value === "en" ? "vi" : "en";
    var linked = form.querySelector("select[name='linked_post_id']");
    if (linked) {
      if (resetLink) linked.value = "0";
      Array.from(linked.options).forEach(function (option) {
        var locale = option.getAttribute("data-handbook-target-language");
        if (!locale) return;
        option.hidden = locale !== target;
        option.disabled = option.hidden || option.getAttribute("data-handbook-link-available") !== "1";
        if (option.selected && option.disabled) linked.value = "0";
      });
    }
    var label = form.querySelector("[data-handbook-link-label]");
    if (label) label.textContent = "Bài " + (target === "en" ? "tiếng Anh" : "tiếng Việt") + " liên kết";
    form.querySelectorAll("[data-yoast-url-prefix]").forEach(function (panel) {
      panel.setAttribute("data-yoast-url-prefix", language.value === "en" ? "/en/cam-nang/" : "/cam-nang/");
    });
    var title = form.querySelector("input[name='title']");
    if (title) title.dispatchEvent(new Event("input", { bubbles: true }));
  }

  document.addEventListener("change", function (event) {
    if (event.target.matches("select[data-handbook-language]")) syncLanguage(event.target, true);
  });

  function initialize() {
    document.querySelectorAll("select[data-handbook-language]").forEach(function (language) {
      syncLanguage(language, false);
    });
    var error = document.querySelector('[data-handbook-import-error]');
    if (error) error.focus();
  }
  document.addEventListener('submit', function (event) {
    if (!event.target.matches('form[data-handbook-import]')) return;
    var button = event.target.querySelector('[data-handbook-import-submit]');
    if (button) { button.disabled = true; button.textContent = 'Đang tạo bài nháp…'; }
  });
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('[data-handbook-import-submit]').forEach(function (button) {
      button.disabled = false; button.textContent = 'Tạo bài nháp';
    });
  });
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", initialize);
  else initialize();
})();
