<?php
/** Adminka — DOM parsing, sanitizing, and file-writing helpers. */

declare(strict_types=1);

function html_load(string $html): object
{
    if (class_exists('\Dom\HTMLDocument')) {
        return \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    // Encoding hint so legacy parser treats the source as UTF-8
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR);
    foreach ($doc->childNodes as $n) {           // drop the xml PI we injected
        if ($n->nodeType === XML_PI_NODE) { $doc->removeChild($n); break; }
    }
    return $doc;
}

/** Find all [data-editable] elements in a doc (works on both parsers). */
function find_editables(object $doc): array
{
    if (method_exists($doc, 'querySelectorAll')) {
        return iterator_to_array($doc->querySelectorAll('[data-editable]'));
    }
    $xp = new DOMXPath($doc);
    return iterator_to_array($xp->query('//*[@data-editable]'));
}

/** Elements that opt into editing, including ones still missing an id. */
function find_editable_candidates(object $doc): array
{
    $nodes = method_exists($doc, 'querySelectorAll')
        ? $doc->querySelectorAll('[data-editable],[data-editable-type]')
        : (new DOMXPath($doc))->query('//*[@data-editable or @data-editable-type]');
    // PHP's querySelectorAll returns a node once per matching selector in a
    // list — dedupe so an element with both attributes appears only once.
    $out = [];
    foreach ($nodes as $el) {
        $out[spl_object_id($el)] = $el;
    }
    return array_values($out);
}

