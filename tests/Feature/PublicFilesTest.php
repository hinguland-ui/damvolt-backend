<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::disk('public')->put('uploads/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
        Storage::disk('public')->put('uploads/notes.txt', 'plain text');
        Storage::disk('public')->put('uploads/.hidden.png', 'x');
        // a picture just OUTSIDE the disk's folder, which must never be reachable
        file_put_contents(dirname(rtrim(Storage::disk('public')->path(''), '/\\')).DIRECTORY_SEPARATOR.'outside.png', 'outside');
    }

    public function test_an_uploaded_picture_is_served_even_without_the_storage_symlink(): void
    {
        $r = $this->get('/storage/uploads/logo.png')->assertOk();

        $this->assertSame('image/png', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age', $r->headers->get('Cache-Control'));
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_pictures_are_served_without_starting_a_session_or_setting_cookies(): void
    {
        $r = $this->get('/storage/uploads/logo.png')->assertOk();

        $this->assertEmpty($r->headers->getCookies());
    }

    public function test_only_pictures_inside_the_disk_can_be_fetched(): void
    {
        $this->get('/storage/uploads/missing.png')->assertNotFound();
        $this->get('/storage/uploads/notes.txt')->assertNotFound();            // not a picture
        $this->get('/storage/uploads/.hidden.png')->assertNotFound();          // hidden file
        $this->get('/storage/.htaccess')->assertNotFound();
        $this->get('/storage/uploads/logo.php')->assertNotFound();
        $this->get('/storage/uploads/%2e%2e/outside.png')->assertNotFound();     // traversal
        $this->get('/storage/..%2f..%2f.env')->assertNotFound();
        $this->get('/storage/uploads/logo.png%00.php')->assertNotFound();
    }

    public function test_a_second_request_gets_304_not_modified(): void
    {
        $first = $this->get('/storage/uploads/logo.png')->assertOk();
        $lastModified = $first->headers->get('Last-Modified');
        $this->assertNotEmpty($lastModified);

        $this->get('/storage/uploads/logo.png', ['If-Modified-Since' => $lastModified])->assertStatus(304);
    }

    public function test_the_private_disk_no_longer_claims_the_storage_url(): void
    {
        $names = collect(app('router')->getRoutes()->getRoutes())->map->getName()->filter()->all();

        $this->assertNotContains('storage.local', $names);
        $this->assertContains('public-file', $names);
    }
}
