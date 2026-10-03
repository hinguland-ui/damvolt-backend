<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
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

        RateLimiter::clear('admin-lock:127.0.0.1');
        for ($i = 0; $i < 4; $i++) {
            $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        // 5th attempt is locked even with the correct password …
        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'right-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // … and the lock (about 30 s) is still there after a page refresh, so the countdown never restarts.
        $wait = RateLimiter::availableIn('admin-lock:127.0.0.1');
        $this->assertGreaterThan(0, $wait);
        $this->assertLessThanOrEqual(30, $wait);
        $this->get('/admin/login')->assertOk()->assertSee('id="lock-secs">'.$wait, false);

        RateLimiter::clear('admin-lock:127.0.0.1');
        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'right-password'])->assertRedirect('/admin');
    }

    private function smtp(): void
    {
        Setting::put('smtp', ['host' => 'smtp.test', 'port' => 587, 'encryption' => 'tls', 'from_email' => 'noreply@test.com', 'password' => '']);
        Mail::fake();
    }

    private function sentCode(): string
    {
        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        return $code;
    }

    public function test_forgot_password_three_steps(): void
    {
        $this->smtp();
        $admin = $this->admin(['email' => 'boss@example.com', 'password' => 'old-password']);

        $this->postJson('/admin/password/send', ['email' => 'boss@example.com'])->assertOk()->assertJson(['ok' => true]);
        $code = $this->sentCode();

        // new password is refused until the code is right
        $this->postJson('/admin/password/reset', ['password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertStatus(410);
        $this->postJson('/admin/password/verify', ['code' => $code === '123456' ? '654321' : '123456'])->assertStatus(422);
        $this->postJson('/admin/password/verify', ['code' => $code])->assertOk();
        $this->postJson('/admin/password/reset', ['password' => 'new-password-1', 'password_confirmation' => 'different'])->assertStatus(422);
        $this->postJson('/admin/password/reset', ['password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertOk();

        $this->assertTrue(Hash::check('new-password-1', $admin->fresh()->password));
        // the code cannot be used twice
        $this->postJson('/admin/password/verify', ['code' => $code])->assertStatus(410);
    }

    public function test_forgot_password_does_not_reveal_unknown_emails_or_mail_them(): void
    {
        $this->smtp();
        $this->postJson('/admin/password/send', ['email' => 'nobody@example.com'])->assertOk()->assertJson(['ok' => true]);
        Mail::assertNothingSent();
    }

    public function test_forgot_password_waits_a_minute_after_three_tries_even_for_unknown_emails(): void
    {
        $this->smtp();
        RateLimiter::clear('pw-reset-lock:127.0.0.1');
        RateLimiter::clear('pw-reset-try:127.0.0.1');

        $this->postJson('/admin/password/send', ['email' => 'nobody1@example.com'])->assertOk()->assertJson(['retry_after' => 0]);
        $this->postJson('/admin/password/send', ['email' => 'nobody2@example.com'])->assertOk()->assertJson(['retry_after' => 0]);
        $this->postJson('/admin/password/send', ['email' => 'nobody3@example.com'])->assertOk()->assertJson(['retry_after' => 60]);
        $this->postJson('/admin/password/send', ['email' => 'nobody4@example.com'])->assertStatus(429)->assertJson(['ok' => false]);

        // still locked after a refresh: the login page starts the countdown from the server clock
        $wait = RateLimiter::availableIn('pw-reset-lock:127.0.0.1');
        $this->assertGreaterThan(0, $wait);
        $this->assertLessThanOrEqual(60, $wait);
        $this->get('/admin/login')->assertOk()->assertSee('var pwLeft = '.$wait, false);
    }

    public function test_forgot_password_needs_the_captcha_when_it_is_enabled(): void
    {
        $this->smtp();
        $this->admin(['email' => 'boss@example.com']);
        Setting::put('recaptcha', ['enabled' => true, 'site_key' => 'site', 'secret_key' => Crypt::encryptString('secret')]);
        $google = $this->fakeGoogle(false);
        RateLimiter::clear('pw-reset-lock:127.0.0.1');
        RateLimiter::clear('pw-reset-try:127.0.0.1');

        $this->postJson('/admin/password/send', ['email' => 'boss@example.com'])->assertStatus(422);                                  // no tick
        $this->postJson('/admin/password/send', ['email' => 'boss@example.com', 'g-recaptcha-response' => 'bad'])->assertStatus(422); // Google says no
        Mail::assertNothingSent();

        $google->pass = true;
        $this->postJson('/admin/password/send', ['email' => 'boss@example.com', 'g-recaptcha-response' => 'ok'])->assertOk();
        Mail::assertSent(OtpMail::class);
    }

    public function test_meta_pixel_id_is_validated_and_sent_to_the_website(): void
    {
        $this->asAdmin()->put('/admin/settings/pixel', ['pixel_id' => 'abc<script>'])->assertSessionHasErrors('pixel_id');
        $this->asAdmin()->put('/admin/settings/pixel', ['pixel_id' => '123456789012345'])->assertSessionHasNoErrors();
        $this->assertSame('123456789012345', \App\Support\Content::get()['site']['metaPixelId'] ?? null);

        $this->asAdmin()->put('/admin/settings/pixel', ['pixel_id' => ''])->assertSessionHasNoErrors();
        $this->assertSame('', \App\Support\Content::get()['site']['metaPixelId'] ?? null);
    }

    public function test_reset_code_dies_after_five_wrong_guesses(): void
    {
        $this->smtp();
        $this->admin(['email' => 'boss@example.com']);
        $this->postJson('/admin/password/send', ['email' => 'boss@example.com']);
        $code = $this->sentCode();
        $wrong = $code === '111111' ? '222222' : '111111';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/admin/password/verify', ['code' => $wrong])->assertStatus(422);
        }
        $this->postJson('/admin/password/verify', ['code' => $code])->assertStatus(422);
    }

    public function test_two_step_login_needs_the_emailed_code(): void
    {
        $this->smtp();
        Setting::put('security', ['two_factor' => true]);
        $this->admin(['email' => 'boss@example.com', 'password' => 'right-password']);

        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'right-password'])->assertRedirect('/admin/login/code');
        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/admin/login');

        $code = $this->sentCode();
        $this->post('/admin/login/code', ['code' => $code === '123456' ? '654321' : '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/admin/login/code', ['code' => $code])->assertRedirect('/admin');
        $this->assertAuthenticated();
        $this->assertEqualsWithDelta(time(), session('admin_login_at'), 5);
    }

    public function test_two_step_login_wrong_password_sends_no_code_and_off_means_off(): void
    {
        $this->smtp();
        Setting::put('security', ['two_factor' => true]);
        $this->admin(['email' => 'boss@example.com', 'password' => 'right-password']);
        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        Mail::assertNothingSent();

        Setting::put('security', ['two_factor' => false]);
        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'right-password'])->assertRedirect('/admin');
    }

    public function test_two_step_login_stays_off_while_smtp_is_not_set_up(): void
    {
        Setting::put('security', ['two_factor' => true]);
        $this->admin(['email' => 'boss@example.com', 'password' => 'right-password']);
        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'right-password'])->assertRedirect('/admin');
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

    public function test_users_page_only_allows_changing_a_password(): void
    {
        $admin = $this->admin(['email' => 'me@example.com', 'name' => 'Me', 'password' => 'old-password']);
        $other = $this->admin(['email' => 'other@example.com']);

        // no adding, no deleting
        $this->asAdmin($admin)->get('/admin/users/create')->assertStatus(405);
        $this->asAdmin($admin)->post('/admin/users', ['name' => 'x', 'email' => 'x@example.com'])->assertStatus(405);
        $this->asAdmin($admin)->delete("/admin/users/{$other->id}")->assertStatus(405);
        $this->assertDatabaseHas('users', ['id' => $other->id]);

        // name / email / role / status in the request are ignored; the password changes
        $this->asAdmin($admin)->put("/admin/users/{$admin->id}", ['name' => 'Hacker', 'email' => 'new@example.com', 'role' => 'user', 'is_active' => 0, 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasNoErrors();
        $fresh = $admin->fresh();
        $this->assertSame(['Me', 'me@example.com', 'admin', true], [$fresh->name, $fresh->email, $fresh->role, $fresh->is_active]);
        $this->assertTrue(Hash::check('new-password-1', $fresh->password));

        // a password is required
        $this->asAdmin($admin)->put("/admin/users/{$admin->id}", ['name' => 'Me'])->assertSessionHasErrors('password');
    }

    public function test_replacing_a_picture_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $png = fn ($name) => UploadedFile::fake()->image($name, 40, 40);

        // first upload, then replace it WITHOUT ticking "remove": the old file must be gone
        $this->asAdmin()->put('/admin/home/about', ['title' => 'About', 'image' => $png('a.png')])->assertSessionHasNoErrors();
        $first = Setting::section('home.about')['image'];
        $this->assertTrue($disk->exists($first));

        $this->asAdmin()->put('/admin/home/about', ['title' => 'About', 'image' => $png('b.png')])->assertSessionHasNoErrors();
        $second = Setting::section('home.about')['image'];
        $this->assertNotSame($first, $second);
        $this->assertFalse($disk->exists($first));
        $this->assertTrue($disk->exists($second));
        $this->assertCount(1, $disk->allFiles('uploads'));

        // saving again without a new file keeps the picture
        $this->asAdmin()->put('/admin/home/about', ['title' => 'About 2'])->assertSessionHasNoErrors();
        $this->assertTrue($disk->exists($second));

        // a starter picture (images/…) is removed on replace too — unless another place still uses it
        $disk->put('images/starter.webp', 'x');
        Setting::put('home.about', ['title' => 'About', 'image' => 'images/starter.webp']);
        Setting::put('brand', ['site_name' => 'S', 'short_name' => 'S', 'logo' => 'images/starter.webp']);   // shared
        $this->asAdmin()->put('/admin/home/about', ['title' => 'About', 'image' => $png('c.png')])->assertSessionHasNoErrors();
        $this->assertTrue($disk->exists('images/starter.webp'));                                              // still used by brand
        Setting::put('brand', ['site_name' => 'S', 'short_name' => 'S']);
        Setting::put('home.about', ['title' => 'About', 'image' => 'images/starter.webp']);
        $this->asAdmin()->put('/admin/home/about', ['title' => 'About', 'image' => $png('d.png')])->assertSessionHasNoErrors();
        $this->assertFalse($disk->exists('images/starter.webp'));
    }

    public function test_unused_uploaded_pictures_are_cleaned_up_but_new_and_used_ones_stay(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put('uploads/used.png', 'x');
        $disk->put('uploads/orphan-old.png', 'x');
        $disk->put('uploads/orphan-new.png', 'x');
        Setting::put('home.about', ['title' => 'A', 'image' => 'uploads/used.png']);
        touch($disk->path('uploads/used.png'), time() - 3 * 86400);
        touch($disk->path('uploads/orphan-old.png'), time() - 3 * 86400);

        $this->assertSame(1, Media::cleanOrphans());

        $this->assertTrue($disk->exists('uploads/used.png'));
        $this->assertFalse($disk->exists('uploads/orphan-old.png'));
        $this->assertTrue($disk->exists('uploads/orphan-new.png'));      // younger than a day
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