function slugify(string $text): string
{
    if (function_exists('transliterator_transliterate')) {
        $text = (string)transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
    } else {
        $text = (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
}

/** A readable id base for an element: content-, filename-, or class-derived. */
function editable_id_base(object $el): string
{
    $tag  = strtolower($el->nodeName);
    $type = strtolower($el->getAttribute('data-editable-type') ?: 'text');

    // Dom\HTMLDocument's getAttribute returns null for absent attributes.
    $src = (string)$el->getAttribute('src');
    $hint = match ($type) {
        'image' => slugify(pathinfo($src, PATHINFO_FILENAME) ?: (string)$el->getAttribute('alt')),
        'video' => slugify(pathinfo($src, PATHINFO_FILENAME)),
        'list', 'form' => slugify(((string)strtok((string)$el->getAttribute('class'), ' ')) ?: $tag) . '-' . $type,
        default => slugify(implode('-', array_slice(preg_split('/\s+/', trim($el->textContent)), 0, 4))),
    };
    $hint = implode('-', array_slice(explode('-', $hint), 0, 4));   // keep it short
    return $hint !== '' ? $hint : $type . '-' . $tag;
}

/**
 * Fill in ids for editables that lack one and re-id duplicates (document
 * order keeps the first occurrence). Deterministic for an unchanged file,
 * so ids generated at render time match the ones regenerated at save time.
 * Returns the number of elements touched.
 */
function assign_missing_ids(object $doc): int
{
    $taken = [];
    $todo  = [];
    foreach (find_editable_candidates($doc) as $el) {
        $id = (string)$el->getAttribute('data-editable');   // null when absent
        if ($id === '' || isset($taken[$id])) {
            $todo[] = $el;
            continue;
        }
        $taken[$id] = true;
    }
    foreach ($todo as $el) {
        $base = editable_id_base($el);
        $id   = $base;
        for ($n = 2; isset($taken[$id]); $n++) $id = "$base-$n";
        $taken[$id] = true;
        $el->setAttribute('data-editable', $id);
    }
    return count($todo);
}

/** All [data-editable] elements within a subtree, including its root. */
function find_editables_in(object $el): array
{
    $out = method_exists($el, 'querySelectorAll')
        ? iterator_to_array($el->querySelectorAll('[data-editable]'))
        : iterator_to_array((new DOMXPath($el->ownerDocument))->query('.//*[@data-editable]', $el));
    if ($el->hasAttribute('data-editable')) array_unshift($out, $el);
    return $out;
}

/** Element children of a list container — its items. */
function list_items(object $list): array
{
    $items = [];
    foreach ($list->childNodes as $c) {
        if ($c->nodeType === XML_ELEMENT_NODE) $items[] = $c;
    }
    return $items;
}

/** Give every data-editable inside a cloned subtree a fresh unique id. */
function relabel_editables(object $clone, object $doc): void
{
    $taken = [];
    foreach (find_editables($doc) as $el) $taken[$el->getAttribute('data-editable')] = true;
    foreach (find_editables_in($clone) as $el) {
        $base = (string)$el->getAttribute('data-editable');
        if ($base === '') continue;
        // Strip clone suffixes so a copy of "card-title-2" becomes
        // "card-title-3", not "card-title-2-2".
        $base = preg_replace('/(?:-\d+)+$/', '', $base) ?: $base;
        for ($n = 2; isset($taken["$base-$n"]); $n++);
        $el->setAttribute('data-editable', "$base-$n");
        $taken["$base-$n"] = true;
    }
}

/** Form controls inside a form-type container, in document order. */
function find_form_controls(object $container): array
{
    return method_exists($container, 'querySelectorAll')
        ? iterator_to_array($container->querySelectorAll('input,textarea,select,button'))
        : iterator_to_array((new DOMXPath($container->ownerDocument))
            ->query('.//input|.//textarea|.//select|.//button', $container));
}

/** The <label> for a control: label[for=id] inside the container, else a wrapping label. */
function find_label(object $container, object $control): ?object
{
    $for = (string)$control->getAttribute('id');
    if ($for !== '') {
        $labels = method_exists($container, 'querySelectorAll')
            ? $container->querySelectorAll('label')
            : (new DOMXPath($container->ownerDocument))->query('.//label', $container);
        foreach ($labels as $l) {
            if ($l->getAttribute('for') === $for) return $l;
        }
    }
    for ($p = $control->parentNode; $p && $p !== $container; $p = $p->parentNode) {
        if ($p->nodeType === XML_ELEMENT_NODE && strtolower($p->nodeName) === 'label') return $p;
    }
    return null;
}

/** Replace a label's own text while keeping any nested elements (e.g. a wrapped input). */
function set_label_text(object $container, object $control, string $text): void
{
    $label = find_label($container, $control);
    if (!$label) return;
    $first = null;
    foreach (iterator_to_array($label->childNodes) as $n) {
        if ($n->nodeType === XML_TEXT_NODE && trim($n->textContent) !== '') {
            if ($first === null) $first = $n;
            else $label->removeChild($n);
        }
    }
    if ($first !== null) $first->textContent = $text;
    else $label->insertBefore($label->ownerDocument->createTextNode($text), $label->firstChild);
}

/** Apply a whitelisted set of field edits to one form control. */
function apply_control_edit(object $container, object $control, array $f): void
{
    $tag = strtolower($control->nodeName);
    if (isset($f['label'])) set_label_text($container, $control, (string)$f['label']);

    if ($tag === 'button') {
        if (isset($f['text'])) $control->textContent = (string)$f['text'];
        return;
    }
    if (isset($f['name'])) {
        $name = (string)$f['name'];
        if ($name === '') $control->removeAttribute('name');
        elseif (preg_match('/^[\w\[\]-]+$/', $name)) $control->setAttribute('name', $name);
    }
    if (array_key_exists('required', $f)) {
        $f['required'] ? $control->setAttribute('required', '') : $control->removeAttribute('required');
    }
    if ($tag === 'input') {
        static $types = ['text', 'email', 'tel', 'url', 'search', 'number', 'password',
                         'date', 'time', 'datetime-local', 'month', 'week', 'color', 'range',
                         'checkbox', 'radio'];
        if (isset($f['type']) && in_array(strtolower((string)$f['type']), $types, true)) {
            $control->setAttribute('type', strtolower((string)$f['type']));
        }
        foreach (['placeholder', 'value'] as $a) {
            if (!isset($f[$a])) continue;
            $v = (string)$f[$a];
            $v === '' ? $control->removeAttribute($a) : $control->setAttribute($a, $v);
        }
    }
    if ($tag === 'textarea') {
        if (isset($f['placeholder'])) {
            $v = (string)$f['placeholder'];
            $v === '' ? $control->removeAttribute('placeholder') : $control->setAttribute('placeholder', $v);
        }
        if (isset($f['value'])) $control->textContent = (string)$f['value'];
    }
    if ($tag === 'select' && (isset($f['options']) || isset($f['placeholder']))) {
        // Existing option nodes, reusable by index: renames and reordering
        // keep each option's value/selected/disabled untouched.
        $orig = method_exists($control, 'querySelectorAll')
            ? iterator_to_array($control->querySelectorAll('option'))
            : iterator_to_array((new DOMXPath($control->ownerDocument))->query('.//option', $control));

        // A select's "placeholder" is its hidden first option
        // (<option value="" disabled selected style="display:none;">).
        // It is managed by the placeholder field, never by the option rows.
        $ph = ($orig
            && $orig[0]->hasAttribute('value') && (string)$orig[0]->getAttribute('value') === ''
            && $orig[0]->hasAttribute('disabled')) ? $orig[0] : null;

        if (isset($f['options']) && is_string($f['options'])) {   // legacy "value | text" lines
            $options = [];
            foreach (preg_split('/\R/', $f['options']) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $parts = array_map('trim', explode('|', $line, 2));
                $options[] = ['text' => $parts[1] ?? $parts[0], 'value' => isset($parts[1]) ? $parts[0] : ''];
            }
        } elseif (isset($f['options']) && is_array($f['options'])) {
            $options = $f['options'];
        } else {                                     // placeholder-only edit: keep options
            $options = [];
            foreach ($orig as $i => $node) {
                if ($node === $ph) continue;
                $options[] = ['text' => (string)$node->textContent, 'from' => $i];
            }
        }

        while ($control->firstChild) $control->removeChild($control->firstChild);

        $phText = isset($f['placeholder'])
            ? trim((string)$f['placeholder'])
            : ($ph ? trim((string)$ph->textContent) : '');
        if ($phText !== '') {
            if (!$ph) {
                $ph = $control->ownerDocument->createElement('option');
                $ph->setAttribute('value', '');
                $ph->setAttribute('disabled', '');
                $ph->setAttribute('selected', '');
                $ph->setAttribute('style', 'display:none;');
            }
            $ph->textContent = $phText;
            $control->appendChild($ph);
        }

        foreach ($options as $o) {
            if (!is_array($o)) continue;
            $text  = (string)($o['text'] ?? '');
            $value = (string)($o['value'] ?? '');
            if (trim($text) === '' && $value === '') continue;
            $from = $o['from'] ?? null;
            if (is_numeric($from) && (int)$from >= 0 && isset($orig[(int)$from]) && $orig[(int)$from] !== $ph) {
                $opt = $orig[(int)$from];
                $opt->textContent = $text;
            } else {
                $opt = $control->ownerDocument->createElement('option');
                $opt->textContent = $text !== '' ? $text : $value;
                if ($value !== '' && $value !== $text) $opt->setAttribute('value', $value);
                if (!empty($o['selected'])) $opt->setAttribute('selected', '');
                if (!empty($o['disabled'])) $opt->setAttribute('disabled', '');
            }
            $control->appendChild($opt);
        }
    }
}

/** May this attribute be edited through the attribute editor at all? */
function attr_name_ok(string $name): bool
{
    $n = strtolower($name);
    return preg_match('/^[a-z][a-z0-9-]*$/', $n) === 1
        && !str_starts_with($n, 'on')
        && !str_starts_with($n, 'data-editable')
        && !in_array($n, ['style', 'srcdoc', 'contenteditable'], true);
}

/** Resolve a requested page to a real path inside site_root, or fail. */
function resolve_page(string $page, array $config): string
{
    $page = ltrim(str_replace('\\', '/', $page), '/');
    $ext  = strtolower(pathinfo($page, PATHINFO_EXTENSION));
    if (!in_array($ext, $config['extensions'], true)) {
        fail(400, 'File type not editable.');
    }
    $root = realpath($config['site_root']);
    $path = realpath($root . '/' . $page);
    if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)
        && $path !== $root . DIRECTORY_SEPARATOR . basename($page)) {
        // allow files directly in root too
        if ($path === false || dirname($path) !== $root && !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            fail(404, 'Page not found.');
        }
    }
    $bdir = realpath($config['backup_dir']);
    if ($bdir !== false && str_starts_with($path, $bdir . DIRECTORY_SEPARATOR)) {
        fail(400, 'Backups are not editable.');
    }
    return $path;
}

