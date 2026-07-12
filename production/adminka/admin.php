<?php
/**
 * Adminka — flat-file inline editor for static HTML sites.
 *
 * Elements marked with data-editable="unique-id" become editable in place.
 * Optional data-editable-type: text (default) | html | image | video | link
 *
 * Sign-in: password, Google / Apple (config), passkeys (added after sign-in).
 * Media:   images and videos live in the folders from config['media'] and are
 *          picked/uploaded through the edit-mode picker.
 *
 * Requires PHP 8.1+. Uses the HTML5-accurate Dom\HTMLDocument parser on PHP 8.4+,
 * falls back to legacy DOMDocument on older versions.
 */

declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

$config = require __DIR__ . '/config.php';
require __DIR__ . '/admin-lib/util.php';
require __DIR__ . '/admin-lib/html.php';
require __DIR__ . '/admin-lib/media.php';
require __DIR__ . '/admin-lib/webauthn.php';
require __DIR__ . '/admin-lib/oauth.php';

$action = $_GET['action'] ?? '';

/* -------------------------------------------------- pre-auth entry points */

if ($action === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}
if ($action === 'oauth_start') oauth_start((string)($_GET['provider'] ?? ''), $config);
if ($action === 'oauth_cb')    oauth_callback((string)($_GET['provider'] ?? ''), $config);
if ($action === 'passkey_login_options') passkey_login_options($config);
if ($action === 'passkey_login' && $_SERVER['REQUEST_METHOD'] === 'POST') passkey_login($config);

/* ------------------------------------------------------------------- auth */

