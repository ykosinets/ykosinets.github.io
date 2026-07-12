/**
 * Adminka — edit-mode client. Loaded (deferred, after ui.js) into the page
 * being edited; window.ADMINKA carries {page, csrf, media:{image:[ext],video:[ext]}}.
 *
 * The page is treated as a canvas: a capture-phase interceptor cancels every
 * click's default action AND stops it from reaching the page's own scripts
 * (smooth-scroll handlers, menus, carousels), then routes the click to the
 * matching adminka editor. Only adminka's own UI is exempt.
 *
 * Save payload: {page, csrf, edits:{id: value}, attr_edits:{id:{name: string|null}},
 *                form_edits:{id:[{i, ...fields}]}, list_ops:[{id, op, index}]}
 */
(function () {
  'use strict';
  var CFG = window.ADMINKA || {};
  var PAGE = CFG.page, CSRF = CFG.csrf;
  var UI = window.AdminkaUI;

  /* ------------------------------------------------------------- styles */
  var style = document.createElement('style');
  style.textContent =
    '[data-editable]{outline:2px dashed rgba(30,120,220,.55);outline-offset:2px;min-height:1em}' +
    '[data-editable]:hover,[data-editable]:focus{outline-color:#e07b00;outline-style:solid}' +
    '#adminka-bar{position:fixed;left:0;right:0;bottom:0;z-index:99999;display:flex;gap:12px;align-items:center;' +
      'padding:10px 16px;background:#10312b;color:#f3efe6;font:14px/1 system-ui,sans-serif;box-shadow:0 -2px 8px rgba(0,0,0,.25)}' +
    '#adminka-bar button{padding:8px 18px;border:0;border-radius:4px;font:inherit;cursor:pointer;background:#e07b00;color:#fff}' +
    '#adminka-bar button:disabled{opacity:.5;cursor:default}' +
    '#adminka-status{opacity:.85}' +
    '#adminka-bar a{color:#f3efe6;margin-left:auto}' +
    'body{padding-bottom:60px}' +

    '#adminka-picker{position:fixed;inset:0;z-index:100001;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center}' +
    '#adminka-picker[hidden]{display:none}' +
    '.ap-box{background:#fff;color:#222;border-radius:8px;width:min(720px,92vw);max-height:80vh;display:flex;flex-direction:column;font:14px/1.4 system-ui,sans-serif}' +
    '.ap-head{display:flex;gap:8px;align-items:center;padding:12px 16px;border-bottom:1px solid #ddd}' +
    '.ap-head b{margin-right:auto;font-size:15px}' +
    '.ap-head button{padding:6px 12px;border:1px solid #ccc;border-radius:4px;background:#f7f6f2;cursor:pointer;font:inherit}' +
    '#ap-close{border:0;background:none;font-size:20px;line-height:1;padding:4px 8px}' +
    '.ap-grid{overflow:auto;padding:16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}' +
    '.ap-tile{border:1px solid #ddd;border-radius:6px;padding:0;background:#fff;cursor:pointer;overflow:hidden;text-align:center}' +
    '.ap-tile:hover{border-color:#e07b00;box-shadow:0 0 0 2px rgba(224,123,0,.3)}' +
    '.ap-tile img,.ap-tile video{width:100%;height:100px;object-fit:cover;display:block;background:#eee;pointer-events:none}' +
    '.ap-tile span{display:block;padding:6px 8px;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
    '.ap-empty{grid-column:1/-1;color:#777;text-align:center;padding:30px 0}' +

    '.adminka-item{position:relative}' +
    '.adminka-remove{position:absolute;top:6px;right:6px;z-index:99998;width:24px;height:24px;padding:0;' +
      'border:0;border-radius:50%;background:#b00020;color:#fff;font:16px/24px system-ui,sans-serif;cursor:pointer;opacity:.75}' +
    '.adminka-remove:hover{opacity:1}' +
    '.adminka-add{min-height:60px;padding:14px 22px;border:2px dashed rgba(30,120,220,.55);border-radius:6px;' +
      'background:none;color:#1e78dc;font:15px system-ui,sans-serif;cursor:pointer}' +
    '.adminka-add:hover{border-color:#e07b00;color:#e07b00}' +

    '#adminka-gear{position:absolute;z-index:99998;width:26px;height:26px;padding:0;border:0;border-radius:50%;' +
      'background:#10312b;color:#f3efe6;font:14px/26px system-ui,sans-serif;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,.35)}' +
    '#adminka-gear:hover{background:#e07b00}' +
    '#adminka-gear[hidden]{display:none}' +
    '.adminka-media{position:absolute;z-index:99998;width:26px;height:26px;padding:0;border:0;border-radius:50%;' +
      'background:#10312b;color:#f3efe6;font:13px/26px system-ui,sans-serif;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,.35);opacity:.85}' +
    '.adminka-media:hover{background:#e07b00;opacity:1}' +
    '[data-editable-type=form] input,[data-editable-type=form] textarea,[data-editable-type=form] select,' +
      '[data-editable-type=form] button,[data-editable-type=form] label{cursor:pointer}' +

    /* Sliders/carousels: page JS is frozen in edit mode, so a marked track
       becomes a scroll-snap strip with every slide reachable. */
    '[data-editable-scroll]{display:flex!important;flex-wrap:nowrap!important;overflow-x:auto!important;' +
      'scroll-snap-type:x mandatory;gap:16px;padding-bottom:10px;scroll-behavior:smooth}' +
    '[data-editable-scroll]>*{flex:0 0 min(85%,640px)!important;width:auto!important;max-width:none!important;' +
      'display:block!important;position:static!important;transform:none!important;opacity:1!important;' +
      'visibility:visible!important;height:auto!important;scroll-snap-align:center}' +

    /* Popups / accordion panels: content the live site keeps hidden is shown
       inline for editing when marked with data-editable-reveal. */
    '[data-editable-reveal]{display:block!important;position:static!important;inset:auto!important;' +
      'transform:none!important;opacity:1!important;visibility:visible!important;pointer-events:auto!important;' +
      'max-height:none!important;height:auto!important;overflow:visible!important;margin:10px 0;' +
      'outline:2px dashed rgba(176,0,32,.45);outline-offset:4px}' +
    '[data-editable-reveal]::before{content:"Hidden on the live site — shown for editing";display:block;' +
      'font:11px/1.4 system-ui,sans-serif;color:#b00020;opacity:.85;margin-bottom:6px}' +

    '.ac-wrap{position:relative;margin:0 auto;overflow:hidden;max-width:100%;width:fit-content}' +
    '.ac-wrap img{display:block;max-width:100%;max-height:56vh;user-select:none;-webkit-user-drag:none}' +
    '.ac-rect{position:absolute;border:2px solid #e07b00;box-shadow:0 0 0 9999px rgba(0,0,0,.45);cursor:move;touch-action:none;box-sizing:border-box}' +
    '.ac-h{position:absolute;width:14px;height:14px;background:#e07b00;border:2px solid #fff;border-radius:50%;box-sizing:border-box}' +
    '.ac-h[data-h=nw]{top:-8px;left:-8px;cursor:nwse-resize}' +
    '.ac-h[data-h=ne]{top:-8px;right:-8px;cursor:nesw-resize}' +
    '.ac-h[data-h=sw]{bottom:-8px;left:-8px;cursor:nesw-resize}' +
    '.ac-h[data-h=se]{bottom:-8px;right:-8px;cursor:nwse-resize}';
  document.head.appendChild(style);

  /* ----------------------------------------------------------- skeleton */
  document.body.insertAdjacentHTML('beforeend',
    '<div id="adminka-bar">' +
      '<button id="adminka-save" disabled>Save changes</button>' +
      '<span id="adminka-status">Click any outlined element to edit. Click images and videos to swap them.</span>' +
      '<a href="admin.php">All pages</a>' +
      '<a href="admin.php?action=logout">Log out</a>' +
    '</div>' +
    '<div id="adminka-picker" hidden>' +
      '<div class="ap-box">' +
        '<div class="ap-head">' +
          '<b id="ap-title">Choose image</b>' +
          '<button type="button" id="ap-alt" hidden>Alt text&hellip;</button>' +
          '<button type="button" id="ap-upload">Upload&hellip;</button>' +
          '<button type="button" id="ap-url">Use URL&hellip;</button>' +
          '<button type="button" id="ap-close" aria-label="Close">&times;</button>' +
          '<input type="file" id="ap-file" hidden>' +
        '</div>' +
        '<div class="ap-grid" id="ap-grid"></div>' +
      '</div>' +
    '</div>');

  var saveBtn = document.getElementById('adminka-save');
  var status  = document.getElementById('adminka-status');

  /* -------------------------------------------------------------- state */
  var dirty = {}, attrDirty = {}, formDirty = {};

  function isDirty() {
    return Object.keys(dirty).length || Object.keys(attrDirty).length || Object.keys(formDirty).length;
  }
  function refresh() { saveBtn.disabled = !isDirty(); if (isDirty()) status.textContent = 'Unsaved changes'; }
  function markDirty(id) { dirty[id] = true; refresh(); }
  function markAttrs(id, changes) {
    attrDirty[id] = Object.assign(attrDirty[id] || {}, changes);
    refresh();
  }
  function markForm(id, i, fields) {
    formDirty[id] = formDirty[id] || {};
    formDirty[id][i] = Object.assign(formDirty[id][i] || { i: i }, fields);
    refresh();
  }

  /* ----------------------------------------------- canvas-mode routing */
  var ADMIN_UI = '#adminka-bar,#adminka-picker,#adminka-gear,.aui-overlay,.adminka-remove,.adminka-add,.adminka-media';

  function intercept(e) {
    var t = e.target;
    if (!t || !t.closest || t.closest(ADMIN_UI)) return;
    // Block both the browser default (navigation, submit, anchor jump) and
    // the page's own click handlers (smooth scroll, menus, sliders).
    e.preventDefault();
    e.stopPropagation();
    if (e.type === 'click') routeClick(t);
  }
  document.addEventListener('click', intercept, true);
  document.addEventListener('auxclick', intercept, true);
  document.addEventListener('submit', function (e) {
    if (e.target.closest && !e.target.closest(ADMIN_UI)) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  function routeClick(t) {
    var formScope = t.closest('[data-editable-type="form"]');
    if (formScope) {
      var c = t.closest('input,textarea,select,button');
      if (!c) {
        var lab = t.closest('label');
        if (lab) c = lab.control || (lab.htmlFor ? document.getElementById(lab.htmlFor) : null);
      }
      if (c && formScope.contains(c)) {
        var controls = formScope.querySelectorAll('input,textarea,select,button');
        var i = Array.prototype.indexOf.call(controls, c);
        if (i >= 0) {
          openControlEditor(formScope, formScope.getAttribute('data-editable'), c, i);
          return;
        }
      }
    }
    var el = t.closest('[data-editable],[data-editable-type]');
    if (!el) return;
    var type = (el.getAttribute('data-editable-type') || 'text').toLowerCase();
    if (type === 'link')  openLinkEditor(el);
    if (type === 'image') openImagePicker(el);
    if (type === 'video') openVideoEditor(el);
  }

  /* ------------------------------------------------------- media picker */
  var picker  = document.getElementById('adminka-picker');
  var grid    = document.getElementById('ap-grid');
  var apTitle = document.getElementById('ap-title');
  var apAlt   = document.getElementById('ap-alt');
  var apFile  = document.getElementById('ap-file');
  var pickCb = null, pickKind = 'image', altTarget = null;

  function openPicker(kind, el, cb) {
    pickCb = cb; pickKind = kind; altTarget = (kind === 'image' && el) ? el : null;
    apTitle.textContent = kind === 'image' ? 'Choose image' : 'Choose video';
    apAlt.hidden = !altTarget;
    var exts = (CFG.media && CFG.media[kind]) || [];
    apFile.accept = exts.map(function (e) { return '.' + e; }).join(',');
    document.body.appendChild(picker);         // repaint above any open dialog
    picker.hidden = false;
    grid.innerHTML = '<p class="ap-empty">Loading…</p>';
    fetch('admin.php?action=media_list&kind=' + kind)
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (!r.ok) { grid.innerHTML = '<p class="ap-empty"></p>'; grid.firstChild.textContent = r.error; return; }
        renderGrid(r.items);
      })
      .catch(function () { grid.innerHTML = '<p class="ap-empty">Could not load the media list.</p>'; });
  }
  function closePicker() { picker.hidden = true; pickCb = null; altTarget = null; }
  function pick(url) { var cb = pickCb; closePicker(); if (cb) cb(url); }

  function renderGrid(items) {
    grid.innerHTML = '';
    if (!items.length) {
      grid.innerHTML = '<p class="ap-empty">Nothing here yet — use Upload to add the first file.</p>';
      return;
    }
    items.forEach(function (it) {
      var tile = document.createElement('button');
      tile.type = 'button'; tile.className = 'ap-tile'; tile.title = it.name;
      var media = document.createElement(pickKind === 'image' ? 'img' : 'video');
      media.src = it.url;
      if (pickKind === 'video') { media.muted = true; media.preload = 'metadata'; }
      var cap = document.createElement('span');
      cap.textContent = it.name;
      tile.appendChild(media); tile.appendChild(cap);
      tile.addEventListener('click', function () { pick(it.url); });
      grid.appendChild(tile);
    });
  }

  document.getElementById('ap-close').addEventListener('click', closePicker);
  picker.addEventListener('mousedown', function (e) { if (e.target === picker) closePicker(); });
  document.getElementById('ap-url').addEventListener('click', function () {
    UI.form({ title: 'Use a URL', submit: 'Use', fields: [
      { name: 'url', label: 'File URL', placeholder: 'https://…' }
    ]}).then(function (v) { if (v && v.url.trim()) pick(v.url.trim()); });
  });
  apAlt.addEventListener('click', function () {
    if (!altTarget) return;
    var el = altTarget;
    UI.form({ title: 'Alt text', submit: 'Apply', fields: [
      { name: 'alt', label: 'Describes the image', value: el.getAttribute('alt') || '' }
    ]}).then(function (v) {
      if (v === null) return;
      el.setAttribute('alt', v.alt);
      markDirty(el.getAttribute('data-editable'));
      closePicker();
    });
  });
  document.getElementById('ap-upload').addEventListener('click', function () { apFile.click(); });

  apFile.addEventListener('change', function () {
    if (!apFile.files.length) return;
    var f = apFile.files[0];
    apFile.value = '';
    if (pickKind === 'image' && /^image\/(jpeg|png|webp)$/.test(f.type)) {
      openCropDialog(f).then(function (out) { if (out) uploadFile(out); });
    } else {
      uploadFile(f);       // gif/avif/video: canvas re-encoding would hurt them
    }
  });

  function uploadFile(file) {
    var fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('file', file);
    grid.innerHTML = '<p class="ap-empty">Uploading…</p>';
    fetch('admin.php?action=media_upload&kind=' + pickKind, { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (r.ok) { pick(r.item.url); }
        else { grid.innerHTML = '<p class="ap-empty"></p>'; grid.firstChild.textContent = r.error; }
      })
      .catch(function () { grid.innerHTML = '<p class="ap-empty">Upload failed — try again.</p>'; });
  }

  /* --------------------------------------------------------- crop tool */
  function openCropDialog(file) {
    return new Promise(function (resolve) {
      var url = URL.createObjectURL(file);
      var done = false;
      function finish(result) { if (done) return; done = true; URL.revokeObjectURL(url); resolve(result); }

      var d = UI.dialog({ title: 'Crop image', wide: true, onCancel: function () { finish(null); } });
      d.body.innerHTML =
        '<div class="ac-wrap"><img alt="">' +
          '<div class="ac-rect">' +
            '<span class="ac-h" data-h="nw"></span><span class="ac-h" data-h="ne"></span>' +
            '<span class="ac-h" data-h="sw"></span><span class="ac-h" data-h="se"></span>' +
          '</div>' +
        '</div>' +
        '<div class="aui-hint">Drag the corners to crop, drag inside the frame to move it.</div>';
      var wrap = d.body.querySelector('.ac-wrap');
      var img  = d.body.querySelector('img');
      var rect = d.body.querySelector('.ac-rect');
      var r = { x: 0, y: 0, w: 0, h: 0 };
      var disp = { w: 0, h: 0 };            // rendered image size, computed from natural size

      function draw() {
        rect.style.left = r.x + 'px';  rect.style.top = r.y + 'px';
        rect.style.width = r.w + 'px'; rect.style.height = r.h + 'px';
      }
      // Size the image ourselves from its natural dimensions rather than
      // relying on the browser having laid it out (load can fire at 0×0).
      img.onload = function () {
        var avail = Math.max(200, (d.body.clientWidth || 600) - 4);
        var maxH  = Math.max(200, Math.round(window.innerHeight * 0.56));
        var ratio = Math.min(avail / img.naturalWidth, maxH / img.naturalHeight, 1);
        disp = { w: Math.round(img.naturalWidth * ratio), h: Math.round(img.naturalHeight * ratio) };
        img.style.width = disp.w + 'px'; img.style.height = disp.h + 'px';
        wrap.style.width = disp.w + 'px';
        r = { x: 0, y: 0, w: disp.w, h: disp.h };
        draw();
      };
      img.src = url;

      var drag = null;
      rect.addEventListener('pointerdown', function (e) {
        e.preventDefault();
        var h = e.target.getAttribute ? e.target.getAttribute('data-h') : null;
        drag = { mode: h || 'move', sx: e.clientX, sy: e.clientY, o: { x: r.x, y: r.y, w: r.w, h: r.h } };
        rect.setPointerCapture(e.pointerId);
      });
      rect.addEventListener('pointermove', function (e) {
        if (!drag) return;
        var dx = e.clientX - drag.sx, dy = e.clientY - drag.sy, o = drag.o;
        var W = disp.w, H = disp.h, MIN = 16;
        if (drag.mode === 'move') {
          r.x = Math.min(Math.max(0, o.x + dx), W - o.w);
          r.y = Math.min(Math.max(0, o.y + dy), H - o.h);
        } else {
          var x1 = o.x, y1 = o.y, x2 = o.x + o.w, y2 = o.y + o.h;
          if (drag.mode.indexOf('w') > -1) x1 = Math.min(Math.max(0, x1 + dx), x2 - MIN);
          if (drag.mode.indexOf('e') > -1) x2 = Math.max(Math.min(W, x2 + dx), x1 + MIN);
          if (drag.mode.indexOf('n') > -1) y1 = Math.min(Math.max(0, y1 + dy), y2 - MIN);
          if (drag.mode.indexOf('s') > -1) y2 = Math.max(Math.min(H, y2 + dy), y1 + MIN);
          r = { x: x1, y: y1, w: x2 - x1, h: y2 - y1 };
        }
        draw();
      });
      rect.addEventListener('pointerup', function () { drag = null; });

      function footBtn(label, cls, fn) {
        var b = document.createElement('button');
        b.type = 'button';
        if (cls) b.className = cls;
        b.textContent = label;
        b.addEventListener('click', fn);
        d.foot.appendChild(b);
      }
      footBtn('Upload original', '', function () { d.close(false); finish(file); });
      footBtn('Crop & upload', 'aui-primary', function () {
        var full = r.x === 0 && r.y === 0 && r.w === disp.w && r.h === disp.h;
        // No usable crop rect (never sized, or full-frame): send as-is.
        if (full || !img.naturalWidth || disp.w < 1 || r.w < 1 || r.h < 1) { d.close(false); finish(file); return; }
        var scale  = img.naturalWidth / disp.w;
        var canvas = document.createElement('canvas');
        canvas.width  = Math.max(1, Math.round(r.w * scale));
        canvas.height = Math.max(1, Math.round(r.h * scale));
        canvas.getContext('2d').drawImage(img,
          Math.round(r.x * scale), Math.round(r.y * scale),
          canvas.width, canvas.height, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
          d.close(false);
          finish(blob ? new File([blob], file.name, { type: file.type }) : file);
        }, file.type, 0.92);
      });
    });
  }

  /* ---------------------------------------------------- element editors */
  function openLinkEditor(el) {
    var id = el.getAttribute('data-editable');
    UI.form({ title: 'Edit link', submit: 'Apply', fields: [
      { name: 'text', label: 'Text', value: el.textContent.trim() },
      { name: 'href', label: 'URL', value: el.getAttribute('href') || '', placeholder: 'https://… or /page.html' }
    ]}).then(function (v) {
      if (v === null) return;
      el.textContent = v.text;
      el.setAttribute('href', v.href);
      markDirty(id);
    });
  }

  function openImagePicker(el) {
    openPicker('image', el, function (url) {
      el.setAttribute('src', url);
      markDirty(el.getAttribute('data-editable'));
    });
  }

  var BG_ATTRS = ['muted', 'autoplay', 'loop', 'playsinline', 'webkit-playsinline',
                  'disablepictureinpicture', 'disableremoteplayback'];

  function openVideoEditor(el) {
    var id = el.getAttribute('data-editable');
    if (el.pause) el.pause();
    UI.form({ title: 'Video', submit: 'Apply', fields: [
      { name: 'src', label: 'Video file', type: 'media', value: el.getAttribute('src') || '',
        browse: function (set) { openPicker('video', null, set); } },
      { name: 'poster', label: 'Poster — image shown before playback', type: 'media', value: el.getAttribute('poster') || '',
        browse: function (set) { openPicker('image', null, set); } },
      { name: 'bg', label: 'Autoplay silently in the background (muted, looping, inline)', type: 'checkbox',
        checked: el.hasAttribute('autoplay') && el.hasAttribute('muted') },
      { name: 'controls', label: 'Show player controls', type: 'checkbox', checked: el.hasAttribute('controls') }
    ]}).then(function (v) {
      if (v === null) return;
      el.querySelectorAll('source').forEach(function (s) { s.remove(); });
      if (v.src) el.setAttribute('src', v.src);
      if (v.poster) el.setAttribute('poster', v.poster); else el.removeAttribute('poster');
      if (v.bg) {
        BG_ATTRS.forEach(function (a) { el.setAttribute(a, ''); });
        el.setAttribute('preload', 'metadata');
        el.muted = true;
      } else {
        BG_ATTRS.forEach(function (a) { el.removeAttribute(a); });
        el.removeAttribute('preload');
      }
      if (v.controls) el.setAttribute('controls', ''); else el.removeAttribute('controls');
      if (el.load) el.load();
      markDirty(id);
    });
  }

  /* ---------------------------------------------------- attribute editor */
  var gear = document.createElement('button');
  gear.id = 'adminka-gear'; gear.type = 'button'; gear.textContent = '⚙';
  gear.title = 'Edit attributes'; gear.hidden = true;
  document.body.appendChild(gear);
  var gearTarget = null;

  document.addEventListener('mouseover', function (e) {
    var t = e.target.closest ? e.target.closest('[data-editable-attrs]') : null;
    if (t) {
      gearTarget = t;
      var r = t.getBoundingClientRect();
      gear.style.top  = (window.scrollY + r.top - 10) + 'px';
      gear.style.left = (window.scrollX + r.right - 16) + 'px';
      gear.hidden = false;
    } else if (e.target !== gear) {
      gear.hidden = true;
    }
  });

  gear.addEventListener('click', function () {
    var el = gearTarget;
    if (!el) return;
    var id = el.getAttribute('data-editable');
    if (!id) { UI.alert({ title: 'Attributes', message: 'This element has data-editable-attrs but no data-editable id.' }); return; }
    var names = (el.getAttribute('data-editable-attrs') || '').split(/[\s,]+/).filter(Boolean);
    UI.form({
      title: 'Attributes',
      submit: 'Apply',
      fields: names.map(function (n) {
        return { name: n, label: n, type: 'attr', present: el.hasAttribute(n), value: el.getAttribute(n) || '' };
      })
    }).then(function (v) {
      if (v === null) return;
      var changes = {};
      names.forEach(function (n) {
        var was = el.hasAttribute(n) ? el.getAttribute(n) : null;
        if (v[n] === was) return;
        changes[n] = v[n];
        if (v[n] === null) el.removeAttribute(n);
        else el.setAttribute(n, v[n]);
      });
      if (Object.keys(changes).length) markAttrs(id, changes);
    });
  });

  /* --------------------------------------------------------- form editor */
  function findLabel(scope, c) {
    if (c.id) {
      var labels = scope.querySelectorAll('label');
      for (var i = 0; i < labels.length; i++) {
        if (labels[i].getAttribute('for') === c.id) return labels[i];
      }
    }
    var p = c.closest ? c.closest('label') : null;
    return p && scope.contains(p) ? p : null;
  }
  function labelText(label) {
    if (!label) return '';
    return Array.prototype.filter.call(label.childNodes, function (n) { return n.nodeType === 3; })
      .map(function (n) { return n.textContent; }).join(' ').trim();
  }
  function setLabelTextLive(label, text) {
    if (!label) return;
    var first = null;
    Array.prototype.slice.call(label.childNodes).forEach(function (n) {
      if (n.nodeType === 3 && n.textContent.trim() !== '') {
        if (!first) first = n; else n.remove();
      }
    });
    if (first) first.textContent = text;
    else label.insertBefore(document.createTextNode(text), label.firstChild);
  }

  // Input types where the placeholder attribute actually does something.
  var PLACEHOLDER_TYPES = ['text', 'search', 'url', 'tel', 'email', 'password', 'number'];

  // A select's "placeholder" is its first option when it looks like
  // <option value="" disabled selected style="display:none;">…</option>.
  function selectPlaceholderOption(c) {
    var o = c.options[0];
    return (o && o.getAttribute('value') === '' && o.disabled) ? o : null;
  }

  function openControlEditor(scope, formId, c, index) {
    var tag = c.tagName.toLowerCase();
    var label = findLabel(scope, c);
    var fields = [];

    if (tag === 'button') {
      fields.push({ name: 'text', label: 'Button text', value: c.textContent.trim() });
    } else {
      if (label) fields.push({ name: 'label', label: 'Label', value: labelText(label) });
      if (tag === 'textarea'
          || (tag === 'input' && PLACEHOLDER_TYPES.indexOf((c.getAttribute('type') || 'text').toLowerCase()) > -1)) {
        fields.push({ name: 'placeholder', label: 'Placeholder', value: c.getAttribute('placeholder') || '' });
      }
      if (tag === 'select') {
        var ph = selectPlaceholderOption(c);
        fields.push({ name: 'placeholder', label: 'Placeholder', value: ph ? ph.text : '',
                      hint: 'Shown until the visitor picks an option' });
        fields.push({ name: 'options', label: 'Options', type: 'options',
                      value: Array.prototype.filter.call(c.options, function (o) { return o !== ph; })
                        .map(function (o) { return { text: o.text, from: o.index }; }) });
      }
      fields.push({ name: 'required', label: 'Required', type: 'checkbox', checked: c.hasAttribute('required') });
    }

    UI.form({ title: 'Edit ' + (tag === 'button' ? 'button' : 'field'), submit: 'Apply', fields: fields })
      .then(function (v) {
        if (v === null) return;
        applyControlLive(scope, c, label, v);
        markForm(formId, index, v);
      });
  }

  function applyControlLive(scope, c, label, f) {
    var tag = c.tagName.toLowerCase();
    if ('label' in f) setLabelTextLive(label, f.label);
    if (tag === 'button') { if ('text' in f) c.textContent = f.text; return; }
    if ('required' in f) { f.required ? c.setAttribute('required', '') : c.removeAttribute('required'); }
    if (tag === 'input' || tag === 'textarea') {
      if ('placeholder' in f) { f.placeholder ? c.setAttribute('placeholder', f.placeholder) : c.removeAttribute('placeholder'); }
    }
    if (tag === 'select' && Array.isArray(f.options)) {
      // Reuse the original <option> nodes so value/selected/disabled survive
      // renames and reordering; rows with from:-1 are brand new. The hidden
      // placeholder option is managed by the placeholder field, not the rows.
      var orig = Array.prototype.slice.call(c.options);
      var ph   = selectPlaceholderOption(c);
      c.innerHTML = '';
      if ('placeholder' in f && f.placeholder.trim()) {
        if (!ph) {
          ph = document.createElement('option');
          ph.setAttribute('value', '');
          ph.setAttribute('disabled', '');
          ph.setAttribute('selected', '');
          ph.setAttribute('style', 'display:none;');
        }
        ph.textContent = f.placeholder.trim();
        c.appendChild(ph);
      }
      f.options.forEach(function (o) {
        if (!o.text.trim()) return;
        var opt = (o.from >= 0 && orig[o.from]) ? orig[o.from] : document.createElement('option');
        opt.textContent = o.text;
        c.appendChild(opt);
      });
    }
  }

  /* --------------------------------------------------- editable elements */
  document.querySelectorAll('[data-editable]').forEach(function (el) {
    var id   = el.getAttribute('data-editable');
    var type = (el.getAttribute('data-editable-type') || 'text').toLowerCase();

    if (type === 'text' || type === 'html') {
      el.addEventListener('input', function () { markDirty(id); });
    }
    if (type === 'image' || type === 'video' || type === 'link') {
      el.style.cursor = 'pointer';
      el.title = 'Click to edit';
    }
    if (type === 'form') {
      el.querySelectorAll('input,textarea,select,button').forEach(function (c) {
        c.addEventListener('mousedown', function (e) { e.preventDefault(); });   // keep focus off
      });
    }
  });

  /* ------------------------------------------------ media corner badges */
  // Background images/videos are often covered by content and can't be
  // clicked directly; every media editable gets a floating corner badge.
  var badges = [];
  document.querySelectorAll('[data-editable-type="image"],[data-editable-type="video"]').forEach(function (el) {
    var isVideo = el.getAttribute('data-editable-type').toLowerCase() === 'video';
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'adminka-media';
    b.textContent = isVideo ? '🎬' : '🖼';
    b.title = 'Edit this ' + (isVideo ? 'video' : 'image');
    b.addEventListener('click', function () { isVideo ? openVideoEditor(el) : openImagePicker(el); });
    document.body.appendChild(b);
    badges.push([b, el]);
  });
  function positionBadges() {
    badges.forEach(function (pair) {
      var r = pair[1].getBoundingClientRect();
      if (!r.width || !r.height) { pair[0].hidden = true; return; }
      pair[0].hidden = false;
      pair[0].style.top  = (window.scrollY + r.top + 6) + 'px';
      pair[0].style.left = (window.scrollX + r.left + 6) + 'px';
    });
  }
  positionBadges();
  window.addEventListener('resize', positionBadges);
  window.addEventListener('load', positionBadges);
  document.addEventListener('load', positionBadges, true);   // img/video loads shift layout

  /* --------------------------------------------------------------- lists */
  document.querySelectorAll('[data-editable-type="list"]').forEach(function (list) {
    var listId = list.getAttribute('data-editable');
    var items  = Array.prototype.slice.call(list.children);

    if (items.length > 1) {                       // the last item stays: it is the template
      items.forEach(function (item, i) {
        item.classList.add('adminka-item');
        var x = document.createElement('button');
        x.type = 'button'; x.className = 'adminka-remove'; x.textContent = '×';
        x.title = 'Remove this item';
        x.addEventListener('click', function (e) {
          e.preventDefault(); e.stopPropagation();
          UI.confirm({ title: 'Remove this item?', message: 'The page is saved right away — a backup is kept.',
                       confirm: 'Remove' })
            .then(function (ok) { if (ok) structuralSave(listId, { op: 'remove', index: i }); });
        });
        item.appendChild(x);
      });
    }

    var add = document.createElement('button');
    add.type = 'button'; add.className = 'adminka-add'; add.textContent = '+ Add item';
    add.title = 'Duplicate the last item';
    add.addEventListener('click', function (e) {
      e.preventDefault();
      structuralSave(listId, { op: 'add' });
    });
    list.appendChild(add);
  });

  /* --------------------------------------------------------------- save */
  function collectEdits() {
    var edits = {};
    document.querySelectorAll('[data-editable]').forEach(function (el) {
      var id = el.getAttribute('data-editable');
      if (!dirty[id]) return;
      var type = (el.getAttribute('data-editable-type') || 'text').toLowerCase();
      if (type === 'text' || type === 'html') {
        var c = el.cloneNode(true);   // never let editor controls leak into content
        c.querySelectorAll('.adminka-remove,.adminka-add').forEach(function (b) { b.remove(); });
        edits[id] = type === 'text' ? c.textContent : c.innerHTML;
      }
      if (type === 'image') edits[id] = { src: el.getAttribute('src'), alt: el.getAttribute('alt') || '' };
      if (type === 'video') edits[id] = {
        src:      el.getAttribute('src'),
        poster:   el.getAttribute('poster') || '',
        bg:       el.hasAttribute('autoplay') && el.hasAttribute('muted'),
        controls: el.hasAttribute('controls')
      };
      if (type === 'link') edits[id] = { href: el.getAttribute('href'), text: el.textContent };
    });
    return edits;
  }

  function payload() {
    var p = { page: PAGE, csrf: CSRF, edits: collectEdits() };
    if (Object.keys(attrDirty).length) p.attr_edits = attrDirty;
    var fe = {};
    Object.keys(formDirty).forEach(function (id) {
      fe[id] = Object.keys(formDirty[id]).map(function (i) { return formDirty[id][i]; });
    });
    if (Object.keys(fe).length) p.form_edits = fe;
    return p;
  }

  function postSave(body, onOk) {
    saveBtn.disabled = true; status.textContent = 'Saving…';
    fetch('admin.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); }).then(function (r) {
      if (r.ok) { dirty = {}; attrDirty = {}; formDirty = {}; onOk(); }
      else { saveBtn.disabled = false; status.textContent = 'Error: ' + r.error; }
    }).catch(function () { saveBtn.disabled = false; status.textContent = 'Network error — try again.'; });
  }

  // Add/remove saves immediately (pending edits included) and reloads,
  // so the server can assign fresh ids to the new item's editable fields.
  function structuralSave(listId, op) {
    var body = payload();
    body.list_ops = [{ id: listId, op: op.op, index: op.index }];
    postSave(body, function () { location.reload(); });
  }

  saveBtn.addEventListener('click', function () {
    postSave(payload(), function () { status.textContent = 'Saved ✓'; });
  });

  window.addEventListener('beforeunload', function (e) {
    if (isDirty()) { e.preventDefault(); e.returnValue = ''; }
  });
})();