function is_safe_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') return false;
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $url, $m)) {   // has a scheme
        $scheme = strtolower(rtrim($m[0], ':'));
        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }
    return true;                                             // relative or #anchor
}

/** Whitelist-sanitize an HTML fragment; returns nodes importable into $target. */
function sanitize_fragment(string $html, object $targetDoc, array $config): array
{
    $doc  = html_load('<!DOCTYPE html><html><body><div id="__frag">' . $html . '</div></body></html>');
    $frag = method_exists($doc, 'querySelector')
        ? $doc->querySelector('#__frag')
        : (new DOMXPath($doc))->query('//*[@id="__frag"]')->item(0);
    if (!$frag) return [];

    sanitize_node($frag, $config);

    $out = [];
    foreach (iterator_to_array($frag->childNodes) as $child) {
        $out[] = $targetDoc->importNode($child, true);
    }
    return $out;
}

function sanitize_node(object $node, array $config): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child->nodeType === XML_COMMENT_NODE) {
            $node->removeChild($child);
            continue;
        }
        if ($child->nodeType !== XML_ELEMENT_NODE) continue;

        $tag = strtolower($child->nodeName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta'], true)) {
            $node->removeChild($child);        // drop tag AND its content
            continue;
        }
        if (!in_array($tag, $config['allowed_tags'], true)) {
            // unwrap: keep children, drop the tag itself
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }
        // strip disallowed attributes
        $allowed = $config['allowed_attrs'][$tag] ?? [];
        foreach (iterator_to_array($child->attributes) as $attr) {
            $name = strtolower($attr->name);
            if (!in_array($name, $allowed, true)
                || ($name === 'href' && !is_safe_url($attr->value))) {
                $child->removeAttribute($attr->name);
            }
        }
        sanitize_node($child, $config);
    }
}

function atomic_write(string $path, string $data): void
{
    $tmp = tempnam(dirname($path), '.adminka_');
    if ($tmp === false || file_put_contents($tmp, $data) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        fail(500, 'Could not write file. Check permissions.');
    }
    @chmod($path, 0644);
}

function make_backup(string $path, array $config): void
{
    $dir = $config['backup_dir'];
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = str_replace(['/', '\\'], '__', ltrim(str_replace(realpath($config['site_root']), '', $path), '/\\'));

    // Prune before creating so this page never keeps more than backup_keep
    // copies: drop the oldest until there's room for the new one.
    // (Timestamped names sort chronologically, so oldest sorts first.)
    $old = glob($dir . '/' . $name . '.*') ?: [];
    sort($old);
    while (count($old) >= $config['backup_keep']) {
        @unlink(array_shift($old));
    }

    copy($path, $dir . '/' . $name . '.' . date('Y-m-d_His'));
}
