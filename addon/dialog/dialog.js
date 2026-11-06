/*
 * PetaBill popups. One look for every question and message: a round icon, a title, the message and the buttons.
 *
 *   BSDialog.confirm('Delete this invoice?')            -> Promise<boolean>; "delete", "remove", "cancel"… turn it red
 *   BSDialog.success('Saved.')  .error(…)  .warning(…)  .info(…)  .tip(…)   -> Promise (resolves when closed)
 *   BSDialog.show({ type, title, message, ok, cancel, note, steps: [..], ask: true, remember: 'key' })
 *     steps: a numbered list under the message. ask: two buttons on any kind (Esc or a click outside answers null).
 *     remember: adds "Don't show this again"; BSDialog.remembered(key) is true once it was ticked.
 *
 * Every page gets this file from bs_dialog_start() (addon/inc/security.php), so the older pages need no changes:
 *  - alert() shows the matching popup. A message that is still open when the page moves on is shown again on the next page.
 *  - confirm() shows a question. The browser's confirm() must answer at once, so the first answer is "no" (the click or
 *    submit is cancelled); "Yes" then repeats that same click, submit or change with the answer "yes". Inline code such as
 *    onclick="return confirm('…')" therefore works as before.
 *  - Messages the server leaves after a save ([data-popup="success|error|warning|info|tip"]) open as popups.
 * Inside the admin frame the popup opens in the main window, so it is centred on the screen and not on a page that may be
 * taller than the window.
 */
