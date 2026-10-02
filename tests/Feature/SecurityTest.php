<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cors.allowed_origins' => [self::ORIGIN]]);
        RateLimiter::clear('contact');
    }

    private function admin(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => 'admin', 'is_active' => true], $attrs));
    }

    private function asAdmin(?User $user = null): static
    {
        return $this->actingAs($user ?? $this->admin())->withSession(['admin_login_at' => time()]);
    }

    /** Fakes Google's siteverify; flip the returned flag to change the answer. */
    private function fakeGoogle(bool $pass = false): \stdClass
    {
        $state = (object) ['pass' => $pass];
        Http::fake(['www.google.com/recaptcha/api/siteverify' => fn () => Http::response(['success' => $state->pass, 'error-codes' => $state->pass ? [] : ['invalid-input-response']])]);

        return $state;
    }

    private function enquiry(array $over = []): array
    {
        return array_merge([
            'name' => 'Praveen Kumar',
            'phone' => '09982925680',
            'email' => 'customer@example.com',
            'message' => 'Need a 500 kVA transformer for our plant.',
        ], $over);
    }

    // ---------------------------------------------------------------- admin access

    public function test_every_admin_page_requires_login(): void
    {
        foreach (['/admin', '/admin/home', '/admin/settings', '/admin/services', '/admin/services/create', '/admin/legal',
            '/admin/users', '/admin/enquiries', '/admin/manage/faqs'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        foreach (['/admin/cache/clear', '/admin/smtp/test', '/admin/recaptcha/test', '/admin/manage/faqs', '/admin/services/reorder'] as $url) {
            $this->postJson($url)->assertUnauthorized();
        }
    }

    public function test_non_admin_users_and_inactive_admins_are_refused(): void
    {
        $user = User::factory()->create(['role' => 'user', 'is_active' => true]);
        $this->actingAs($user)->withSession(['admin_login_at' => time()])->get('/admin')->assertRedirect('/admin/login');

        $off = $this->admin(['is_active' => false]);
        $this->actingAs($off)->withSession(['admin_login_at' => time()])->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_login_expires_after_24_hours_and_not_before(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->withSession(['admin_login_at' => time() - 23 * 3600])->get('/admin')->assertOk();
        $this->actingAs($admin)->withSession(['admin_login_at' => time() - 25 * 3600])->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/login'); // no login timestamp at all
    }

    public function test_admin_pages_are_not_cacheable_and_carry_security_headers(): void
    {
        $r = $this->asAdmin()->get('/admin');
        $r->assertOk();
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $r->assertHeader('X-Frame-Options', 'DENY');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_login_is_rate_limited_and_session_is_regenerated(): void
    {
        $this->admin(['email' => 'boss@example.com', 'password' => 'right-password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        // 6th attempt is locked even with the correct password
        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'right-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_login_stamps_the_24h_clock(): void
    {
        $this->admin(['email' => 'boss@example.com', 'password' => 'right-password']);

        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'right-password'])->assertRedirect('/admin');
        $this->assertAuthenticated();
        $this->assertEqualsWithDelta(time(), session('admin_login_at'), 5);
    }

    public function test_admin_login_needs_captcha_when_enabled(): void
    {
        $this->admin(['email' => 'boss@example.com', 'password' => 'right-password']);
        Setting::put('recaptcha', ['enabled' => true, 'site_key' => 'site', 'secret_key' => Crypt::encryptString('secret')]);
        $google = $this->fakeGoogle(false);

        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'right-password', 'g-recaptcha-response' => 'bad'])
            ->assertSessionHasErrors('captcha');
        $this->assertGuest();

        $google->pass = true;
        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'right-password', 'g-recaptcha-response' => 'ok'])
            ->assertRedirect('/admin');
    }

    public function test_return_url_cannot_redirect_to_another_site(): void
    {
        $admin = $this->admin();
        $base = ['question' => 'Q?', 'answer' => 'A', 'is_active' => 1];

        $evil = $this->asAdmin($admin)->post('/admin/manage/faqs', $base + ['_return' => 'https://evil.example/phish']);
        $evil->assertRedirect(route('admin.dashboard'));

        // "starts with our host" trick
        $trick = $this->asAdmin($admin)->post('/admin/manage/faqs', $base + ['_return' => url('/').'.evil.example/x']);
        $trick->assertRedirect(route('admin.dashboard'));

        $good = $this->asAdmin($admin)->post('/admin/manage/faqs', $base + ['_return' => url('/admin/home#tab-seo')]);
        $good->assertRedirect(url('/admin/home#tab-seo'));
    }

    public function test_javascript_links_cannot_be_saved(): void
    {
        $this->asAdmin()->put('/admin/settings/social', ['facebook' => 'javascript:alert(1)', 'instagram' => 'https://instagram.com/x'])
            ->assertSessionHasErrors('facebook');

        $this->asAdmin()->put('/admin/settings/social', ['facebook' => 'https://facebook.com/x', 'instagram' => '#'])
            ->assertSessionHasNoErrors();
    }

    public function test_an_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $admin = $this->admin(['email' => 'me@example.com']);

        $this->asAdmin($admin)->put("/admin/users/{$admin->id}", ['name' => 'Me', 'email' => 'me@example.com', 'role' => 'user', 'is_active' => 1])
            ->assertSessionHas('error');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_secrets_are_encrypted_at_rest_and_shown_back_to_the_admin(): void
    {
        $admin = $this->admin();
        $this->asAdmin($admin)->put('/admin/settings/smtp', [
            'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'password' => 'S3cret-pass!', 'send_confirmation' => 1,
        ])->assertSessionHasNoErrors();

        $stored = Setting::section('smtp')['password'];
        $this->assertNotSame('S3cret-pass!', $stored);
        $this->assertSame('S3cret-pass!', Crypt::decryptString($stored));

        $this->asAdmin($admin)->get('/admin/settings')->assertSee('S3cret-pass!', false);

        // never leaks through the public API
        $this->getJson('/api/content', ['Origin' => self::ORIGIN])->assertOk()->assertDontSee('S3cret-pass!')->assertDontSee($stored);
    }

    public function test_clear_cache_bumps_the_content_version(): void
    {
        $before = $this->getJson('/api/content', ['Origin' => self::ORIGIN])->json('version');
        $this->travel(2)->seconds();
        $this->asAdmin()->postJson('/admin/cache/clear')->assertOk()->assertJson(['ok' => true]);
        $this->assertNotSame($before, $this->getJson('/api/content', ['Origin' => self::ORIGIN])->json('version'));
    }

    // ---------------------------------------------------------------- public contact API

    public function test_contact_form_saves_a_normalised_enquiry(): void
    {
        $this->postJson('/api/contact', $this->enquiry(), ['Origin' => self::ORIGIN])->assertCreated();

        $row = ContactMessage::first();
        $this->assertSame('9982925680', $row->phone);              // leading 0 removed
        $this->assertSame('skipped', $row->mail_status);           // SMTP not configured -> saved, not lost
    }

    public function test_contact_form_only_accepts_the_websites_origin(): void
    {
        $this->postJson('/api/contact', $this->enquiry(), ['Origin' => 'https://evil.example'])->assertForbidden();
        $this->postJson('/api/contact', $this->enquiry())->assertForbidden();                     // no Origin / Referer
        $this->assertSame(0, ContactMessage::count());

        $this->postJson('/api/contact', $this->enquiry(), ['Referer' => self::ORIGIN.'/contact'])->assertCreated();
    }

    public function test_the_same_phone_or_email_cannot_submit_again_within_24_hours(): void
    {
        $this->postJson('/api/contact', $this->enquiry(), ['Origin' => self::ORIGIN])->assertCreated();

        RateLimiter::clear('contact');
        $this->postJson('/api/contact', $this->enquiry(['name' => 'Someone', 'phone' => '+91 99829 25680', 'email' => 'other@example.com']), ['Origin' => self::ORIGIN])
            ->assertStatus(429)->assertJson(['code' => 'duplicate']);

        $this->postJson('/api/contact', $this->enquiry(['phone' => '9876543210']), ['Origin' => self::ORIGIN])   // same e-mail
            ->assertStatus(429)->assertJson(['code' => 'duplicate']);

        $this->travel(25)->hours();
        $this->postJson('/api/contact', $this->enquiry(), ['Origin' => self::ORIGIN])->assertCreated();
    }

    public function test_contact_form_validation_and_honeypot(): void
    {
        $this->postJson('/api/contact', $this->enquiry(['phone' => '12345']), ['Origin' => self::ORIGIN])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->postJson('/api/contact', $this->enquiry(['message' => 'short']), ['Origin' => self::ORIGIN])->assertStatus(422);

        $this->postJson('/api/contact', $this->enquiry(['website' => 'http://spam']), ['Origin' => self::ORIGIN])->assertOk();   // pretends success
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_contact_form_is_rate_limited_per_ip(): void
    {
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/contact', $this->enquiry(['phone' => '98765432'.sprintf('%02d', $i), 'email' => "u{$i}@example.com"]), ['Origin' => self::ORIGIN])->assertCreated();
        }
        $this->postJson('/api/contact', $this->enquiry(['phone' => '9876543299', 'email' => 'u9@example.com']), ['Origin' => self::ORIGIN])->assertStatus(429);
    }

    public function test_contact_form_requires_the_captcha_when_enabled(): void
    {
        Setting::put('recaptcha', ['enabled' => true, 'site_key' => 'site', 'secret_key' => Crypt::encryptString('secret')]);
        $google = $this->fakeGoogle(false);

        $this->postJson('/api/contact', $this->enquiry(), ['Origin' => self::ORIGIN])->assertStatus(422)->assertJson(['code' => 'captcha']);
        $this->postJson('/api/contact', $this->enquiry() + ['captcha' => 'x'], ['Origin' => self::ORIGIN])->assertStatus(422)->assertJson(['code' => 'captcha']);
        $this->assertSame(0, ContactMessage::count());

        $google->pass = true;
        $this->postJson('/api/contact', $this->enquiry() + ['captcha' => 'good'], ['Origin' => self::ORIGIN])->assertCreated();
    }

    public function test_a_half_configured_captcha_never_locks_people_out(): void
    {
        Setting::put('recaptcha', ['enabled' => true, 'site_key' => 'only-the-public-key', 'secret_key' => '']);
        $this->postJson('/api/contact', $this->enquiry(), ['Origin' => self::ORIGIN])->assertCreated();
        $this->get('/admin/login')->assertOk();
    }

    // ---------------------------------------------------------------- injection & abuse

    public function test_sql_injection_in_the_enquiry_search_is_harmless(): void
    {
        ContactMessage::create(['name' => 'Alice', 'phone' => '9876543210', 'message' => 'plain message here']);
        ContactMessage::create(['name' => 'Bob', 'phone' => '9876543211', 'message' => '100% genuine request']);
        $admin = $this->admin();

        foreach ([
            "' OR '1'='1", "' OR 1=1 --", "'; DROP TABLE contact_messages; --", '" OR ""="', "') UNION SELECT password FROM users --",
            '\\', "alice' AND SLEEP(5) --",
        ] as $payload) {
            $r = $this->asAdmin($admin)->get('/admin/enquiries?q='.urlencode($payload));
            $r->assertOk()->assertSee('No enquiries match your search.');        // matched literally, so nothing found
        }
        $this->assertSame(2, ContactMessage::count());                           // table intact, nothing dropped

        // LIKE wildcards are escaped: "%" finds only the row that really contains a percent sign, "_" none
        $this->asAdmin($admin)->get('/admin/enquiries?q='.urlencode('%'))->assertOk()->assertSee('Bob')->assertDontSee('Alice');
        $this->asAdmin($admin)->get('/admin/enquiries?q='.urlencode('_'))->assertOk()->assertSee('No enquiries match your search.');
    }

    public function test_sql_injection_payloads_in_the_contact_form_are_stored_as_plain_text(): void
    {
        $payload = "Robert'); DROP TABLE contact_messages;--";
        $this->postJson('/api/contact', $this->enquiry(['name' => $payload, 'message' => "x' OR '1'='1' -- long enough message"]), ['Origin' => self::ORIGIN])->assertCreated();

        $this->assertSame(1, ContactMessage::count());
        $this->assertSame($payload, ContactMessage::first()->name);
    }

    public function test_the_contact_api_ignores_fields_it_does_not_own(): void
    {
        $this->postJson('/api/contact', $this->enquiry(['id' => 999, 'is_read' => true, 'mail_status' => 'sent', 'ip' => '1.2.3.4', 'created_at' => '2001-01-01']), ['Origin' => self::ORIGIN])->assertCreated();

        $row = ContactMessage::first();
        $this->assertNotSame(999, $row->id);
        $this->assertFalse($row->is_read);
        $this->assertNotSame('1.2.3.4', $row->ip);
        $this->assertSame('skipped', $row->mail_status);
        $this->assertGreaterThan(2020, $row->created_at->year);
    }

    public function test_wrong_data_types_are_rejected_not_crashed(): void
    {
        foreach ([['name' => ['a', 'b']], ['message' => ['x' => 'y']], ['phone' => ['9876543210']], ['email' => ['a@b.c']], ['service' => ['x']]] as $bad) {
            Cache::flush();   // each bad request counts toward the 3/min limit — reset it per try
            $this->postJson('/api/contact', $this->enquiry($bad), ['Origin' => self::ORIGIN])->assertStatus(422);
        }
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_line_breaks_cannot_inject_mail_headers(): void
    {
        $this->postJson('/api/contact', $this->enquiry(['name' => "Eve\r\nBcc: victim@example.com", 'company' => "Corp\nX-Evil: 1"]), ['Origin' => self::ORIGIN])->assertCreated();

        $row = ContactMessage::first();
        $this->assertStringNotContainsString("\n", $row->name);
        $this->assertStringNotContainsString("\r", $row->name);
        $this->assertStringNotContainsString("\n", $row->company);
    }

    public function test_reorder_endpoints_cast_ids_to_integers(): void
    {
        $this->asAdmin()->postJson('/admin/manage/faqs/reorder', ['ids' => ['1; DROP TABLE users', "2' OR '1'='1", ['x']]])->assertOk();
        $this->assertGreaterThan(0, User::count());          // users table still there
    }

    public function test_unknown_crud_resources_and_tabs_are_404(): void
    {
        $this->asAdmin()->get('/admin/manage/users')->assertNotFound();
        $this->asAdmin()->postJson('/admin/manage/users', ['name' => 'x'])->assertNotFound();
        $this->asAdmin()->put('/admin/settings/not-a-tab', [])->assertNotFound();
        $this->asAdmin()->get('/admin/manage/faqs;DROP')->assertNotFound();
    }

    public function test_every_public_or_write_endpoint_has_a_rate_limit(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $limited = fn (string $uri, string $method) => collect($routes->first(fn ($r) => $r->uri() === $uri && in_array($method, $r->methods()))?->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));

        $this->assertTrue($limited('api/contact', 'POST'), 'contact form');
        $this->assertTrue($limited('api/content', 'GET'), 'content feed');
        $this->assertTrue($limited('admin/smtp/test', 'POST'), 'smtp test');
        $this->assertTrue($limited('admin/recaptcha/test', 'POST'), 'recaptcha test');
        $this->assertTrue($limited('admin/cache/clear', 'POST'), 'cache clear');
        // /admin/login POST is limited in AuthController (5 tries / 5 min per email+IP) — covered by its own test above
    }

    // ---------------------------------------------------------------- locked-down backend

    public function test_the_backend_root_goes_to_the_login_page_never_a_framework_page(): void
    {
        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/up')->assertNotFound();
        $this->get('/welcome')->assertNotFound();
    }

    public function test_the_content_api_answers_nothing_to_direct_visits(): void
    {
        $this->get('/api/content')->assertNotFound()->assertSee('', false);                       // no Origin
        $this->get('/api/content', ['Sec-Fetch-Mode' => 'navigate', 'Sec-Fetch-Site' => 'none'])->assertNotFound();   // address bar
        $this->get('/api/content', ['Origin' => 'https://evil.example'])->assertNotFound();
        $this->get('/api/does-not-exist')->assertNotFound();
        $this->assertSame('', $this->get('/api/content')->getContent());

        $this->get('/api/content', ['Origin' => self::ORIGIN])->assertOk()->assertJsonStructure(['version', 'site', 'home']);
        $this->get('/api/content', ['Sec-Fetch-Site' => 'same-origin', 'Sec-Fetch-Mode' => 'cors'])->assertOk();   // our own page
    }

    public function test_admin_css_and_js_are_not_openable_directly(): void
    {
        $url = '/admin/assets/css/admin.css';

        $this->get($url)->assertNotFound();                                                      // curl / unknown
        $this->get($url, ['Sec-Fetch-Dest' => 'document', 'Sec-Fetch-Mode' => 'navigate'])->assertNotFound();   // address bar
        $this->get($url, ['Sec-Fetch-Dest' => 'style', 'Sec-Fetch-Mode' => 'no-cors'])->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
        $this->get('/admin/assets/js/admin.js', ['Sec-Fetch-Dest' => 'script'])->assertOk();
        $this->get($url, ['Referer' => 'http://localhost/admin/login'])->assertOk();            // browsers without Fetch-Metadata
        $this->get('/admin/assets/css/..%2F..%2F.env', ['Sec-Fetch-Dest' => 'style'])->assertNotFound();
        $this->get('/admin/assets/php/admin.php', ['Sec-Fetch-Dest' => 'style'])->assertNotFound();
        $this->assertFileDoesNotExist(public_path('assets/admin/js/admin.js'));                  // not in the web root
    }

    public function test_the_login_page_loads_its_assets_through_the_guarded_route(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('/admin/assets/css/admin.css', false)->assertDontSee('/assets/admin/');
    }

    public function test_ui_demo_pages_are_gone(): void
    {
        foreach (['forms', 'charts', 'editor'] as $page) {
            $this->asAdmin()->get("/admin/ui/{$page}")->assertNotFound();
        }
    }
}
