/*
 * One button design for every PetaBill page: a solid colour and an icon chosen from the button's own words.
 * Loaded on every page with dialog.js (bs_dialog_start in addon/inc/security.php); pages need no changes.
 *   blue    Save, Search, Print, Add/New, Edit, Download/Export, Upload/Import, Email, Next…   (and anything else)
 *   green   OK, Approve, Verify, Activate, Pay/Receive
 *   red     Delete, Remove, Void, Reject, Block
 *   grey    Cancel, Close, Back, Reset, Help, Log out
 *   teal    View, Preview, Show, Info, Details, Refresh
 *   yellow  Hold, Warning
 *   light   arrow-only buttons (◀ ▶) and the filter tabs that are not chosen
 * A page's own danger / success / warning / info class still wins. Icons are inline SVG, so nothing loads from outside
 * and the portals' strict Content-Security-Policy is no obstacle. Add class "no-bsb" to keep a button as it is.
 */
(function () {
  'use strict';
  if (window.BSButtons) return;

  var ICONS = {
    check: 'M5 12.5l4.5 4.5L19 7',
    'check-circle': 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM8 12.5l2.8 2.8L16.2 9.6',
    x: 'M6 6l12 12M18 6L6 18',
    'x-circle': 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM9 9l6 6M15 9l-6 6',
    trash: 'M4 7h16M10 7V4.5h4V7M6.5 7l1 13h9l1-13M10 11v5.5M14 11v5.5',
    ban: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM5.6 5.6l12.8 12.8',
    save: 'M5 4h11.5L20 7.5V20H5zM8 4v5h7.5V4M8 20v-6h8v6',
    search: 'M11 4.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13zM16 16l4.5 4.5',
    print: 'M7 9V4h10v5M6.5 17H4v-7.5h16V17h-2.5M7 14h10v6H7z',
    download: 'M12 4v11M7 10.5l5 5 5-5M5 20h14',
    upload: 'M12 20V9M7 13.5l5-5 5 5M5 4h14',
    plus: 'M12 5v14M5 12h14',
    pencil: 'M4 20h4.2L19.5 8.7a2.1 2.1 0 0 0-3-3L5.2 17zM14.5 7.5l3 3',
    eye: 'M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12zM12 9.2a2.8 2.8 0 1 0 0 5.6 2.8 2.8 0 0 0 0-5.6z',
    info: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 11v6M12 7.6v.2',
    warning: 'M12 3.5L21.5 20h-19zM12 10v4.5M12 17.4v.2',
    question: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM9.6 9.6a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .9-1 1.6v.4M12 16.9v.2',
    refresh: 'M19.5 11A7.5 7.5 0 1 0 17.3 16.3M20 4.5V11h-6.5',
    'arrow-left': 'M19 12H5M11 6l-6 6 6 6',
    'arrow-right': 'M5 12h14M13 6l6 6-6 6',
    mail: 'M3.5 6h17v12h-17zM3.5 7l8.5 6 8.5-6',
    send: 'M21 3L10.5 13.5M21 3l-6.5 18-4-7.5L3 9.5z',
    copy: 'M9 9h11v11H9zM5.5 15H4V4h11v1.5',
    cut: 'M6 4.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM6 14.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM8.2 8.4L20 18M8.2 15.6L20 6',
    paste: 'M9 3.5h6v3H9zM7.5 5H5v16h14V5h-2.5',
    calendar: 'M4 6h16v14H4zM4 10.5h16M8 3.5v4M16 3.5v4',
    clock: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7.5V12l3 2',
    cash: 'M2.5 6.5h19v11h-19zM12 9.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM6 12h.01M18 12h.01',
    login: 'M10 17l5-5-5-5M15 12H3.5M14 4h6v16h-6',
    logout: 'M15 17l5-5-5-5M20 12H8.5M10 4H4v16h6',
    'user-plus': 'M9 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M19 8v6M16 11h6',
    list: 'M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01',
    filter: 'M4 5h16l-6.2 7.2V18l-3.6 2v-7.8z',
    clip: 'M20 11.5l-8.3 8.3a5 5 0 0 1-7.1-7.1L13 4.3a3.3 3.3 0 0 1 4.7 4.7l-8.5 8.5a1.7 1.7 0 0 1-2.4-2.4l7.8-7.8',
    truck: 'M2.5 6h11.5v10H2.5zM14 9.5h4l3 3.5v3h-7M6.5 16a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM17 16a2 2 0 1 0 0 4 2 2 0 0 0 0-4z',
    receipt: 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h3',
    swap: 'M7 7.5h13M16.5 4L20 7.5 16.5 11M17 16.5H4M7.5 13L4 16.5 7.5 20',
    pause: 'M8.5 5v14M15.5 5v14',
    gear: 'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6zM12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M5.3 18.7l2.1-2.1M16.6 7.4l2.1-2.1',
    share: 'M14 5l7 7-7 7M21 12H10a6.5 6.5 0 0 0-6.5 6.5',
    home: 'M3.5 11L12 4l8.5 7M6 9.5V20h12V9.5'
  };

  /* First match wins. The words are the button's text in lower case, symbols removed. */
  var RULES = [
    [/^(sign ?in|log ?in|login)\b/, 'primary', 'login'],
    [/^(sign ?up|register|create account)/, 'primary', 'user-plus'],
    [/^(log ?out|sign ?out)/, 'secondary', 'logout'],
    [/\b(delete|trash|remove)\b/, 'danger', 'trash'],
    [/^(void|reject|fail|dishono)/, 'danger', 'x-circle'],
    [/^(block|deactivate|disable|ban|lock)\b/, 'danger', 'ban'],
    [/^(ok|okay|success|done|yes)\b/, 'success', 'check'],
    [/^(approve|accept|verify|activate|enable|confirm|complete|settle|post)\b|mark .*paid/, 'success', 'check-circle'],
    [/^(pay|receive|collect)\b|cash receipt/, 'success', 'cash'],
    [/^(cancel|close|no|dismiss|discard)\b/, 'secondary', 'x'],
    [/^(back|previous|prev)\b|move backward/, 'secondary', 'arrow-left'],
    [/^(next|forward|continue)\b|move forward/, 'primary', 'arrow-right'],
    [/^(reset|clear)\b/, 'secondary', 'refresh'],
    [/^(help|guide|how to)\b/, 'secondary', 'question'],
    [/^(refresh|reload|recalculate|sync)\b/, 'info', 'refresh'],
    [/^(list|list view|all .* list)$/, 'primary', 'list'],
    [/^(info|details?|more info|additional info)\b/, 'info', 'info'],
    [/\b(view|preview|show)\b/, 'info', 'eye'],
    [/^(hold|pause|suspend)\b/, 'warning', 'pause'],
    [/^(warning|alert)\b/, 'warning', 'warning'],
    [/^(save|update)\b/, 'primary', 'save'],
    [/^(search|find|lookup|go)\b|\bsearch\b/, 'primary', 'search'],
    [/\bprint\b/, 'primary', 'print'],
    [/\b(download|export|excel|csv|pdf)\b/, 'primary', 'download'],
    [/\b(upload|import)\b/, 'primary', 'upload'],
    [/\battach/, 'primary', 'clip'],
    [/\b(e-?mail|mail)\b/, 'primary', 'mail'],
    [/^(send|submit)\b/, 'primary', 'send'],
    [/^(copy|duplicate|clone)\b/, 'primary', 'copy'],
    [/^cut$/, 'primary', 'cut'],
    [/^paste\b/, 'primary', 'paste'],
    [/\b(schedule|calendar|date)\b/, 'primary', 'calendar'],
    [/\b(time|clock)\b/, 'primary', 'clock'],
    [/\b(challan|deliver|dispatch)\b/, 'primary', 'truck'],
    [/\b(receipt|invoice|bill)\b/, 'primary', 'receipt'],
    [/\b(exchange|swap|transfer|move)\b/, 'primary', 'swap'],
    [/^(add|new|create)\b/, 'primary', 'plus'],
    [/^(edit|modify|change)\b/, 'primary', 'pencil'],
    [/^(apply|filter)\b/, 'primary', 'filter'],
    [/\b(setting|config)/, 'secondary', 'gear'],
    [/\bshare\b/, 'primary', 'share'],
    [/^(home|dashboard)$/, 'primary', 'home']
  ];

  var SELECTOR = 'button, input[type=submit], input[type=button], input[type=reset], a.btn, label.btn, .FormButton, .rma-btn, .cs-btn, .ui-act';
  var SKIP_INSIDE = '.no-bsb, .bsd-backdrop, .bsg-bar, #navigation, .app-header, .app-nav, .user-drop, .bell-menu, .suggestions, [role=tablist], .period, .ui-find, .datepicker, .ui-datepicker, .menu-search, .sales-nav, .acc-nav';
  var SKIP_SELF = /(^|\s)(no-bsb|close|nav-top|bsg-step|bsg-help|bsg-fab|bsd-btn|bell-btn|user-chip|nav-toggle|side-collapse|lg-eye|chip|btn-close|bsb-skip)(\s|$)/;

  function words(el) {
    var t = el.tagName === 'INPUT' ? el.value : el.textContent;
    return String(t || '').replace(/[»«…+*:]/g, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
  }
  function choose(el, text) {
    var cls = ' ' + (el.className && el.className.baseVal === undefined ? el.className : '') + ' ';
    if (!/[a-z]/.test(text)) return { tone: 'light', icon: '' }; // ◀ ▶ ❚❚ ×
    var hit = null;
    for (var i = 0; i < RULES.length; i++) if (RULES[i][0].test(text)) { hit = RULES[i]; break; }
    var tone = hit ? hit[1] : 'primary', icon = hit ? hit[2] : '';
    if (/ (btn-danger|danger) /.test(cls)) tone = 'danger';
    else if (/ btn-success /.test(cls)) tone = 'success';
    else if (/ btn-warning /.test(cls)) tone = 'warning';
    else if (/ btn-info /.test(cls)) tone = 'info';
    // Filter tabs (a group of buttons): the chosen one is blue, the others light
    var group = el.closest('.btn-group');
    if (group && group.querySelectorAll(SELECTOR).length > 2 && tone === 'primary' && !icon) {
      tone = / (btn-primary|active|primary) /.test(cls) || el.getAttribute('aria-current') ? 'primary' : 'light';
    }
    return { tone: tone, icon: icon };
  }
  function svg(name) {
    var n = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    n.setAttribute('viewBox', '0 0 24 24');
    n.setAttribute('aria-hidden', 'true');
    n.setAttribute('class', 'bsb-ico');
    var p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    p.setAttribute('d', ICONS[name]);
    n.appendChild(p);
    return n;
  }
  function dataUri(name, colour) {
    var s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="' + colour + '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="' + ICONS[name] + '"/></svg>';
    return 'url("data:image/svg+xml,' + encodeURIComponent(s) + '")';
  }
  var INK = { primary: '#fff', success: '#fff', danger: '#fff', secondary: '#fff', info: '#062a33', warning: '#212529', light: '#212529' };

  function decorate(el) {
    if (SKIP_SELF.test(typeof el.className === 'string' ? el.className : '') || el.closest(SKIP_INSIDE)) return;
    if (el.type === 'hidden' || el.type === 'file' || el.type === 'image') return;
    var text = words(el);
    var hasOwnIcon = el.tagName !== 'INPUT' && el.querySelector('i, img, svg:not(.bsb-ico), .glyphicon, .bi, .fa');
    var row = el.classList.contains('ui-act'); // table row actions keep their pill look and only get the icon
    if (el.dataset.bsbText !== text || el.dataset.bsbRow !== String(row) || !el.classList.contains(row ? 'bsb-row' : 'bsb')) {
      el.dataset.bsbRow = String(row);
      var c = choose(el, text);
      el.dataset.bsbText = text;
      el.dataset.bsbIcon = hasOwnIcon ? '' : c.icon;
      if (!row) {
        el.classList.remove('bsb-primary', 'bsb-success', 'bsb-danger', 'bsb-secondary', 'bsb-info', 'bsb-warning', 'bsb-light');
        if (!el.classList.contains('bsb')) { // first dressing: show the colour at once, without fading in from the old look
          el.classList.add('bsb-init');
          requestAnimationFrame(function () { requestAnimationFrame(function () { el.classList.remove('bsb-init'); }); });
        }
        el.classList.add('bsb', 'bsb-' + c.tone);
        if (el.closest('td, th') || /(^|\s)(btn-sm|btn-xs|small)(\s|$)/.test(el.className)) el.classList.add('bsb-sm');
      } else {
        el.classList.remove('bsb', 'bsb-sm', 'bsb-primary', 'bsb-success', 'bsb-danger', 'bsb-secondary', 'bsb-info', 'bsb-warning', 'bsb-light');
        el.classList.add('bsb-row');
      }
      if (el.tagName === 'INPUT') {
        // !important here, or the colour rule (also !important) would hide the icon
        if (el.dataset.bsbIcon) { el.classList.add('bsb-input-ico'); el.style.setProperty('background-image', dataUri(el.dataset.bsbIcon, row ? (el.classList.contains('ui-act-danger') ? '#b91c1c' : '#1d4ed8') : INK[c.tone]), 'important'); }
        else { el.classList.remove('bsb-input-ico'); el.style.removeProperty('background-image'); }
      }
    }
    if (el.tagName !== 'INPUT') {
      var current = el.querySelector(':scope > .bsb-ico');
      var want = el.dataset.bsbIcon;
      if (current && (!want || current.getAttribute('data-name') !== want)) { current.remove(); current = null; }
      if (want && !current) { var ico = svg(want); ico.setAttribute('data-name', want); el.insertBefore(ico, el.firstChild); }
    }
  }
  function run(root) {
    (root || document).querySelectorAll(SELECTOR).forEach(decorate);
    if (root && root.matches && root.matches(SELECTOR)) decorate(root);
  }

  /* Buttons drawn later by the page (rows of a grid, a renamed Save button) are dressed as they appear. */
  var pending = [];
  var timer = null;
  function flush() {
    timer = null;
    var list = pending; pending = [];
    list.forEach(function (n) { if (n.isConnected) { var b = n.closest ? n.closest(SELECTOR) : null; if (b) decorate(b); run(n); } });
  }
  function start() {
    run(document);
    if (!window.MutationObserver) return;
    new MutationObserver(function (records) {
      records.forEach(function (r) {
        var n = r.type === 'characterData' ? r.target.parentElement : r.target;
        if (n && n.nodeType === 1 && !(n.closest && n.closest('.bsb-ico'))) pending.push(n);
      });
      if (pending.length && !timer) timer = setTimeout(flush, 30);
    }).observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['value'] });
  }
  window.BSButtons = { refresh: run, icons: ICONS, rules: RULES };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