(function () {
  'use strict';
  if (window.BSDialog) return;
  var nativeAlert = window.alert, nativeConfirm = window.confirm;

  /* ---------------------------------------------------------------- the popup, drawn in this window */
  var TYPES = {
    confirm: { tone: 'blue', icon: 'question', title: '', ok: 'Yes, proceed', cancel: 'Close' },
    danger: { tone: 'red', icon: 'trash', title: '', ok: 'Yes, delete', cancel: 'Cancel' },
    success: { tone: 'green', icon: 'check', title: 'Success', ok: 'OK' },
    error: { tone: 'red', icon: 'cross', title: 'Failed', ok: 'OK' },
    warning: { tone: 'amber', icon: 'alert', title: 'Please check', ok: 'OK' },
    info: { tone: 'blue', icon: 'info', title: 'Information', ok: 'OK' },
    tip: { tone: 'violet', icon: 'bulb', title: 'Recommendation', ok: 'Got it' }
  };
  var ICONS = {
    check: '<path class="draw" d="M5.5 12.5l4.2 4.2 8.8-9.4"/>',
    cross: '<path class="draw" d="M7 7l10 10M17 7L7 17"/>',
    alert: '<path d="M12 5.5v8.5"/><circle class="dot" cx="12" cy="18.3" r="1.5"/>',
    info: '<circle class="dot" cx="12" cy="6" r="1.5"/><path d="M12 10.5v8"/>',
    question: '<path d="M8.8 8.6a3.3 3.3 0 1 1 4.6 3.05c-.85.37-1.4 1.2-1.4 2.12v.73"/><circle class="dot" cx="12" cy="18.4" r="1.4"/>',
    trash: '<path d="M4.5 7h15M9.5 7V4.8h5V7M6.8 7l.9 12.2h8.6l.9-12.2M10.3 10.6v5.2M13.7 10.6v5.2"/>',
    bulb: '<path d="M9.2 17.6h5.6M10.2 20.6h3.6M12 3.4a5.9 5.9 0 0 0-3.4 10.7c.6.45.9 1.1.9 1.85v.2h5v-.2c0-.75.3-1.4.9-1.85A5.9 5.9 0 0 0 12 3.4z"/>'
  };
  var queue = [];
  var open = null;

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }

  function render(item) {
    var o = item.opts, t = TYPES[o.type] || TYPES.info;
    var asking = o.type === 'confirm' || o.type === 'danger' || !!o.ask;
    var id = 'bsd' + Date.now().toString(36);
    var backdrop = el('div', 'bsd-backdrop');
    var card = el('div', 'bsd-card bsd-' + t.tone);
    card.setAttribute('role', asking ? 'alertdialog' : 'dialog');
    card.setAttribute('aria-modal', 'true');
    card.tabIndex = -1;
    var icon = el('div', 'bsd-icon');
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = '<svg viewBox="0 0 24 24">' + ICONS[t.icon] + '</svg>';
    card.appendChild(icon);
    var title = o.title !== undefined ? o.title : t.title;
    var labelled;
    if (title) {
      labelled = el('h2', 'bsd-title', title);
      card.appendChild(labelled);
    }
    if (o.message) {
      var msg = el('p', asking && !title ? 'bsd-question' : 'bsd-msg', o.message);
      msg.id = id + 'm';
      card.appendChild(msg);
      card.setAttribute('aria-describedby', msg.id);
      labelled = labelled || msg;
    }
    if (labelled) { labelled.id = labelled.id || id + 't'; card.setAttribute('aria-labelledby', labelled.id); }
    if (o.steps && o.steps.length) {
      var list = el('ol', 'bsd-steps');
      o.steps.forEach(function (s) {
        var li = el('li');
        if (typeof s === 'string') { li.textContent = s; }
        else { li.appendChild(el('b', '', s.title)); if (s.text) li.appendChild(el('span', '', s.text)); if (s.done) li.classList.add('bsd-done'); }
        list.appendChild(li);
      });
      card.appendChild(list);
    }
    var remember = null;
    if (o.remember) {
      remember = el('label', 'bsd-remember');
      var box = el('input');
      box.type = 'checkbox';
      remember.appendChild(box);
      remember.appendChild(document.createTextNode(" Don't show this again"));
      card.appendChild(remember);
    }
    if (o.note) {
      var note = el('p', 'bsd-note');
      note.appendChild(el('b', '', 'Tip:'));
      note.appendChild(document.createTextNode(' ' + o.note));
      card.appendChild(note);
    }
    var actions = el('div', 'bsd-actions' + (asking ? '' : ' bsd-single'));
    var cancel = null;
    if (asking) {
      cancel = el('button', 'bsd-btn bsd-cancel', o.cancel || t.cancel);
      cancel.type = 'button';
      actions.appendChild(cancel);
    }
    var ok = el('button', 'bsd-btn bsd-ok', o.ok || t.ok);
    ok.type = 'button';
    actions.appendChild(ok);
    card.appendChild(actions);
    backdrop.appendChild(card);

    var before = document.activeElement;
    function close(answer) {
      if (open !== item) return;
      document.removeEventListener('keydown', onKey, true);
      if (remember && remember.firstChild.checked) setRemembered(o.remember);
      backdrop.classList.add('bsd-closing');
      setTimeout(function () {
        backdrop.remove();
        if (!queue.length) document.documentElement.classList.remove('bsd-open');
        try { if (before && before.focus && document.contains(before)) before.focus({ preventScroll: true }); } catch (e) {}
        open = null;
        item.resolve(answer);
        next();
      }, 130);
    }
    function onKey(e) {
      if (e.key === 'Escape') { e.preventDefault(); close(asking ? null : true); } // a question closed without an answer: null
      else if (e.key === 'Tab') { // keep the focus on the popup's buttons
        var list = cancel ? [cancel, ok] : [ok];
        var i = list.indexOf(document.activeElement);
        e.preventDefault();
        list[(i + (e.shiftKey ? list.length - 1 : 1)) % list.length].focus();
      }
    }
    ok.addEventListener('click', function () { close(true); });
    if (cancel) cancel.addEventListener('click', function () { close(false); });
    if (remember) remember.firstChild.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); close(true); } });
    backdrop.addEventListener('mousedown', function (e) {
      if (e.target !== backdrop) return;
      if (asking) { // a question needs an answer: nudge instead of closing
        card.classList.remove('bsd-shake');
        void card.offsetWidth;
        card.classList.add('bsd-shake');
      } else {
        close(true);
      }
    });
    document.addEventListener('keydown', onKey, true);
    document.documentElement.classList.add('bsd-open');
    (document.body || document.documentElement).appendChild(backdrop);
    // A question that deletes starts on Cancel, so a stray Enter cannot delete.
    (o.type === 'danger' && cancel ? cancel : ok).focus({ preventScroll: true });
    item.close = close;
  }

  function next() {
    if (open || !queue.length) return;
    if (!document.body) { document.addEventListener('DOMContentLoaded', next, { once: true }); return; }
    open = queue.shift();
    render(open);
  }

  function showHere(opts) {
    return new Promise(function (resolve) {
      queue.push({ opts: opts, resolve: resolve });
      next();
    });
  }

  /* ---------------------------------------------------------------- open in the main window when this is a page in its frame */
  var host = null;
  try {
    if (window.top !== window && window.top.location.origin === location.origin && window.top.BSDialog && window.top.BSDialog.host) {
      host = window.top.BSDialog;
    }
  } catch (e) { host = null; }

  function show(opts) {
    opts = typeof opts === 'string' ? { message: opts } : Object.assign({}, opts || {});
    if (!TYPES[opts.type]) opts.type = 'info';
    opts.message = opts.message === undefined || opts.message === null ? '' : String(opts.message);
    if (host) {
      try { return host.show(opts); } catch (e) { host = null; }
    }
    return showHere(opts);
  }

  /* ---------------------------------------------------------------- reading a message the older pages wrote for alert() */
  function guessType(message) {
    var m = String(message).toLowerCase();
    if (/\b(error|fail(ed|ure)?|could ?not|cannot|can't|unable|denied|not allowed|invalid|wrong|refused|blocked|expired)\b/.test(m)) return 'error';
    if (/\b(saved|success(ful(ly)?)?|done|completed|updated|approved|posted|created|added|deleted|removed|sent|submitted)\b/.test(m) && !/\bnot\b/.test(m)) return 'success';
    if (/\b(please|select|enter|must|required|choose|missing|at ?least|no record|there (are|is) no|only|maximum|minimum|exceed|already)\b/.test(m)) return 'warning';
    return 'info';
  }
  var DANGER = /\b(delete|remove|cancel|reverse|dishono(u)?r|void|discard|reject|unassign|clear|erase|drop)\w*/i;
  function confirmOpts(message, extra) {
    var o = Object.assign({ type: 'confirm', message: message }, extra || {});
    var verb = String(message).match(DANGER);
    if (verb && o.type === 'confirm' && !extra) {
      o.type = 'danger';
      var word = verb[1].toLowerCase().replace(/^dishono(u)?r$/, 'dishonour');
      o.ok = 'Yes, ' + word;
    }
    return o;
  }

  function setRemembered(key) { try { localStorage.setItem('bsd_skip_' + key, '1'); } catch (e) {} }
  function remembered(key) { try { return localStorage.getItem('bsd_skip_' + key) === '1'; } catch (e) { return false; } }

  var api = {
    remembered: remembered,
    forget: function (key) { try { localStorage.removeItem('bsd_skip_' + key); } catch (e) {} },
    host: !host, // this window draws popups for its frames
    show: show,
    confirm: function (message, extra) { return show(confirmOpts(message, extra)); },
    alert: function (message, extra) { return show(Object.assign({ type: guessType(message), message: message }, extra || {})); },
    success: function (message, extra) { return show(Object.assign({ type: 'success', message: message }, extra || {})); },
    error: function (message, extra) { return show(Object.assign({ type: 'error', message: message }, extra || {})); },
    warning: function (message, extra) { return show(Object.assign({ type: 'warning', message: message }, extra || {})); },
    info: function (message, extra) { return show(Object.assign({ type: 'info', message: message }, extra || {})); },
    tip: function (message, extra) { return show(Object.assign({ type: 'tip', message: message }, extra || {})); },
    native: { alert: nativeAlert.bind(window), confirm: nativeConfirm.bind(window) }
  };
  window.BSDialog = api;

  /* ---------------------------------------------------------------- alert(): same words, new look; kept across a page change */
  var CARRY = 'bsd_carry';
  var shown = []; // this page's alert() messages not yet closed
  window.alert = function (message) {
    var entry = { message: message === undefined ? '' : String(message), t: Date.now() };
    shown.push(entry);
    api.alert(entry.message).then(function () { shown.splice(shown.indexOf(entry), 1); });
  };
  window.addEventListener('pagehide', function () {
    if (host || !shown.length) return; // the main window keeps showing a frame's popups by itself
    try { sessionStorage.setItem(CARRY, JSON.stringify(shown.slice(-3))); } catch (e) {}
  });
  try {
    var carried = JSON.parse(sessionStorage.getItem(CARRY) || '[]');
    sessionStorage.removeItem(CARRY);
    carried.forEach(function (c) { if (Date.now() - c.t < 30000) api.alert(c.message); });
  } catch (e) {}

  /* ---------------------------------------------------------------- confirm(): ask, then repeat the action on "Yes" */
  var last = { el: null, kind: '', submitter: null, at: 0 };
  function remember(kind) {
    return function (e) {
      var t = e.target;
      if (kind === 'click' && t.closest) t = t.closest('a, button, input, select, label, [onclick]') || t;
      last = { el: t, kind: kind, submitter: e.submitter || null, at: Date.now() };
    };
  }
  document.addEventListener('click', remember('click'), true);
  document.addEventListener('submit', remember('submit'), true);
  document.addEventListener('change', remember('change'), true);

  var approved = []; // questions already answered "Yes" while their action repeats
  var asked = false;
  function repeat(a) {
    if (a.kind === 'submit' && a.el.tagName === 'FORM') {
      if (a.el.requestSubmit) {
        try { if (a.submitter && a.submitter.form === a.el) a.el.requestSubmit(a.submitter); else a.el.requestSubmit(); }
        catch (e) { a.el.requestSubmit(); }
      } else {
        a.el.submit();
      }
    } else if (a.kind === 'change') {
      a.el.dispatchEvent(new Event('change', { bubbles: true }));
    } else if (typeof a.el.click === 'function') {
      a.el.click();
    }
  }
  window.confirm = function (message) {
    message = message === undefined ? '' : String(message);
    if (approved.indexOf(message) !== -1) return true; // kept until the action has run: one click may ask two questions
    var a = last;
    if (!a.el || Date.now() - a.at > 1500 || !document.contains(a.el)) {
      return nativeConfirm.call(window, message); // not from a click or a form: nothing to repeat, so ask the old way
    }
    asked = true;
    last = { el: null, kind: '', submitter: null, at: 0 };
    api.confirm(message).then(function (yes) {
      if (!yes) { approved = []; return; }
      approved.push(message);
      asked = false;
      repeat(a);
      if (!asked) approved = []; // nothing else was asked: the action has run
    });
    return false;
  };

  /* ---------------------------------------------------------------- messages the server left after a save */
  function lift() {
    document.querySelectorAll('[data-popup]').forEach(function (n) {
      if (n.getAttribute('data-popup-done')) return;
      n.setAttribute('data-popup-done', '1');
      var kind = n.getAttribute('data-popup');
      var text = (n.textContent || '').trim();
      if (!text) return;
      n.classList.add('bsd-hidden');
      show({ type: TYPES[kind] ? kind : 'info', message: text });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', lift);
  else lift();
})();
