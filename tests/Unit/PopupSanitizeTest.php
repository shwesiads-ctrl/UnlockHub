<?php

namespace Tests\Unit;

use App\Support\Popup;
use App\Support\PopupTemplates;
use Tests\TestCase;

class PopupSanitizeTest extends TestCase
{
    public function test_unsafe_links_and_unknown_blocks_are_dropped(): void
    {
        $p = Popup::sanitize([
            'theme' => 'nope',
            'blocks' => [
                ['type' => 'box', 'title' => 'Hi', 'link_label' => 'x', 'link_url' => 'javascript:alert(1)'],
                ['type' => 'evil', 'text' => '<script>'],
                ['type' => 'buttons', 'items' => [['label' => 'Go', 'url' => '//evil.example', 'color' => 'zzz']]],
            ],
        ]);

        $this->assertSame('blue', $p['theme']);
        $this->assertCount(2, $p['blocks']);
        $this->assertSame('', $p['blocks'][0]['link_url']);
        $this->assertSame('', $p['blocks'][1]['items'][0]['url']);
        $this->assertSame('blue', $p['blocks'][1]['items'][0]['color']);
    }

    public function test_text_is_escaped_and_only_bold_is_allowed(): void
    {
        $html = (string) Popup::inline('<b>x</b> **ok**');

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringContainsString('<strong>ok</strong>', $html);
    }

    public function test_templates_are_already_clean(): void
    {
        foreach (PopupTemplates::all() as $key => $t) {
            $this->assertSame($t['popup'], Popup::sanitize($t['popup']), "Template {$key} changes when sanitised");
        }
    }
}