if (!isset($_SESSION['auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user'], $_POST['pass'])) {
        usleep(500_000); // slow down brute force
        if (hash_equals($config['admin_user'], (string)$_POST['user'])
            && password_verify((string)$_POST['pass'], $config['admin_hash'])) {
            session_regenerate_id(true);
            $_SESSION['auth'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: admin.php');
            exit;
        }
        $loginError = 'Wrong login or password.';
    }
    $providers   = oauth_enabled_providers($config);
    $hasPasskeys = (bool)passkeys_load($config)['creds'];
    ?><!DOCTYPE html>
    <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Adminka — sign in</title>
    <style>
      body{font:16px/1.5 system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f5f4f0}
      form{background:#fff;padding:32px;border:1px solid #ddd;border-radius:8px;width:300px}
      h1{font-size:1.2rem;margin:0 0 16px}
      input{width:100%;box-sizing:border-box;padding:10px;margin-bottom:12px;border:1px solid #ccc;border-radius:4px;font:inherit}
      button{width:100%;padding:10px;border:0;border-radius:4px;background:#10312b;color:#fff;font:inherit;cursor:pointer}
      .err{color:#b00020;font-size:.9rem;margin-bottom:12px}
      .or{display:flex;align-items:center;gap:10px;color:#999;font-size:.8rem;margin:16px 0}
      .or::before,.or::after{content:"";flex:1;border-top:1px solid #ddd}
      .alt{display:block;width:100%;box-sizing:border-box;text-align:center;padding:10px;margin-bottom:8px;
        border:1px solid #ccc;border-radius:4px;background:#fff;color:#222;text-decoration:none;font:inherit;cursor:pointer}
      .alt:hover{background:#f7f6f2}
    </style></head><body>
    <form method="post">
      <h1>Adminka</h1>
      <?php if (!empty($loginError)) echo '<div class="err">' . htmlspecialchars($loginError) . '</div>'; ?>
      <div class="err" id="pk-err" hidden></div>
      <input name="user" placeholder="Login" autocomplete="username" required>
      <input name="pass" type="password" placeholder="Password" autocomplete="current-password" required>
      <button>Sign in</button>
      <?php if ($providers || $hasPasskeys): ?>
      <div class="or">or</div>
      <?php foreach ($providers as $key => $label): ?>
      <a class="alt" href="admin.php?action=oauth_start&amp;provider=<?= $key ?>">Continue with <?= $label ?></a>
      <?php endforeach; ?>
      <?php if ($hasPasskeys): ?>
      <button type="button" class="alt" id="pk-btn">Sign in with a passkey</button>
      <?php endif; ?>
      <?php endif; ?>
    </form>
    <?php if ($hasPasskeys): ?>
    <script>
    (function () {
      var err = document.getElementById('pk-err');
      function b2a(buf){var b='',u=new Uint8Array(buf);for(var i=0;i<u.length;i++)b+=String.fromCharCode(u[i]);
        return btoa(b).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');}
      function a2b(s){s=s.replace(/-/g,'+').replace(/_/g,'/');var bin=atob(s),u=new Uint8Array(bin.length);
        for(var i=0;i<bin.length;i++)u[i]=bin.charCodeAt(i);return u.buffer;}
      function show(msg){err.textContent=msg;err.hidden=false;}

      document.getElementById('pk-btn').addEventListener('click', function () {
        err.hidden = true;
        fetch('admin.php?action=passkey_login_options')
          .then(function(r){return r.json();})
          .then(function(r){
            if(!r.ok) throw new Error(r.error);
            var pk = r.publicKey;
            pk.challenge = a2b(pk.challenge);
            pk.allowCredentials = pk.allowCredentials.map(function(c){c.id=a2b(c.id);return c;});
            return navigator.credentials.get({publicKey: pk});
          })
          .then(function(cred){
            return fetch('admin.php?action=passkey_login', {
              method:'POST', headers:{'Content-Type':'application/json'},
              body: JSON.stringify({ id: cred.id, response: {
                clientDataJSON:    b2a(cred.response.clientDataJSON),
                authenticatorData: b2a(cred.response.authenticatorData),
                signature:         b2a(cred.response.signature)
              }})
            }).then(function(r){return r.json();});
          })
          .then(function(r){
            if(!r.ok) throw new Error(r.error);
            location.href = 'admin.php';
          })
          .catch(function(e){ show(e.message || 'Passkey sign-in failed.'); });
      });
    })();
    </script>
    <?php endif; ?>
    </body></html><?php
    exit;
}

$csrf = $_SESSION['csrf'];

/* ---------------------------------------------------------- authed APIs */

if ($action === 'asset') {
    $assets = ['ui.js' => 'application/javascript', 'editor.js' => 'application/javascript'];
    $file   = (string)($_GET['file'] ?? '');
    if (!isset($assets[$file])) fail(404, 'No such asset.');
    header('Content-Type: ' . $assets[$file] . '; charset=utf-8');
    header('Cache-Control: no-cache');
    readfile(__DIR__ . '/admin-lib/' . $file);
    exit;
}

if ($action === 'media_list')   media_list((string)($_GET['kind'] ?? ''), $config);
if ($action === 'media_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    media_upload((string)($_GET['kind'] ?? ''), $config);
}
if ($action === 'passkey_register_options') passkey_register_options($config);
if ($action === 'passkey_register' && $_SERVER['REQUEST_METHOD'] === 'POST') passkey_register($config);
if ($action === 'passkey_delete'   && $_SERVER['REQUEST_METHOD'] === 'POST') passkey_delete($config);

/* ------------------------------------------------------------------- save */

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $body = json_body();
    require_csrf($body);

    $path      = resolve_page((string)($body['page'] ?? ''), $config);
    $edits     = $body['edits'] ?? [];
    $ops       = $body['list_ops'] ?? [];
    $attrEdits = $body['attr_edits'] ?? [];
    $formEdits = $body['form_edits'] ?? [];
    foreach ([$edits, $ops, $attrEdits, $formEdits] as $part) {
        if (!is_array($part)) fail(400, 'Bad request.');
    }
    if (!$edits && !$ops && !$attrEdits && !$formEdits) fail(400, 'Nothing to save.');

    $doc = html_load(file_get_contents($path));
    // Same deterministic generation as edit mode, so a save against a
    // not-yet-normalized file still addresses the ids the client saw.
    assign_missing_ids($doc);
    $applied = 0;

    foreach (find_editables($doc) as $el) {
        $id = $el->getAttribute('data-editable');

        // Attribute edits: names must be listed in the FILE's data-editable-attrs.
        if (isset($attrEdits[$id]) && is_array($attrEdits[$id])) {
            $allowed = preg_split('/[\s,]+/', (string)$el->getAttribute('data-editable-attrs'), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($attrEdits[$id] as $name => $value) {
                $name = (string)$name;
                if (!in_array($name, $allowed, true) || !attr_name_ok($name)) continue;
                if ($value === null) {
                    $el->removeAttribute($name);
                } else {
                    $value = (string)$value;
                    if (in_array(strtolower($name), ['src', 'href', 'action', 'formaction', 'poster'], true)
                        && !is_safe_url($value)) continue;
                    $el->setAttribute($name, $value);
                }
                $applied++;
            }
        }

        // Form field edits, addressed by control index within the container.
        if (isset($formEdits[$id]) && is_array($formEdits[$id])
            && strtolower((string)$el->getAttribute('data-editable-type')) === 'form') {
            $controls = find_form_controls($el);
            foreach ($formEdits[$id] as $f) {
                if (!is_array($f)) continue;
                $control = $controls[(int)($f['i'] ?? -1)] ?? null;
                if (!$control) continue;
                apply_control_edit($el, $control, $f);
                $applied++;
            }
        }

        if (!isset($edits[$id])) continue;
        $type  = strtolower($el->getAttribute('data-editable-type') ?: 'text'); // type trusted from FILE, not client
        $value = $edits[$id];

        switch ($type) {
            case 'text':
                $el->textContent = trim((string)$value);
                break;

            case 'html':
                while ($el->firstChild) $el->removeChild($el->firstChild);
                foreach (sanitize_fragment((string)$value, $doc, $config) as $node) {
                    $el->appendChild($node);
                }
                break;

            case 'image':
                $src = (string)($value['src'] ?? '');
                if (strtolower($el->nodeName) === 'img' && is_safe_url($src)) {
                    $el->setAttribute('src', $src);
                    if (isset($value['alt'])) $el->setAttribute('alt', (string)$value['alt']);
                }
                break;

            case 'video':
                if (strtolower($el->nodeName) !== 'video') break;
                $src = (string)($value['src'] ?? '');
                if (is_safe_url($src)) {
                    $el->setAttribute('src', $src);
                    foreach (iterator_to_array($el->childNodes) as $child) {
                        // src attribute replaces any <source> children
                        if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'source') {
                            $el->removeChild($child);
                        }
                    }
                }
                if (isset($value['poster'])) {
                    $poster = (string)$value['poster'];
                    if ($poster === '') $el->removeAttribute('poster');
                    elseif (is_safe_url($poster)) $el->setAttribute('poster', $poster);
                }
                if (isset($value['bg'])) {
                    $bgAttrs = ['muted', 'autoplay', 'loop', 'playsinline', 'webkit-playsinline',
                                'disablepictureinpicture', 'disableremoteplayback'];
                    if ($value['bg']) {
                        foreach ($bgAttrs as $a) $el->setAttribute($a, '');
                        $el->setAttribute('preload', 'metadata');
                    } else {
                        foreach ($bgAttrs as $a) $el->removeAttribute($a);
                        $el->removeAttribute('preload');
                    }
                }
                if (isset($value['controls'])) {
                    $value['controls'] ? $el->setAttribute('controls', '') : $el->removeAttribute('controls');
                }
                break;

            case 'link':
                $href = (string)($value['href'] ?? '');
                if (is_safe_url($href)) $el->setAttribute('href', $href);
                if (isset($value['text'])) $el->textContent = (string)$value['text'];
                break;
        }
        $applied++;
    }

    // Structural list operations run after content edits, so a duplicated
    // item carries the content the editor was looking at.
    foreach ($ops as $op) {
        if (!is_array($op)) fail(400, 'Bad list operation.');
        $listId = (string)($op['id'] ?? '');
        $list   = null;
        foreach (find_editables($doc) as $el) {
            if ($el->getAttribute('data-editable') === $listId
                && strtolower((string)$el->getAttribute('data-editable-type')) === 'list') {
                $list = $el;
                break;
            }
        }
        if (!$list) fail(400, "List \"$listId\" not found on this page.");
        $items = list_items($list);

        switch ($op['op'] ?? '') {
            case 'add':
                if (!$items) fail(400, 'Cannot add: the list is empty, so there is no item to copy.');
                $tpl   = $items[isset($op['index']) && isset($items[(int)$op['index']]) ? (int)$op['index'] : count($items) - 1];
                $clone = $tpl->cloneNode(true);
                relabel_editables($clone, $doc);
                $list->appendChild($clone);
                break;

            case 'remove':
                $i = (int)($op['index'] ?? -1);
                if (!isset($items[$i])) fail(400, 'No such list item.');
                if (count($items) <= 1) fail(400, 'Cannot remove the last item — new items are copied from it.');
                $list->removeChild($items[$i]);
                break;

            default:
                fail(400, 'Unknown list operation.');
        }
        $applied++;
    }

    if (!$applied) fail(400, 'No matching editable elements found.');

    make_backup($path, $config);
    atomic_write($path, $doc->saveHTML());
    echo json_encode(['ok' => true, 'applied' => $applied]);
    exit;
}

/* -------------------------------------------------------------- edit mode */

if (isset($_GET['page'])) {
    $path = resolve_page((string)$_GET['page'], $config);
    $doc  = html_load(file_get_contents($path));

    // Bare data-editable / data-editable-type markers get generated ids the
    // first time the page is opened here; the file is normalized once so
    // authors never have to invent ids by hand.
    if (assign_missing_ids($doc) > 0) {
        make_backup($path, $config);
        atomic_write($path, $doc->saveHTML());
    }

    foreach (find_editables($doc) as $el) {
        $type = strtolower($el->getAttribute('data-editable-type') ?: 'text');
        if ($type === 'text') {
            // IDE indentation inside the source looks odd in the inline editor;
            // the browser collapses it visually anyway, so show it collapsed.
            // The file itself only changes if this element is actually edited.
            if (!in_array(strtolower($el->nodeName), ['pre', 'code', 'textarea'], true)) {
                $norm = preg_replace('/\s+/u', ' ', trim($el->textContent));
                if ($norm !== $el->textContent) $el->textContent = $norm;
            }
        }
        if ($type === 'text' || $type === 'html') {
            $el->setAttribute('contenteditable', $type === 'text' ? 'plaintext-only' : 'true');
        }
    }

    $html = $doc->saveHTML();
    $boot = '<script>window.ADMINKA = ' . json_encode(
        [
            'page'  => (string)$_GET['page'],
            'csrf'  => $csrf,
            'media' => [
                'image' => $config['media']['image']['ext'],
                'video' => $config['media']['video']['ext'],
            ],
        ],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) . ';</script>';

    $toolbar = '<script src="admin.php?action=asset&file=ui.js" defer></script>'
             . '<script src="admin.php?action=asset&file=editor.js" defer></script>';

    // inject toolbar right before </body> (string-level, so DOM stays untouched)
    $inject = $boot . $toolbar;
    $pos = strripos($html, '</body>');
    echo $pos !== false
        ? substr($html, 0, $pos) . $inject . substr($html, $pos)
        : $html . $inject;
    exit;
}

/* -------------------------------------------------------------- page list */

$root  = realpath($config['site_root']);
$bdir  = realpath($config['backup_dir']) ?: '';
$pages = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile()) continue;
    if ($bdir && str_starts_with($f->getPathname(), $bdir)) continue;
    if (in_array(strtolower($f->getExtension()), $config['extensions'], true)) {
        $pages[] = ltrim(str_replace($root, '', $f->getPathname()), '/\\');
    }
}
sort($pages);
$passkeys = passkeys_load($config)['creds'];
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Adminka — pages</title>
<style>
  body{font:16px/1.6 system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;background:#f5f4f0}
  h1{font-size:1.4rem}
  h2{font-size:1.1rem;margin-top:40px}
  ul{list-style:none;padding:0}
  li{background:#fff;border:1px solid #ddd;border-radius:6px;margin-bottom:8px}
  li a{display:block;padding:14px 18px;text-decoration:none;color:#10312b}
  li a:hover{background:#fdf3e7}
  .top{display:flex;justify-content:space-between;align-items:center}
  .pk li{display:flex;align-items:center;gap:10px;padding:10px 18px}
  .pk .when{color:#888;font-size:.85rem;margin-left:auto}
  .pk button{border:1px solid #ccc;background:#fff;border-radius:4px;padding:4px 10px;cursor:pointer;font:inherit;font-size:.85rem}
  .pk button:hover{border-color:#b00020;color:#b00020}
  #pk-add{border:1px solid #ccc;background:#fff;border-radius:6px;padding:10px 16px;cursor:pointer;font:inherit}
  #pk-add:hover{background:#fdf3e7}
  .hint{color:#888;font-size:.85rem}
  #pk-msg{color:#b00020;font-size:.9rem}
</style></head><body>
<div class="top"><h1>Editable pages</h1><a href="admin.php?action=logout">Log out</a></div>
<ul>
<?php foreach ($pages as $p): $e = htmlspecialchars($p, ENT_QUOTES); ?>
  <li><a href="admin.php?page=<?= urlencode($p) ?>"><?= $e ?></a></li>
<?php endforeach; ?>
</ul>

<h2>Passkeys</h2>
<ul class="pk">
<?php foreach ($passkeys as $c): ?>
  <li><span><?= htmlspecialchars($c['label']) ?></span>
      <span class="when">added <?= htmlspecialchars($c['created']) ?></span>
      <button data-id="<?= htmlspecialchars($c['id'], ENT_QUOTES) ?>">Remove</button></li>
<?php endforeach; ?>
<?php if (!$passkeys): ?>
  <li><span class="hint" style="padding:10px 0">No passkeys yet.</span></li>
<?php endif; ?>
</ul>
<p><button id="pk-add">Add a passkey for this device</button></p>
<p class="hint">Passkeys work over HTTPS (or on localhost) and let you sign in with Touch&nbsp;ID / Face&nbsp;ID instead of the password.</p>
<p id="pk-msg" hidden></p>

<script>window.ADMINKA = <?= json_encode(['csrf' => $csrf], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="admin.php?action=asset&file=ui.js"></script>
<script>
(function () {
  var CSRF = window.ADMINKA.csrf;
  var msg  = document.getElementById('pk-msg');
  function b2a(buf){var b='',u=new Uint8Array(buf);for(var i=0;i<u.length;i++)b+=String.fromCharCode(u[i]);
    return btoa(b).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');}
  function a2b(s){s=s.replace(/-/g,'+').replace(/_/g,'/');var bin=atob(s),u=new Uint8Array(bin.length);
    for(var i=0;i<bin.length;i++)u[i]=bin.charCodeAt(i);return u.buffer;}
  function show(t){msg.textContent=t;msg.hidden=false;}

  document.getElementById('pk-add').addEventListener('click', function () {
    msg.hidden = true;
    if (!window.PublicKeyCredential) { show('This browser does not support passkeys.'); return; }
    fetch('admin.php?action=passkey_register_options')
      .then(function(r){return r.json();})
      .then(function(r){
        if(!r.ok) throw new Error(r.error);
        var pk = r.publicKey;
        pk.challenge = a2b(pk.challenge);
        pk.user.id   = a2b(pk.user.id);
        pk.excludeCredentials = pk.excludeCredentials.map(function(c){c.id=a2b(c.id);return c;});
        return navigator.credentials.create({publicKey: pk});
      })
      .then(function(cred){
        return AdminkaUI.form({ title: 'Name this passkey', submit: 'Save', fields: [
          { name: 'label', label: 'Shown in the passkey list', value: 'This device' }
        ]}).then(function (v) {
          return fetch('admin.php?action=passkey_register', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ csrf: CSRF, id: cred.id, label: (v && v.label) || 'Passkey', response: {
              clientDataJSON:    b2a(cred.response.clientDataJSON),
              attestationObject: b2a(cred.response.attestationObject)
            }})
          }).then(function(r){return r.json();});
        });
      })
      .then(function(r){
        if(!r.ok) throw new Error(r.error);
        location.reload();
      })
      .catch(function(e){ show(e.message || 'Could not add the passkey.'); });
  });

  document.querySelectorAll('.pk button[data-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      AdminkaUI.confirm({ title: 'Remove this passkey?',
                          message: 'You will no longer be able to sign in with it.',
                          confirm: 'Remove' })
        .then(function (ok) {
          if (!ok) return;
          fetch('admin.php?action=passkey_delete', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ csrf: CSRF, id: btn.getAttribute('data-id') })
          }).then(function(r){return r.json();})
            .then(function(r){ if (r.ok) location.reload(); else show(r.error); });
        });
    });
  });
})();
</script>
</body></html>
