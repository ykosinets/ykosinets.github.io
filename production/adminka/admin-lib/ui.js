/**
 * Adminka UI — promise-based modal dialogs (replaces prompt/confirm/alert).
 * Self-contained: injects its own styles, no dependencies.
 *
 *   AdminkaUI.form({title, submit, fields:[...]})  -> Promise<values|null>
 *   AdminkaUI.confirm({title, message, confirm})   -> Promise<boolean>
 *   AdminkaUI.alert({title, message})              -> Promise<void>
 *
 * Field spec: {name, label, value, type:'text'|'textarea'|'select'|'checkbox'|'attr',
 *              options:[{v,t}], placeholder, hint, checked, present}
 * Type 'attr' renders a presence checkbox + value input; resolves to
 * a string when present, null when unset.
 */
(function () {
  'use strict';

  var style = document.createElement('style');
  style.textContent =
    '.aui-overlay{position:fixed;inset:0;z-index:100001;background:rgba(0,0,0,.5);' +
      'display:flex;align-items:center;justify-content:center;font:14px/1.45 system-ui,sans-serif}' +
    '.aui-box{background:#fff;color:#222;border-radius:8px;width:min(440px,92vw);max-height:86vh;' +
      'display:flex;flex-direction:column;box-shadow:0 12px 40px rgba(0,0,0,.3)}' +
    '.aui-head{display:flex;align-items:center;padding:14px 18px;border-bottom:1px solid #e5e5e5}' +
    '.aui-head b{font-size:15px;margin-right:auto}' +
    '.aui-x{border:0;background:none;font-size:20px;line-height:1;padding:4px 8px;cursor:pointer;color:#777}' +
    '.aui-x:hover{color:#222}' +
    '.aui-body{padding:16px 18px;overflow:auto}' +
    '.aui-body p.aui-msg{margin:0 0 8px}' +
    '.aui-row{margin-bottom:12px}' +
    '.aui-row:last-child{margin-bottom:0}' +
    '.aui-row label.aui-l{display:block;font-size:12px;color:#666;margin-bottom:4px;text-transform:uppercase;letter-spacing:.03em}' +
    '.aui-row input[type=text],.aui-row textarea,.aui-row select{width:100%;box-sizing:border-box;' +
      'padding:8px 10px;border:1px solid #ccc;border-radius:4px;font:inherit;background:#fff;color:#222}' +
    '.aui-row textarea{min-height:84px;resize:vertical}' +
    '.aui-row input:focus,.aui-row textarea:focus,.aui-row select:focus{outline:2px solid rgba(224,123,0,.5);border-color:#e07b00}' +
    '.aui-check{display:flex;align-items:center;gap:8px;cursor:pointer}' +
    '.aui-check input{width:16px;height:16px;accent-color:#10312b}' +
    '.aui-attr{display:flex;align-items:center;gap:8px}' +
    '.aui-attr input[type=checkbox]{width:16px;height:16px;flex:none;accent-color:#10312b}' +
    '.aui-attr code{flex:none;min-width:90px;font:12px ui-monospace,monospace;color:#444}' +
    '.aui-attr input[type=text]{flex:1}' +
    '.aui-attr input[type=text]:disabled{background:#f3f3f3;color:#999}' +
    '.aui-hint{font-size:12px;color:#888;margin-top:4px}' +
    '.aui-media{display:flex;gap:8px}' +
    '.aui-media input{flex:1}' +
    '.aui-media button{flex:none;padding:8px 14px;border:1px solid #ccc;border-radius:4px;background:#f7f6f2;font:inherit;cursor:pointer}' +
    '.aui-media button:hover{border-color:#e07b00}' +
    '.aui-optrow{display:flex;align-items:center;gap:4px;margin-bottom:6px}' +
    '.aui-optrow input{flex:1}' +
    '.aui-optrow.aui-dragging{opacity:.45}' +
    '.aui-drag{border:0;background:none;padding:4px 6px;color:#999;font-size:15px;cursor:grab;touch-action:none;user-select:none}' +
    '.aui-drag:active{cursor:grabbing}' +
    '.aui-del{border:0;background:none;padding:4px 6px;color:#999;font-size:16px;line-height:1;cursor:pointer}' +
    '.aui-del:hover{color:#b00020}' +
    '.aui-opt-add{border:1px dashed #bbb;border-radius:6px;background:none;padding:8px 14px;font:inherit;cursor:pointer;color:#555;width:100%}' +
    '.aui-opt-add:hover{border-color:#e07b00;color:#e07b00}' +
    '.aui-foot{display:flex;gap:10px;justify-content:flex-end;padding:14px 18px;border-top:1px solid #e5e5e5}' +
    '.aui-foot button{padding:8px 18px;border-radius:4px;font:inherit;cursor:pointer;border:1px solid #ccc;background:#fff;color:#222}' +
    '.aui-foot .aui-primary{border:0;background:#10312b;color:#fff}' +
    '.aui-foot .aui-danger{border:0;background:#b00020;color:#fff}';
  (document.head || document.documentElement).appendChild(style);

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  /** Build and show a dialog; returns {overlay, body, foot, close(viaCancel)}. */
  function shell(title, onCancel, wide) {
    var overlay = document.createElement('div');
    overlay.className = 'aui-overlay';
    overlay.innerHTML =
      '<div class="aui-box" role="dialog" aria-modal="true">' +
        '<div class="aui-head"><b>' + esc(title) + '</b>' +
          '<button type="button" class="aui-x" aria-label="Close">&times;</button></div>' +
        '<div class="aui-body"></div>' +
        '<div class="aui-foot"></div>' +
      '</div>';
    if (wide) overlay.querySelector('.aui-box').style.width = 'min(680px,92vw)';
    var closed = false;
    function close(viaCancel) {
      if (closed) return;
      closed = true;
      overlay.remove();
      document.removeEventListener('keydown', onKey);
      if (viaCancel) onCancel();
    }
    function onKey(e) { if (e.key === 'Escape') { e.preventDefault(); close(true); } }
    overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) close(true); });
    overlay.querySelector('.aui-x').addEventListener('click', function () { close(true); });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(overlay);
    return {
      overlay: overlay,
      body: overlay.querySelector('.aui-body'),
      foot: overlay.querySelector('.aui-foot'),
      close: close
    };
  }

  function button(label, cls, onClick) {
    var b = document.createElement('button');
    b.type = 'button';
    if (cls) b.className = cls;
    b.textContent = label;
    b.addEventListener('click', onClick);
    return b;
  }

  window.AdminkaUI = {

    /** Low-level dialog shell for custom bodies (e.g. the crop tool). */
    dialog: function (opts) {
      return shell(opts.title || '', opts.onCancel || function () {}, !!opts.wide);
    },

    form: function (opts) {
      return new Promise(function (resolve) {
        var d = shell(opts.title || 'Edit', function () { resolve(null); });
        var inputs = {};

        (opts.fields || []).forEach(function (f) {
          var row = document.createElement('div');
          row.className = 'aui-row';

          if (f.type === 'checkbox') {
            row.innerHTML = '<label class="aui-check"><input type="checkbox"> ' + esc(f.label) + '</label>';
            var cb = row.querySelector('input');
            cb.checked = !!f.checked;
            inputs[f.name] = function () { return cb.checked; };
          } else if (f.type === 'attr') {
            row.innerHTML = '<div class="aui-attr"><input type="checkbox" title="Unset to remove the attribute">' +
              '<code>' + esc(f.label) + '</code><input type="text"></div>';
            var p = row.querySelector('input[type=checkbox]');
            var v = row.querySelector('input[type=text]');
            p.checked = !!f.present;
            v.value = f.value || '';
            v.disabled = !p.checked;
            p.addEventListener('change', function () { v.disabled = !p.checked; if (p.checked) v.focus(); });
            v.addEventListener('input', function () { if (!p.checked) p.checked = true; });
            inputs[f.name] = function () { return p.checked ? v.value : null; };
          } else if (f.type === 'media') {
            row.innerHTML = '<label class="aui-l">' + esc(f.label) + '</label>' +
              '<div class="aui-media"><input type="text"><button type="button">Browse…</button></div>';
            var mi = row.querySelector('input');
            mi.value = f.value != null ? f.value : '';
            if (f.placeholder) mi.placeholder = f.placeholder;
            row.querySelector('button').addEventListener('click', function () {
              if (f.browse) f.browse(function (url) { mi.value = url; });
            });
            inputs[f.name] = function () { return mi.value; };
          } else if (f.type === 'options') {
            // Simple label list: rename, drag (☰) to reorder, × to remove,
            // + to add. Values and selected/disabled flags are not editable
            // here — the caller keeps them tied to each row's `from` index.
            row.innerHTML = '<label class="aui-l">' + esc(f.label) + '</label><div class="aui-opts"></div>' +
              '<button type="button" class="aui-opt-add">+ Add option</button>';
            var wrap = row.querySelector('.aui-opts');

            var addOption = function (o, focus) {
              var line = document.createElement('div');
              line.className = 'aui-optrow';
              line.dataset.from = (o.from != null ? o.from : -1);
              line.innerHTML = '<input type="text">' +
                '<button type="button" class="aui-drag" title="Drag to reorder">&#9776;</button>' +
                '<button type="button" class="aui-del" title="Remove option">&times;</button>';
              var text   = line.querySelector('input');
              var handle = line.querySelector('.aui-drag');
              text.value = o.text || '';
              line.querySelector('.aui-del').addEventListener('click', function () { line.remove(); });

              handle.addEventListener('pointerdown', function (e) {
                e.preventDefault();
                line.classList.add('aui-dragging');
                // Listen on document, not the handle: reordering moves `line`
                // (and the handle inside it) in the DOM, which drops any
                // pointer capture and would otherwise stall the drag.
                var move = function (ev) {
                  ev.preventDefault();
                  var others = Array.prototype.filter.call(wrap.children, function (r) { return r !== line; });
                  var next = null;
                  for (var i = 0; i < others.length; i++) {
                    var b = others[i].getBoundingClientRect();
                    if (ev.clientY < b.top + b.height / 2) { next = others[i]; break; }
                  }
                  if (next) wrap.insertBefore(line, next);
                  else wrap.appendChild(line);
                };
                var up = function () {
                  line.classList.remove('aui-dragging');
                  document.removeEventListener('pointermove', move);
                  document.removeEventListener('pointerup', up);
                };
                document.addEventListener('pointermove', move);
                document.addEventListener('pointerup', up);
              });

              wrap.appendChild(line);
              if (focus) text.focus();
            };
            (f.value || []).forEach(function (o) { addOption(o, false); });
            row.querySelector('.aui-opt-add').addEventListener('click', function () { addOption({}, true); });
            inputs[f.name] = function () {
              return Array.prototype.map.call(wrap.children, function (line) {
                return { text: line.querySelector('input').value, from: parseInt(line.dataset.from, 10) };
              });
            };
          } else if (f.type === 'select') {
            row.innerHTML = '<label class="aui-l">' + esc(f.label) + '</label><select></select>';
            var sel = row.querySelector('select');
            (f.options || []).forEach(function (o) {
              var opt = document.createElement('option');
              opt.value = o.v; opt.textContent = o.t;
              sel.appendChild(opt);
            });
            sel.value = f.value != null ? f.value : (f.options && f.options.length ? f.options[0].v : '');
            inputs[f.name] = function () { return sel.value; };
          } else if (f.type === 'textarea') {
            row.innerHTML = '<label class="aui-l">' + esc(f.label) + '</label><textarea></textarea>';
            var ta = row.querySelector('textarea');
            ta.value = f.value || '';
            if (f.placeholder) ta.placeholder = f.placeholder;
            inputs[f.name] = function () { return ta.value; };
          } else {
            row.innerHTML = '<label class="aui-l">' + esc(f.label) + '</label><input type="text">';
            var inp = row.querySelector('input');
            inp.value = f.value != null ? f.value : '';
            if (f.placeholder) inp.placeholder = f.placeholder;
            inputs[f.name] = function () { return inp.value; };
          }
          if (f.hint) {
            var h = document.createElement('div');
            h.className = 'aui-hint';
            h.textContent = f.hint;
            row.appendChild(h);
          }
          d.body.appendChild(row);
        });

        function submit() {
          var out = {};
          Object.keys(inputs).forEach(function (k) { out[k] = inputs[k](); });
          d.close(false);
          resolve(out);
        }
        d.body.addEventListener('keydown', function (e) {
          if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA') return;
          e.preventDefault();
          if (!e.target.closest('.aui-optrow')) submit();   // Enter inside an option row just stays there
        });
        d.foot.appendChild(button('Cancel', '', function () { d.close(true); }));
        d.foot.appendChild(button(opts.submit || 'Apply', opts.danger ? 'aui-danger' : 'aui-primary', submit));

        var first = d.body.querySelector('input[type=text]:not(:disabled),textarea,select');
        if (first) { first.focus(); if (first.select) first.select(); }
      });
    },

    confirm: function (opts) {
      return new Promise(function (resolve) {
        var d = shell(opts.title || 'Are you sure?', function () { resolve(false); });
        d.body.innerHTML = '<p class="aui-msg">' + esc(opts.message || '') + '</p>';
        d.foot.appendChild(button('Cancel', '', function () { d.close(true); }));
        d.foot.appendChild(button(opts.confirm || 'OK', opts.danger === false ? 'aui-primary' : 'aui-danger',
          function () { d.close(false); resolve(true); }));
      });
    },

    alert: function (opts) {
      return new Promise(function (resolve) {
        var d = shell(opts.title || 'Notice', function () { resolve(); });
        d.body.innerHTML = '<p class="aui-msg">' + esc(opts.message || '') + '</p>';
        d.foot.appendChild(button('OK', 'aui-primary', function () { d.close(false); resolve(); }));
      });
    }
  };
})();
