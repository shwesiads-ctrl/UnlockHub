<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Popups built from blocks (home welcome popup + one per service).
 *
 * Everything an admin types goes through sanitize(): only known block types,
 * known options and safe URLs survive, and all text is escaped when rendered
 * (only **bold** is turned into markup). So popups can't inject HTML/JS.
 */
class Popup
{
    /** Hero gradients + accent colour (headings, step titles). */
    public const THEMES = [
        'orange' => ['label' => 'Orange', 'from' => '#FF6A00', 'to' => '#FF9A1F', 'accent' => '#EA580C'],
        'blue' => ['label' => 'Royal blue', 'from' => '#2F5DB4', 'to' => '#1E3A7A', 'accent' => '#2459E8'],
        'sky' => ['label' => 'Sky blue', 'from' => '#1E90FF', 'to' => '#0B4FB3', 'accent' => '#0B6FD6'],
        'navy' => ['label' => 'Navy', 'from' => '#0F1B3D', 'to' => '#23407A', 'accent' => '#1E40AF'],
        'dark' => ['label' => 'Black', 'from' => '#050505', 'to' => '#1F1F1F', 'accent' => '#2563EB'],
        'amber' => ['label' => 'Amber', 'from' => '#F5A30B', 'to' => '#B45309', 'accent' => '#C2410C'],
        'purple' => ['label' => 'Purple', 'from' => '#6D28D9', 'to' => '#3B0764', 'accent' => '#7C3AED'],
        'green' => ['label' => 'Green', 'from' => '#16A34A', 'to' => '#065F46', 'accent' => '#15803D'],
        'red' => ['label' => 'Red', 'from' => '#DC2626', 'to' => '#7F1D1D', 'accent' => '#B91C1C'],
        'none' => ['label' => 'No header', 'from' => null, 'to' => null, 'accent' => '#2459E8'],
    ];

    public const TONES = ['info', 'success', 'warning', 'danger', 'amber', 'plain', 'dark'];

    public const BUTTON_COLORS = [
        'green' => '#16A34A', 'blue' => '#2459E8', 'dark' => '#1F2937', 'orange' => '#EA580C',
        'red' => '#DC2626', 'purple' => '#7C3AED', 'whatsapp' => '#22C55E', 'telegram' => '#0088CC',
    ];

    public const TABLE_TONES = ['', 'green', 'red', 'amber', 'gray'];

    private const MAX_BLOCKS = 40;

    /** Decode the editor's JSON and clean it. Null when there's nothing worth showing. */
    public static function fromJson(?string $json): ?array
    {
        $data = json_decode((string) $json, true);

        if (! is_array($data)) {
            return null;
        }

        $popup = self::sanitize($data);

        return ($popup['blocks'] || $popup['title'] !== '') ? $popup : null;
    }

