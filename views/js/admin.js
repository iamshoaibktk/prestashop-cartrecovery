/**
 * Abandoned Cart Recovery: back office helpers.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
(function () {
  function copyText(text, done) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () {});
      return;
    }
    var t = document.createElement('textarea');
    t.value = text;
    t.style.position = 'fixed';
    t.style.opacity = '0';
    document.body.appendChild(t);
    t.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    document.body.removeChild(t);
  }

  function flash(el) {
    el.classList.add('is-copied');
    setTimeout(function () { el.classList.remove('is-copied'); }, 1200);
  }

  function editor() {
    if (!window.tinyMCE) { return null; }
    return tinyMCE.get('cr-f-body') || tinyMCE.get('CR_ONEOFF_BODY');
  }

  document.addEventListener('click', function (e) {
    var chip = e.target.closest('[data-copy]');
    if (chip) {
      var code = chip.getAttribute('data-copy');
      var ed = editor();
      if (ed) {
        ed.insertContent(code);
        flash(chip);
        if (window.crSchedulePreview) { window.crSchedulePreview(); }
      } else {
        copyText(code, function () { flash(chip); });
      }
      return;
    }
    var btn = e.target.closest('[data-copy-target]');
    if (btn) {
      var input = document.querySelector(btn.getAttribute('data-copy-target'));
      if (input) {
        input.select();
        copyText(input.value, function () { flash(btn); });
      }
      return;
    }
    var confirmEl = e.target.closest('[data-confirm]');
    if (confirmEl && !confirmEl.disabled) {
      var name = (document.getElementById('cr-rc-name') || {}).textContent || '';
      if (!window.confirm(confirmEl.getAttribute('data-confirm').replace('%name%', name))) {
        e.preventDefault();
      }
    }
  });

  function refreshSelection() {
    var checks = document.querySelectorAll('.cr-check');
    var n = 0;
    checks.forEach(function (c) { if (c.checked) { n++; } });
    var out = document.getElementById('cr-selected');
    var btn = document.getElementById('cr-bulk-btn');
    var all = document.getElementById('cr-check-all');
    if (out) { out.textContent = n; }
    if (btn) { btn.disabled = n === 0; }
    if (all) { all.checked = n > 0 && n === checks.length; }
  }

  document.addEventListener('change', function (e) {
    if (e.target.id === 'cr-check-all') {
      document.querySelectorAll('.cr-check').forEach(function (c) { c.checked = e.target.checked; });
    }
    if (e.target.id === 'cr-check-all' || e.target.classList.contains('cr-check')) {
      refreshSelection();
    }
    if (e.target.name === 'CARTREC_DISC_TYPE') {
      var unit = document.getElementById('cr-disc-unit');
      if (unit) { unit.textContent = unit.getAttribute('data-' + e.target.value); }
    }
  });

  document.addEventListener('DOMContentLoaded', refreshSelection);
})();

/* ---------- Settings tab: one editor for every reminder step and language ---------- */
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('cr-settings');
  if (!form) { return; }
  var stepInput = document.getElementById('cr-step');
  var langInput = document.getElementById('cr-lang');
  var subject = document.getElementById('cr-f-subject');
  var title = document.getElementById('cr-f-title');
  var body = document.getElementById('cr-f-body');
  var langName = document.getElementById('cr-f-langname');
  var ready = false;

  function store(field) {
    return form.querySelector('[data-store="' + field + '"][data-step="' + stepInput.value + '"][data-lang="' + langInput.value + '"]');
  }
  function ed() { return window.tinyMCE ? tinyMCE.get('cr-f-body') : null; }

  function save() {
    store('subject').value = subject.value;
    store('title').value = title.value;
    var e = ed();
    store('body').value = (e && ready) ? e.getContent() : body.value;
  }
  function load() {
    subject.value = store('subject').value;
    title.value = store('title').value;
    var e = ed();
    if (e && ready) { e.setContent(store('body').value); } else { body.value = store('body').value; }
    if (langName && window.crLangNames) { langName.textContent = '(' + (window.crLangNames[langInput.value] || '') + ')'; }
  }
  window.crEditorReady = function () { ready = true; load(); };

  function select(selector, attr, input, value) {
    save();
    input.value = value;
    form.querySelectorAll(selector).forEach(function (b) {
      var on = b.getAttribute(attr) === String(value);
      b.classList.toggle('is-active', on);
      b.setAttribute(b.getAttribute('role') === 'tab' ? 'aria-selected' : 'aria-checked', on ? 'true' : 'false');
    });
    load();
  }

  form.addEventListener('click', function (e) {
    var tab = e.target.closest('.cr-steptab');
    if (tab) { select('.cr-steptab', 'data-step', stepInput, tab.getAttribute('data-step')); return; }
    var pill = e.target.closest('.cr-pill[data-lang]');
    if (pill) { select('.cr-pill[data-lang]', 'data-lang', langInput, pill.getAttribute('data-lang')); }
  });
  form.addEventListener('submit', save);
  load();
});

