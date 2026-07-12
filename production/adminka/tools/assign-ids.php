<?php
/**
 * Adminka — fill in generated IDs for bare data-editable attributes.
 *
 * This now also happens automatically the first time a page is opened in the
 * admin; the CLI remains for normalizing files before deploy (or previewing
 * with --dry-run). Duplicate ids are re-assigned too (first occurrence wins).
 *
 *   php tools/assign-ids.php index.html            # writes (keeps a .bak copy)
 *   php tools/assign-ids.php index.html --dry-run  # only shows what it would do
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit(1);

require __DIR__ . '/../admin-lib/util.php';
require __DIR__ . '/../admin-lib/html.php';

$args = array_slice($argv, 1);
$dry  = in_array('--dry-run', $args, true);
$file = array_values(array_diff($args, ['--dry-run']))[0] ?? null;

if ($file === null) {
    fwrite(STDERR, "Usage: php tools/assign-ids.php <page.html> [--dry-run]\n");
    exit(1);
}
if (!is_file($file)) {
    fwrite(STDERR, "Not a file: $file\n");
    exit(1);
}

$doc    = html_load(file_get_contents($file));
$before = [];
foreach (find_editable_candidates($doc) as $el) {
    $before[spl_object_id($el)] = (string)$el->getAttribute('data-editable');
}

$changed = assign_missing_ids($doc);

foreach (find_editable_candidates($doc) as $el) {
    $old = $before[spl_object_id($el)] ?? '';
    $new = $el->getAttribute('data-editable');
    if ($old === $new) continue;
    printf("  <%s> %s-> data-editable=\"%s\"\n",
        strtolower($el->nodeName),
        $old !== '' ? "(duplicate \"$old\") " : '',
        $new);
}

if (!$changed) {
    echo "Nothing to do: every editable in $file already has a unique id\n";
    exit(0);
}
if ($dry) {
    echo "$changed id(s) would be assigned (dry run, file untouched)\n";
    exit(0);
}

copy($file, $file . '.bak');
file_put_contents($file, $doc->saveHTML());
echo "$changed id(s) assigned; original kept as $file.bak\n";