    public static function sanitize(array $in): array
    {
        $theme = array_key_exists($in['theme'] ?? '', self::THEMES) ? $in['theme'] : 'blue';

        $blocks = [];
        foreach (array_slice(is_array($in['blocks'] ?? null) ? $in['blocks'] : [], 0, self::MAX_BLOCKS) as $b) {
            if (is_array($b) && ($clean = self::block($b))) {
                $blocks[] = $clean;
            }
        }

        return [
            'enabled' => filter_var($in['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'theme' => $theme,
            'align' => ($in['align'] ?? '') === 'left' ? 'left' : 'center',
            'badge' => self::str($in['badge'] ?? '', 60),
            'title' => self::str($in['title'] ?? '', 200),
            'subtitle' => self::str($in['subtitle'] ?? '', 300),
            'footer' => self::str($in['footer'] ?? '', 200),
            // When it opens: on page load, or only from the "Service info" button
            'trigger' => ($in['trigger'] ?? '') === 'button' ? 'button' : 'open',
            'frequency' => in_array($in['frequency'] ?? '', ['always', 'session', 'daily', 'once'], true) ? $in['frequency'] : 'session',
            // Home popup only
            'audience' => in_array($in['audience'] ?? '', ['all', 'guests', 'users'], true) ? $in['audience'] : 'all',
            'pages' => ($in['pages'] ?? '') === 'all' ? 'all' : 'home',
            'delay' => max(0, min(30, (int) ($in['delay'] ?? 0))),
            'blocks' => $blocks,
        ];
    }

    private static function block(array $b): ?array
    {
        return match ($b['type'] ?? null) {
            'heading' => ($t = self::str($b['text'] ?? '', 120)) !== '' ? ['type' => 'heading', 'text' => $t] : null,
            'text' => ($t = self::str($b['text'] ?? '', 2000)) !== '' ? ['type' => 'text', 'text' => $t] : null,
            'list' => ($items = self::lines($b['items'] ?? [], 30, 300)) ? [
                'type' => 'list',
                'style' => in_array($b['style'] ?? '', ['bullet', 'check', 'cross', 'number'], true) ? $b['style'] : 'bullet',
                'items' => $items,
            ] : null,
            'box' => self::emptyBox($b) ? null : [
                'type' => 'box',
                'tone' => in_array($b['tone'] ?? '', self::TONES, true) ? $b['tone'] : 'info',
                'title' => self::str($b['title'] ?? '', 150),
                'text' => self::str($b['text'] ?? '', 1500),
                'items' => self::lines($b['items'] ?? [], 30, 300),
                'link_label' => self::str($b['link_label'] ?? '', 80),
                'link_url' => self::url($b['link_url'] ?? ''),
                'half' => filter_var($b['half'] ?? false, FILTER_VALIDATE_BOOL),
            ],
            'steps' => ($items = self::rows($b['items'] ?? [], 15, ['title' => 120, 'text' => 600, 'link_label' => 80, 'link_url' => 'url'])) ? [
                'type' => 'steps', 'title' => self::str($b['title'] ?? '', 120), 'items' => $items,
            ] : null,
            'chips' => ($items = self::lines($b['items'] ?? [], 80, 60)) ? [
                'type' => 'chips', 'title' => self::str($b['title'] ?? '', 120), 'items' => $items,
            ] : null,
            'table' => ($rows = self::rows($b['rows'] ?? [], 25, ['key' => 80, 'value' => 200, 'tone' => 'table_tone'])) ? [
                'type' => 'table', 'title' => self::str($b['title'] ?? '', 120), 'rows' => $rows,
            ] : null,
            'buttons' => ($items = array_values(array_filter(
                self::rows($b['items'] ?? [], 4, ['label' => 60, 'url' => 'url', 'color' => 'button_color']),
                fn ($r) => $r['label'] !== '',
            ))) ? ['type' => 'buttons', 'items' => $items] : null,
            default => null,
        };
    }

    // ---------------------------------------------------------------- helpers

    private static function emptyBox(array $b): bool
    {
        return self::str($b['title'] ?? '', 150) === '' && self::str($b['text'] ?? '', 1500) === ''
            && ! self::lines($b['items'] ?? [], 30, 300) && self::str($b['link_label'] ?? '', 80) === '';
    }

    private static function str(mixed $v, int $max): string
    {
        return is_scalar($v) ? mb_substr(trim(str_replace("\r", '', (string) $v)), 0, $max) : '';
    }

    /** Only http(s), mailto:, tel:, tg:// links or site-relative paths. */
    public static function url(mixed $v): string
    {
        $v = self::str($v, 500);

        return preg_match('~^(https?://|mailto:|tel:|tg://|/(?!/))~i', $v) ? $v : '';
    }

    /** @return list<string> */
    private static function lines(mixed $v, int $max, int $len): array
    {
        $v = is_string($v) ? explode("\n", $v) : (is_array($v) ? $v : []);

        return array_slice(array_values(array_filter(array_map(fn ($l) => self::str($l, $len), $v), fn ($l) => $l !== '')), 0, $max);
    }

    /** Rows of named columns; column spec is a max length or 'url' / 'table_tone' / 'button_color'. */
    private static function rows(mixed $v, int $max, array $cols): array
    {
        $out = [];
        foreach (is_array($v) ? $v : [] as $r) {
            if (! is_array($r)) {
                continue;
            }
            $row = [];
            foreach ($cols as $key => $spec) {
                $row[$key] = match ($spec) {
                    'url' => self::url($r[$key] ?? ''),
                    'table_tone' => in_array($r[$key] ?? '', self::TABLE_TONES, true) ? $r[$key] : '',
                    'button_color' => array_key_exists($r[$key] ?? '', self::BUTTON_COLORS) ? $r[$key] : 'blue',
                    default => self::str($r[$key] ?? '', $spec),
                };
            }
            if (implode('', $row) !== '') {
                $out[] = $row;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------- rendering

    public static function theme(string $key): array
    {
        return self::THEMES[$key] ?? self::THEMES['blue'];
    }

    /** Escape, then allow **bold**, keep line breaks; {name} and {site} placeholders. */
    public static function inline(string $text): HtmlString
    {
        $text = strtr($text, [
            '{name}' => auth()->user()?->name ?? 'Guest',
            '{site}' => (string) setting('site.name', config('app.name')),
        ]);
        $html = e($text);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);

        return new HtmlString(nl2br($html, false));
    }

    /** Item already starts with "1." / an emoji / a dash, so no extra bullet. */
    public static function hasMarker(string $item): bool
    {
        return (bool) preg_match('/^(\d+[.)]|\p{So}|[❌✅⚠⛔•\-])/u', $item);
    }

    /** Short hash: when the admin edits a popup, people see it again. */
    public static function hash(array $popup): string
    {
        return substr(md5(json_encode($popup)), 0, 8);
    }

    /** Groups consecutive half-width boxes so they render side by side. */
    public static function groups(array $blocks): array
    {
        $groups = [];
        foreach ($blocks as $b) {
            $half = ($b['type'] === 'box' && $b['half']);
            $last = array_key_last($groups);
            if ($half && $last !== null && $groups[$last]['half'] && count($groups[$last]['blocks']) < 2) {
                $groups[$last]['blocks'][] = $b;
            } else {
                $groups[] = ['half' => $half, 'blocks' => [$b]];
            }
        }

        return $groups;
    }

    /** The home (welcome) popup, if one is saved. */
    public static function home(): ?array
    {
        $raw = setting('popup.home');

        return $raw ? self::fromJson($raw) : null;
    }

    /** Should the home popup show on this request? */
    public static function homeVisible(?array $popup): bool
    {
        if (! $popup || ! $popup['enabled']) {
            return false;
        }
        if ($popup['pages'] === 'home' && ! request()->routeIs('home')) {
            return false;
        }

        return match ($popup['audience']) {
            'guests' => auth()->guest(),
            'users' => auth()->check(),
            default => true,
        };
    }
}
