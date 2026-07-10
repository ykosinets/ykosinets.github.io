<?php

declare(strict_types=1);

$templateFile = __DIR__ . '/index.html';
$contentFile = __DIR__ . '/content/site.json';

$html = file_exists($templateFile) ? (string) file_get_contents($templateFile) : '';
$content = read_site_content($contentFile);

if ($html === '' || !$content) {
    echo $html;
    exit;
}

$html = replace_tag_content($html, 'title', value($content, 'meta.title'));
$html = replace_meta_description($html, value($content, 'meta.description'));

$html = replace_first_text($html, 'Automotive creator sponsorships', value($content, 'hero.eyebrow'));
$html = replace_hero_title($html, value($content, 'hero.title'));
$html = replace_class_content($html, 'hero__tagline', value($content, 'hero.tagline'));
$html = replace_class_content($html, 'hero__copy', value($content, 'hero.copy'));
$html = replace_first_text($html, 'Book a discovery call', value($content, 'hero.primaryCta'));
$html = replace_first_text($html, 'Explore services', value($content, 'hero.secondaryCta'));

$html = replace_id_content($html, 'proof-title', value($content, 'framework.title'));
$html = replace_repeated_blocks($html, 'proof__row', value($content, 'framework.rows'), static function (string $block, array $item): string {
    $block = replace_nth_tag_content($block, 'span', 1, $item['label'] ?? null);
    $block = replace_nth_tag_content($block, 'strong', 1, $item['value'] ?? null);
    return replace_nth_tag_content($block, 'span', 2, $item['text'] ?? null);
});

$html = replace_first_text($html, 'Campaign context', value($content, 'mediaBand.eyebrow'));
$html = replace_id_content($html, 'media-title', value($content, 'mediaBand.title'));
$html = replace_repeated_blocks($html, 'media-band__card', value($content, 'mediaBand.cards'), static function (string $block, array $item): string {
    return replace_figcaption($block, $item['title'] ?? null, $item['text'] ?? null);
});

$html = replace_first_text($html, 'Services', value($content, 'services.eyebrow'));
$html = replace_repeated_blocks($html, 'services__card', value($content, 'services.cards'), static function (string $block, array $item): string {
    $block = replace_tag_content($block, 'h3', $item['title'] ?? null);
    return replace_tag_content($block, 'p', $item['text'] ?? null);
});

$html = replace_first_text($html, 'Campaign lens', value($content, 'notes.eyebrow'));
$html = replace_id_content($html, 'testimonials-title', value($content, 'notes.title'));
$html = replace_class_content($html, 'testimonials__copy', value($content, 'notes.copy'));
$html = replace_repeated_blocks($html, 'testimonial-slider__slide', value($content, 'notes.slides'), static function (string $block, array $item): string {
    $block = replace_tag_content($block, 'blockquote', $item['quote'] ?? null);
    return replace_tag_content($block, 'cite', $item['cite'] ?? null);
});

$html = replace_id_content($html, 'why-title', value($content, 'why.title'));
$html = replace_repeated_in_block($html, 'why__copy', 'p', value($content, 'why.paragraphs'));
$html = replace_default_list($html, [
    ['Niche fit', 'Campaigns built around automotive audiences and product use cases.'],
    ['Creator relevance', 'Shortlists shaped by trust, content format, and audience intent.'],
    ['Operational clarity', 'Outreach, coordination, and deliverables managed with a B2B standard.'],
], value($content, 'why.benefits'));

$html = replace_id_content($html, 'process-title', value($content, 'process.title'));
$html = replace_repeated_tag_pairs_in_class($html, 'process__list', 'li', value($content, 'process.steps'), static function (string $block, array $item): string {
    $block = replace_tag_content($block, 'h3', $item['title'] ?? null);
    return replace_tag_content($block, 'p', $item['text'] ?? null);
});
$html = replace_class_child_tag_content($html, 'process__media', 'strong', value($content, 'process.mediaTitle'));
$html = replace_process_caption_text($html, value($content, 'process.mediaText'));

