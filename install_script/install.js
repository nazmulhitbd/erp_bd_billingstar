/* Copy buttons, the two ways of connecting the database, and the install itself: one step at a time, so a failure says where it stopped. */
document.querySelectorAll('[data-copy]').forEach(function (button) {
  button.addEventListener('click', function () {
    var text = document.getElementById(button.getAttribute('data-copy')).textContent;
    var done = function () { button.textContent = 'Copied'; setTimeout(function () { button.textContent = 'Copy'; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
    var area = document.createElement('textarea'); // http pages have no clipboard API
    area.value = text;
    document.body.appendChild(area);
    area.select();
    document.execCommand('copy');
    area.remove();
    done();
  });
});
document.querySelectorAll('input[name="mode"]').forEach(function (radio) {
  var sync = function () {
    var on = document.querySelector('input[name="mode"]:checked').value;
    document.querySelectorAll('[data-mode]').forEach(function (row) { row.hidden = row.getAttribute('data-mode') !== on; });
  };
  radio.addEventListener('change', sync);
  sync();
});
(function () {
  var source = document.getElementById('install-data');
  if (!source) return;
  var data = JSON.parse(source.textContent);
  var el = function (id) { return document.getElementById(id); };
  var at = 0;

  function fail(message) {
    el('status').textContent = 'The install stopped at: ' + data.steps[at];
    el('failed').textContent = message;
    el('failed').hidden = false;
    el('retry-row').hidden = false;
  }
  function next() {
    el('status').textContent = 'Running ' + data.files[at] + '...';
    var body = new URLSearchParams({ action: 'run', token: data.token, step: at });
    fetch('index.php', { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (r) { return r.json(); })
      .then(function (result) {
        if (!result.ok) { fail(result.error); return; }
        var row = document.createElement('li');
        row.textContent = data.files[at];
        el('log').appendChild(row);
        at += 1;
        el('fill').style.width = Math.round(at / data.steps.length * 100) + '%';
        if (result.done) { location.href = 'index.php?step=6'; return; }
        next();
      })
      .catch(function () { fail('The server did not answer. Check it is running, then try again.'); });
  }
  el('start').addEventListener('click', function () {
    el('review').hidden = true;
    el('progress').hidden = false;
    next();
  });
  el('retry').addEventListener('click', function () {
    el('failed').hidden = true;
    el('retry-row').hidden = true;
    at = 0; // every step is safe to run again on the database this install made
    el('log').textContent = '';
    el('fill').style.width = '0';
    next();
  });
})();