/* ---------- Appearance language panes ---------- */
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('cr-design');
  if (!form) { return; }
  form.addEventListener('click', function (e) {
    var pill = e.target.closest('.cr-pill[data-lang]');
    if (!pill) { return; }
    var id = pill.getAttribute('data-lang');
    document.getElementById('cr-lang').value = id;
    form.querySelectorAll('.cr-pill[data-lang]').forEach(function (b) {
      var on = b === pill;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-checked', on ? 'true' : 'false');
    });
    form.querySelectorAll('[data-lang-pane]').forEach(function (p) { p.hidden = p.getAttribute('data-lang-pane') !== id; });
    if (window.crSchedulePreview) { window.crSchedulePreview(); }
  });
});

/* ---------- Appearance and one-off tabs: design picker + live preview ---------- */
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('form.cr-live');
  if (!form) { return; }
  var frame = document.getElementById('cr-preview-frame');
  var stage = document.getElementById('cr-preview-stage');
  var scaler = document.getElementById('cr-preview-scaler');
  var panel = form.querySelector('.cr-preview');
  var device = 'desktop';
  var timer = null;
  var seq = 0;

  function frameWidth() { return device === 'mobile' ? 390 : 640; }

  function fit() {
    var w = frameWidth();
    frame.style.width = w + 'px';
    var avail = stage.clientWidth - 32;
    var scale = Math.min(1, avail / w);
    var h = 1200;
    try {
      var doc = frame.contentDocument;
      if (doc && doc.body) { h = Math.max(doc.documentElement.scrollHeight, doc.body.scrollHeight); }
    } catch (e) {}
    frame.style.height = h + 'px';
    scaler.style.width = w + 'px';
    scaler.style.transform = 'scale(' + scale + ')';
    scaler.style.marginLeft = Math.max(0, (avail - w * scale) / 2) + 'px';
    stage.style.height = (h * scale + 32) + 'px';
  }

  function refresh() {
    var id = ++seq;
    panel.classList.add('is-loading');
    if (form.getAttribute('data-rte') && window.tinyMCE) { tinyMCE.triggerSave(); }
    var fd = new FormData(form);
    fd.delete('CARTREC_HERO');
    fd.append('submitCrPreview', '1');
    fd.append('cr_live', '1');
    fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        if (id !== seq) { return; }
        frame.srcdoc = html;
      })
      .catch(function () { panel.classList.remove('is-loading'); });
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(refresh, 400);
  }
  window.crSchedulePreview = schedule;

  // One-off reminder: the summary follows the selected cart; another cart reloads its texts.
  var cartSel = document.getElementById('cr_cart');
  function showRecipient() {
    if (!cartSel) { return; }
    var o = cartSel.options[cartSel.selectedIndex];
    var img = document.getElementById('cr-rc-img');
    document.getElementById('cr-rc-name').textContent = o.getAttribute('data-name');
    document.getElementById('cr-rc-info').textContent = o.getAttribute('data-info');
    document.getElementById('cr-rc-first').textContent = o.getAttribute('data-first') || o.getAttribute('data-name');
    var st = document.getElementById('cr-rc-status');
    st.textContent = o.getAttribute('data-status');
    st.classList.toggle('cr-badge--sent', o.getAttribute('data-sent') === '1');
    if (o.getAttribute('data-img')) { img.src = o.getAttribute('data-img'); img.style.visibility = 'visible'; } else { img.style.visibility = 'hidden'; }
  }
  showRecipient();
  if (cartSel) {
    cartSel.addEventListener('change', function () {
      window.location.href = cartSel.getAttribute('data-reload') + '&cr_cart=' + encodeURIComponent(cartSel.value);
    });
  }

  frame.addEventListener('load', function () {
    panel.classList.remove('is-loading');
    fit();
    setTimeout(fit, 300); // images
  });
  window.addEventListener('resize', fit);

  function setColor(id, value) {
    var hex = document.getElementById(id);
    var pick = form.querySelector('.cr-color__pick[data-for="' + id + '"]');
    hex.value = value;
    if (pick) { pick.value = value; }
  }

  function updateThumb() {
    var active = form.querySelector('.cr-tpl__radio:checked');
    form.querySelectorAll('.cr-tpl').forEach(function (card) {
      var radio = card.querySelector('.cr-tpl__radio');
      card.classList.toggle('is-active', radio === active);
      var thumb = card.querySelector('.cr-tpl__thumb');
      if (radio === active) {
        thumb.style.setProperty('--a', document.getElementById('CARTREC_ACCENT').value);
        thumb.style.setProperty('--h', document.getElementById('CARTREC_HEADER').value);
      } else {
        thumb.style.setProperty('--a', card.getAttribute('data-accent'));
        thumb.style.setProperty('--h', card.getAttribute('data-header'));
      }
    });
  }

  function resetColors() {
    var card = form.querySelector('.cr-tpl__radio:checked').closest('.cr-tpl');
    setColor('CARTREC_ACCENT', card.getAttribute('data-accent'));
    setColor('CARTREC_HEADER', card.getAttribute('data-header'));
  }

  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t === cartSel) { return; }
    if (t.classList.contains('cr-tpl__radio')) {
      resetColors(); // a new design starts from its own colours
    }
    if (t.classList.contains('cr-color__pick')) {
      document.getElementById(t.getAttribute('data-for')).value = t.value;
    }
    if (t.classList.contains('cr-color__hex') && /^#[0-9a-fA-F]{6}$/.test(t.value)) {
      var p = form.querySelector('.cr-color__pick[data-for="' + t.id + '"]');
      if (p) { p.value = t.value; }
    }
    if (t.type === 'file') { return; }
    updateThumb();
    schedule();
  });
  form.addEventListener('input', function (e) {
    var t = e.target;
    if (t.classList.contains('cr-color__pick')) {
      document.getElementById(t.getAttribute('data-for')).value = t.value;
      updateThumb();
    }
    if (t.tagName === 'TEXTAREA' || (t.type === 'text' && !t.classList.contains('cr-color__hex')) || t.classList.contains('cr-color__pick')) {
      schedule();
    }
    if (t.classList.contains('cr-color__hex') && /^#[0-9a-fA-F]{6}$/.test(t.value)) {
      var p = form.querySelector('.cr-color__pick[data-for="' + t.id + '"]');
      if (p) { p.value = t.value; }
      updateThumb();
      schedule();
    }
  });

  var resetBtn = document.getElementById('cr-reset-colors');
  if (resetBtn) {
    resetBtn.addEventListener('click', function () {
      resetColors();
      updateThumb();
      schedule();
    });
  }

  form.querySelectorAll('.cr-seg__btn').forEach(function (b) {
    b.addEventListener('click', function () {
      device = b.getAttribute('data-device');
      form.querySelectorAll('.cr-seg__btn').forEach(function (x) {
        var on = x === b;
        x.classList.toggle('is-active', on);
        x.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      fit();
      setTimeout(fit, 50);
    });
  });

  refresh();
});