$html = replace_first_text($html, 'For product-led automotive brands', value($content, 'cta.eyebrow'));
$html = replace_id_content($html, 'cta-title', value($content, 'cta.title'));
$html = replace_first_text($html, 'Discuss your campaign', value($content, 'cta.button'));

$html = replace_id_content($html, 'contact-title', value($content, 'contact.title'));
$html = replace_contact_intro($html, value($content, 'contact.text'));
$html = replace_first_text($html, 'Send enquiry', value($content, 'contact.button'));

$html = replace_first_text($html, '&copy; 2026 TorqueLink Media. All rights reserved.', value($content, 'footer.copyright'));

echo $html;

function read_site_content(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }

    $content = json_decode((string) file_get_contents($file), true);

    return is_array($content) ? $content : [];
}

function value(array $content, string $path): mixed
{
    $current = $content;

    foreach (explode('.', $path) as $part) {
        if (!is_array($current) || !array_key_exists($part, $current)) {
            return null;
        }

        $current = $current[$part];
    }

    return $current;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function replace_first_text(string $html, string $search, mixed $value): string
{
    if ($value === null) {
        return $html;
    }

    return preg_replace('/' . preg_quote($search, '/') . '/', e($value), $html, 1) ?? $html;
}

function replace_meta_description(string $html, mixed $value): string
{
    if ($value === null) {
        return $html;
    }

    return preg_replace('/(<meta\s+name="description"\s+content=")[^"]*(")/i', '$1' . e($value) . '$2', $html, 1) ?? $html;
}

function replace_hero_title(string $html, mixed $value): string
{
    if ($value === null) {
        return $html;
    }

    $replacement = strtolower(trim((string) $value)) === 'torquelink media'
        ? 'TorqueLink <small>Media</small>'
        : e($value);

    return preg_replace('/(<h1\s+id="hero-title"[^>]*>).*?(<\/h1>)/s', '$1' . $replacement . '$2', $html, 1) ?? $html;
}

function replace_id_content(string $html, string $id, mixed $value): string
{
    if ($value === null) {
        return $html;
    }

    return preg_replace('/(<[^>]+id="' . preg_quote($id, '/') . '"[^>]*>).*?(<\/[^>]+>)/s', '$1' . e($value) . '$2', $html, 1) ?? $html;
}

function replace_class_content(string $html, string $className, mixed $value): string
{
    if ($value === null) {
        return $html;
    }

    $pattern = '/(<[^>]+class="[^"]*\b' . preg_quote($className, '/') . '\b[^"]*"[^>]*>).*?(<\/[^>]+>)/s';

    return preg_replace($pattern, '$1' . e($value) . '$2', $html, 1) ?? $html;
}

function replace_tag_content(string $html, string $tag, mixed $value): string
{
    if ($value === null) {
        return $html;
    }

    return preg_replace('/(<' . preg_quote($tag, '/') . '\b[^>]*>).*?(<\/' . preg_quote($tag, '/') . '>)/s', '$1' . e($value) . '$2', $html, 1) ?? $html;
}

function replace_nth_tag_content(string $html, string $tag, int $nth, mixed $value): string
{
    if ($value === null) {
        return $html;
    }

    $count = 0;

    return preg_replace_callback('/(<' . preg_quote($tag, '/') . '\b[^>]*>).*?(<\/' . preg_quote($tag, '/') . '>)/s', static function (array $matches) use (&$count, $nth, $value): string {
        $count++;

        if ($count !== $nth) {
            return $matches[0];
        }

        return $matches[1] . e($value) . $matches[2];
    }, $html) ?? $html;
}

function replace_figcaption(string $html, mixed $title, mixed $text): string
{
    if ($title === null && $text === null) {
        return $html;
    }

    $caption = '<strong>' . e($title ?? '') . '</strong> ' . e($text ?? '');

    return preg_replace('/(<figcaption\b[^>]*>).*?(<\/figcaption>)/s', '$1' . $caption . '$2', $html, 1) ?? $html;
}

function replace_repeated_blocks(string $html, string $className, mixed $items, callable $callback): string
{
    if (!is_array($items)) {
        return $html;
    }

    $index = 0;
    $pattern = '/(<([a-z0-9]+)\b[^>]*class="[^"]*\b' . preg_quote($className, '/') . '\b[^"]*"[^>]*>.*?<\/\2>)/is';

    return preg_replace_callback($pattern, static function (array $matches) use (&$index, $items, $callback): string {
        if (!array_key_exists($index, $items)) {
            $index++;
            return $matches[0];
        }

        $item = $items[$index];
        $index++;

        return is_array($item) ? $callback($matches[0], $item) : $matches[0];
    }, $html) ?? $html;
}

function replace_default_list(string $html, array $defaults, mixed $items): string
{
    if (!is_array($items)) {
        return $html;
    }

    foreach ($items as $index => $item) {
        if (!is_array($item) || !isset($defaults[$index])) {
            continue;
        }

        $html = replace_first_text($html, $defaults[$index][0], $item['title'] ?? null);
        $html = replace_first_text($html, $defaults[$index][1], $item['text'] ?? null);
    }

    return $html;
}

function replace_repeated_in_block(string $html, string $className, string $tag, mixed $items): string
{
    if (!is_array($items)) {
        return $html;
    }

    return replace_repeated_blocks($html, $className, [$items], static function (string $block, array $blockItems) use ($tag): string {
        $index = 0;

        return preg_replace_callback('/(<' . preg_quote($tag, '/') . '\b[^>]*>).*?(<\/' . preg_quote($tag, '/') . '>)/s', static function (array $matches) use (&$index, $blockItems): string {
            if (!array_key_exists($index, $blockItems)) {
                $index++;
                return $matches[0];
            }

            $value = $blockItems[$index];
            $index++;

            return $matches[1] . e($value) . $matches[2];
        }, $block) ?? $block;
    });
}

function replace_repeated_tag_pairs(string $html, string $tag, array $items, callable $callback): string
{
    $index = 0;

    return preg_replace_callback('/(<' . preg_quote($tag, '/') . '\b[^>]*>.*?<\/' . preg_quote($tag, '/') . '>)/s', static function (array $matches) use (&$index, $items, $callback): string {
        if (!array_key_exists($index, $items) || !is_array($items[$index])) {
            $index++;
            return $matches[0];
        }

        $item = $items[$index];
        $index++;

        return $callback($matches[0], $item);
    }, $html) ?? $html;
}

function replace_repeated_tag_pairs_in_class(string $html, string $className, string $tag, mixed $items, callable $callback): string
{
    if (!is_array($items)) {
        return $html;
    }

    return replace_repeated_blocks($html, $className, [$items], static function (string $block, array $blockItems) use ($tag, $callback): string {
        return replace_repeated_tag_pairs($block, $tag, $blockItems, $callback);
    });
}

function replace_class_child_tag_content(string $html, string $className, string $tag, mixed $value): string
{
    if ($value === null) {
        return $html;
    }

    return replace_repeated_blocks($html, $className, [['value' => $value]], static function (string $block, array $item) use ($tag): string {
        return replace_tag_content($block, $tag, $item['value'] ?? null);
    });
}

function replace_process_caption_text(string $html, mixed $value): string
{
    if ($value === null) {
        return $html;
    }

    return replace_repeated_blocks($html, 'process__media', [['value' => $value]], static function (string $block, array $item): string {
        return preg_replace('/(<figcaption\b[^>]*>\s*<strong\b[^>]*>.*?<\/strong>).*?(<\/figcaption>)/s', '$1 ' . e($item['value'] ?? '') . '$2', $block, 1) ?? $block;
    });
}

function replace_contact_intro(string $html, mixed $value): string
{
    if ($value === null) {
        return $html;
    }

    return preg_replace('/(<h2\s+id="contact-title"[^>]*>.*?<\/h2>\s*<p>).*?(<\/p>)/s', '$1' . e($value) . '$2', $html, 1) ?? $html;
}
