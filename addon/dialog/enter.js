/*
 * Enter moves through a data-entry form, as in desktop billing software. Loaded on every page (bs_dialog_start).
 *  - Enter in a field goes to the next field (hidden, disabled and read-only ones are skipped) and selects its text;
 *    Shift+Enter goes back one.
 *    The form is never sent by Enter; Save, or Ctrl+S / Ctrl+Enter, sends it.
 *  - A search list (customer, product, supplier): Up / Down choose, Enter picks (the first match when none is chosen)
 *    and moves on, Esc closes it.
 *  - Past the last field Enter stops on the form's own button (Save, Show, Apply); Enter there sends the form.
 *  - data-next="#id" on a field sends Enter to that field instead of the next one in the page.
 *  - Mobile No: Enter runs its Search. The last product field stops on Add; after Add the cursor goes back to Model.
 *    Enter in an empty Model field, once lines were added, jumps to the totals and payment, then to Save.
 *  - A text box such as Serial/IMEI keeps Enter for a new line; Enter on an empty line moves on.
 * Applies to POST forms with three or more fields and no password. Tab works as always. Class "no-enter" on a form
 * keeps the browser's own Enter.
 */
(function () {
  'use strict';
  if (window.BSEnter) return;

  var FIELDS = 'input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=reset]):not([type=file]):not([type=image]), select, textarea, button, a.btn';
  var STOPS = '#add-line, #x_add, #save-btn, [data-enter-stop]'; // the only buttons Enter stops on
  var RUN_ON_ENTER = { mobile: 'mobile-search', source_no: 'source-load' }; // field id -> button Enter presses first

  function visible(el) {
    if (el.offsetParent === null && getComputedStyle(el).position !== 'fixed') return false;
    var closed = el.closest('details:not([open])');
    return !(closed && !el.closest('summary'));
  }
  function usable(el) {
    if (el.disabled || el.readOnly || el.tabIndex < 0 || !visible(el)) return false;
    if (el.closest('.suggestions, .bsd-backdrop, .bsg-bar, [hidden]')) return false;
    if (el.matches('button, a.btn')) return el.matches(STOPS);
    return true;
  }
  function entryForm(el) {
    var form = el.form || el.closest('form');
    if (!form || form.classList.contains('no-enter') || (form.getAttribute('method') || 'get').toLowerCase() !== 'post') return null;
    if (form.querySelector('input[type=password]')) return null;
    var fields = Array.prototype.filter.call(form.querySelectorAll('input, select, textarea'), function (f) { return f.type !== 'hidden' && visible(f); });
    return fields.length >= 3 ? form : null;
  }
  function fieldsOf(form) { return Array.prototype.filter.call(form.querySelectorAll(FIELDS), usable); }
  function go(el) {
    if (!el) return;
    el.focus();
    if (el.select && el.matches('input:not([type=checkbox]):not([type=radio]):not([type=date]):not([type=color])')) { try { el.select(); } catch (e) {} }
    if (el.scrollIntoView) { try { el.scrollIntoView({ block: 'nearest' }); } catch (e) {} }
  }
  function hasItems() {
    var body = document.getElementById('grid-body');
    return !!body && Array.prototype.some.call(body.rows, function (r) { return !r.querySelector('td.empty'); });
  }
  function next(form, from, back) {
    var list = fieldsOf(form);
    var i = list.indexOf(from);
    if (back) return i > 0 ? list[i - 1] : null;
    // A field may name where Enter goes next (data-next="#id"), when that field can take the cursor
    var named = from.getAttribute('data-next') && document.querySelector(from.getAttribute('data-next'));
    if (named && usable(named)) return named;
    // Model left empty after lines were added: skip the rest of the product row
    if (from.id === 'e_model' && !from.value.trim() && hasItems()) {
      var section = from.closest('section, .entry, .product-form');
      if (section) {
        for (var k = i + 1; k < list.length; k++) if (!section.contains(list[k]) && list[k].id !== 'add-line') return list[k];
      }
    }
    return i === -1 ? null : list[i + 1] || null;
  }

  /** The button that sends the form, for forms that have no Save stop of their own. */
  function submitOf(form) {
    return Array.prototype.find.call(form.querySelectorAll('button[type=submit], button:not([type]), input[type=submit]'), function (b) {
      return !b.disabled && visible(b) && !/(^|\s)danger(\s|$)/.test(b.className) && !/cancel|void|delete|remove/i.test(b.value + ' ' + b.textContent);
    }) || null;
  }

  /* ---------- Search lists: keyboard choice ---------- */
  function listFor(input) {
    var box = input.parentElement && input.parentElement.querySelector('.suggestions');
    return box && !box.hidden && box.querySelector('button') ? box : null;
  }
  function move(box, step) {
    var items = Array.prototype.slice.call(box.querySelectorAll('button'));
    var i = items.findIndex(function (b) { return b.classList.contains('bsn-active'); });
    items.forEach(function (b) { b.classList.remove('bsn-active'); });
    var to = items[(i + step + items.length) % items.length];
    to.classList.add('bsn-active');
    to.scrollIntoView({ block: 'nearest' });
  }

  document.addEventListener('keydown', function (e) {
    if (e.defaultPrevented || e.isComposing || e.altKey || e.metaKey) return;
    var el = e.target;
    if (!el || !el.closest) return;
    var form = entryForm(el);
    if (!form) return;

    // Ctrl+S / Ctrl+Enter: save
    if (e.ctrlKey && (e.key === 'Enter' || e.key === 's' || e.key === 'S')) {
      var save = document.getElementById('save-btn') || form.querySelector('button[type=submit].primary, button.primary[name=action]');
      if (save && !save.disabled) { e.preventDefault(); save.click(); }
      return;
    }
    if (e.ctrlKey) return;

    var box = el.tagName === 'INPUT' ? listFor(el) : null;
    if (box && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { e.preventDefault(); move(box, e.key === 'ArrowDown' ? 1 : -1); return; }
    if (box && e.key === 'Escape') { box.hidden = true; return; }
    if (e.key !== 'Enter') return;

    if (el.matches('button, a, input[type=submit], input[type=button], input[type=reset]')) return; // Enter presses it
    if (e.shiftKey) { e.preventDefault(); go(next(form, el, true)); return; } // Shift+Enter: back one field
    if (el.tagName === 'TEXTAREA') {
      var atEnd = el.selectionStart === el.value.length;
      if (!(atEnd && (el.value === '' || /\n$/.test(el.value)))) return; // a new line
      el.value = el.value.replace(/\n+$/, '');
      el.dispatchEvent(new Event('input', { bubbles: true }));
    }
    e.preventDefault(); // never send the form with Enter

    if (box) {
      var pick = box.querySelector('button.bsn-active') || (el.value.trim() ? box.querySelector('button') : null);
      if (pick) pick.click();
      box.hidden = true;
    }
    var run = RUN_ON_ENTER[el.id] && document.getElementById(RUN_ON_ENTER[el.id]);
    if (run && !run.disabled && el.value.trim()) run.click();
    go(next(form, el) || submitOf(form)); // past the last field: the form's own button (Save, Show, Apply…)
  });

  /* After Add, the cursor is back on Model for the next product (only when the line went in and Model was cleared). */
  document.addEventListener('click', function (e) {
    var add = e.target.closest && e.target.closest('#add-line');
    if (!add) return;
    setTimeout(function () {
      var model = document.getElementById('e_model');
      if (model && !model.value.trim() && !document.querySelector('.bsd-backdrop')) go(model);
    }, 30);
  });

  window.BSEnter = { next: next, fields: fieldsOf };
})();
