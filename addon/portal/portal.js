/* Customer Portal: print / back buttons and confirmation prompts (no inline scripts, see the CSP header). */
document.addEventListener('click', function (e) {
  if (e.target.closest('[data-print]')) { window.print(); }
  if (e.target.closest('[data-back]')) { history.back(); }
  var c = e.target.closest('[data-confirm]');
  if (c && !window.confirm(c.getAttribute('data-confirm'))) { e.preventDefault(); }
});
