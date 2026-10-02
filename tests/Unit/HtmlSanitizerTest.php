<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_keeps_basic_formatting(): void
    {
        $out = HtmlSanitizer::clean('<h2>Title</h2><p>Hello <strong>bold</strong> <em>it</em></p><ul><li>One</li></ul>');
        $this->assertSame('<h2>Title</h2><p>Hello <strong>bold</strong> <em>it</em></p><ul><li>One</li></ul>', $out);
    }

    #[DataProvider('attacks')]
    public function test_removes_script_vectors(string $html, string $mustNotContain): void
    {
        $this->assertStringNotContainsStringIgnoringCase($mustNotContain, HtmlSanitizer::clean($html));
    }

    public static function attacks(): array
    {
        return [
            'script tag' => ['<p>a</p><script>alert(1)</script>', 'alert'],
            'event handler' => ['<p onclick="steal()">a</p>', 'onclick'],
            'img onerror' => ['<img src=x onerror=alert(1)>', 'onerror'],
            'javascript href' => ['<a href="javascript:alert(1)">x</a>', 'javascript'],
            'unquoted javascript href' => ['<a href=javascript:alert(1)>x</a>', 'javascript'],
            'tab-obfuscated href' => ["<a href=\"java\tscript:alert(1)\">x</a>", 'script:'],
            'entity-obfuscated href' => ['<a href="&#106;avascript:alert(1)">x</a>', 'javascript'],
            'data href' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:'],
            'iframe' => ['<iframe src="https://evil.example"></iframe>', 'iframe'],
            'svg onload' => ['<svg onload=alert(1)><circle/></svg>', 'onload'],
            'style attr' => ['<p style="background:url(javascript:alert(1))">a</p>', 'style'],
            'form' => ['<form action="https://evil.example"><input name=x></form>', 'evil.example'],
        ];
    }

    public function test_safe_links_survive_and_external_ones_are_hardened(): void
    {
        $out = HtmlSanitizer::clean('<a href="https://example.com" onclick="x()">a</a> <a href="/privacy-policy">b</a> <a href="mailto:a@b.com">c</a>');
        $this->assertStringContainsString('href="https://example.com"', $out);
        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
        $this->assertStringContainsString('href="/privacy-policy"', $out);
        $this->assertStringContainsString('href="mailto:a@b.com"', $out);
        $this->assertStringNotContainsString('onclick', $out);
    }

    public function test_safe_url_rule(): void
    {
        foreach (['https://a.com', 'http://a.com/x', '/contact', '#top', 'mailto:a@b.c', 'tel:+919999999999'] as $ok) {
            $this->assertTrue(HtmlSanitizer::safeUrl($ok), $ok);
        }
        foreach (['javascript:alert(1)', 'JaVaScRiPt:1', "java\nscript:1", 'data:text/html,x', '//evil.com', 'vbscript:x', ''] as $bad) {
            $this->assertFalse(HtmlSanitizer::safeUrl($bad), $bad);
        }
    }
}
