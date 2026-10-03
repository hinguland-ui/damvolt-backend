<?php

namespace Tests\Feature;

use App\Mail\ContactAdminMail;
use App\Mail\ContactCustomerMail;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Models\LegalPage;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Support\Facades\DB;
use App\Support\Housekeeping;
use App\Support\EnquiryMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EnquiryAndSeoTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cors.allowed_origins' => [self::ORIGIN]]);
        RateLimiter::clear('contact');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin())->withSession(['admin_login_at' => time()]);
    }

    private function smtp(array $over = []): void
    {
        Setting::put('smtp', array_merge([
            'host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none', 'username' => '', 'password' => '',
            'from_email' => 'info@example.com', 'from_name' => 'Site', 'receive_email' => 'owner@example.com', 'send_confirmation' => true,
        ], $over));
    }

    private function enquiry(array $over = []): ContactMessage
    {
        return ContactMessage::create(array_merge([
            'name' => 'Asha', 'phone' => '9876543210', 'email' => 'asha@example.com', 'service' => 'Transformers', 'message' => 'Need a quote please',
        ], $over));
    }

    // ---------------------------------------------------------------- e-mails for a contact enquiry

    public function test_both_emails_go_out_when_smtp_is_saved(): void
    {
        Mail::fake();
        $this->smtp();
        $e = $this->enquiry();

        $this->assertSame('sent', EnquiryMailer::deliver($e)['status']);

        Mail::assertSent(ContactAdminMail::class, fn ($m) => $m->hasTo('owner@example.com'));
        Mail::assertSent(ContactCustomerMail::class, fn ($m) => $m->hasTo('asha@example.com'));
        $this->assertSame('sent', $e->fresh()->mail_status);
    }

    public function test_an_empty_receive_address_still_notifies_someone_and_still_confirms_to_the_customer(): void
    {
        Mail::fake();
        Setting::put('contact', ['email_1' => 'sales@example.com']);
        $this->smtp(['receive_email' => '']);

        EnquiryMailer::deliver($this->enquiry());

        Mail::assertSent(ContactAdminMail::class, fn ($m) => $m->hasTo('sales@example.com'));   // falls back to the company address
        Mail::assertSent(ContactCustomerMail::class);                                            // customer is NOT skipped any more
    }

    public function test_confirmation_can_be_switched_off_and_needs_a_customer_email(): void
    {
        Mail::fake();
        $this->smtp(['send_confirmation' => false]);
        EnquiryMailer::deliver($this->enquiry());
        Mail::assertSent(ContactAdminMail::class);
        Mail::assertNotSent(ContactCustomerMail::class);

        Mail::fake();
        $this->smtp();
        EnquiryMailer::deliver($this->enquiry(['email' => null, 'phone' => '9876543211']));
        Mail::assertSent(ContactAdminMail::class);
        Mail::assertNotSent(ContactCustomerMail::class);
    }

    public function test_unsaved_smtp_is_reported_not_silently_ignored_and_the_enquiry_is_kept(): void
    {
        Mail::fake();
        $e = $this->enquiry();

        $result = EnquiryMailer::deliver($e);

        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('SMTP is not saved', $e->fresh()->mail_error);
        Mail::assertNothingSent();
        $this->assertSame(1, ContactMessage::count());
    }

    public function test_a_broken_smtp_server_marks_the_enquiry_failed_but_keeps_it_with_the_reason(): void
    {
        $this->smtp(['port' => 1]);                       // nothing listens there
        $e = $this->enquiry();

        $result = EnquiryMailer::deliver($e);

        $this->assertSame('failed', $result['status']);
        $this->assertNotEmpty($e->fresh()->mail_error);
        $this->assertTrue(collect($result['lines'])->contains(fn ($l) => str_contains($l, '❌')));
        $this->assertSame(1, ContactMessage::count());
    }

    public function test_console_lines_use_emojis_and_name_every_recipient(): void
    {
        Mail::fake();
        $this->smtp();

        $lines = implode("\n", EnquiryMailer::deliver($this->enquiry())['lines']);

        foreach (['📨', '📡', '✅ Admin mail sent → owner@example.com', '✅ Customer confirmation sent → asha@example.com', '🏁'] as $needle) {
            $this->assertStringContainsString($needle, $lines);
        }
    }

    public function test_the_admin_can_resend_after_fixing_smtp(): void
    {
        Mail::fake();
        $e = $this->enquiry(['mail_status' => 'skipped']);
        $this->smtp();

        $this->asAdmin()->post("/admin/enquiries/{$e->id}/resend")->assertSessionHas('success');
        Mail::assertSent(ContactAdminMail::class);
        $this->assertSame('sent', $e->fresh()->mail_status);
    }

    public function test_the_contact_form_triggers_the_emails(): void
    {
        Mail::fake();
        $this->smtp();

        $this->postJson('/api/contact', ['name' => 'Raj Kumar', 'phone' => '09811122233', 'email' => 'raj@example.com', 'message' => 'Please call me about panels.'], ['Origin' => self::ORIGIN])->assertCreated();

        Mail::assertSent(ContactAdminMail::class);
        Mail::assertSent(ContactCustomerMail::class, fn ($m) => $m->hasTo('raj@example.com'));
    }

    // ---------------------------------------------------------------- activity log

    public function test_actions_are_logged_with_who_and_what(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->withSession(['admin_login_at' => time()])
            ->put('/admin/home/cta', ['title' => 'Hello', 'text' => 'x'])->assertSessionHasNoErrors();

        $log = ActivityLog::where('action', 'update')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->email, $log->actor);
        $this->assertStringContainsString('Call-to-action', $log->description);
    }

    public function test_login_attempts_are_logged_without_passwords(): void
    {
        User::factory()->create(['email' => 'boss@example.com', 'role' => 'admin', 'is_active' => true, 'password' => 'right-password']);

        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'wrong-one']);
        $this->post('/admin/login', ['email' => 'boss@example.com', 'password' => 'right-password']);

        $this->assertTrue(ActivityLog::where('action', 'login_failed')->where('actor', 'boss@example.com')->exists());
        $this->assertTrue(ActivityLog::where('action', 'login')->where('actor', 'boss@example.com')->exists());
        $this->assertFalse(ActivityLog::where('description', 'like', '%right-password%')->orWhere('description', 'like', '%wrong-one%')->exists());
    }

    public function test_the_log_keeps_only_the_newest_maximum_entries(): void
    {
        Setting::put('security', ['max_logs' => 50]);
        for ($i = 0; $i < 70; $i++) {
            ActivityLog::create(['action' => 'update', 'description' => "row {$i}", 'created_at' => now()]);
        }

        $this->artisan('activity:prune')->assertSuccessful();

        $this->assertSame(50, ActivityLog::count());
        $this->assertSame('row 69', ActivityLog::orderByDesc('id')->value('description'));   // newest kept
        $this->assertSame('row 20', ActivityLog::orderBy('id')->value('description'));        // oldest 20 gone
    }

    public function test_the_log_page_shows_the_newest_entries_and_the_limit(): void
    {
        Setting::put('security', ['max_logs' => 50]);
        for ($i = 0; $i < 60; $i++) {
            ActivityLog::create(['action' => 'update', 'description' => "change number {$i}", 'actor' => 'a@example.com', 'created_at' => now()]);
        }

        $this->asAdmin()->get('/admin/activity')->assertOk()->assertSee('change number 59')->assertDontSee('change number 5<')->assertSee('newest 50 are kept');
        $this->assertSame(50, ActivityLog::count());
    }

    public function test_log_entries_can_be_bulk_deleted(): void
    {
        for ($i = 0; $i < 12; $i++) {
            ActivityLog::create(['action' => 'update', 'description' => "row {$i}", 'created_at' => now()]);
        }
        $ids = ActivityLog::orderBy('id')->limit(10)->pluck('id')->all();

        $this->post('/admin/activity/delete', ['ids' => $ids])->assertRedirect('/admin/login');
        $this->asAdmin()->post('/admin/activity/delete', ['ids' => $ids])->assertSessionHas('success');
        $this->assertSame(0, ActivityLog::whereIn('id', $ids)->count());
        $this->assertSame(2, ActivityLog::where('description', 'like', 'row %')->count());
        $this->asAdmin()->post('/admin/activity/delete', ['ids' => []])->assertSessionHasErrors('ids');
    }

    public function test_max_logs_setting_is_validated_and_used(): void
    {
        $this->asAdmin()->put('/admin/settings/security', ['max_logs' => 10])->assertSessionHasErrors('max_logs');
        $this->asAdmin()->put('/admin/settings/security', ['max_logs' => 200])->assertSessionHasNoErrors();
        $this->assertSame(200, Activity::maxLogs());
    }

    public function test_housekeeping_removes_expired_cache_rows_and_sessions(): void
    {
        $now = time();
        DB::table('cache')->insert([['key' => 'old', 'value' => 's:1:"x";', 'expiration' => $now - 100], ['key' => 'fresh', 'value' => 's:1:"x";', 'expiration' => $now + 1000]]);
        DB::table('sessions')->insert([['id' => 'dead', 'payload' => '', 'last_activity' => $now - 999999], ['id' => 'alive', 'payload' => '', 'last_activity' => $now]]);

        config(['cache.default' => 'database', 'session.driver' => 'database']);   // phpunit uses array stores
        Housekeeping::run();

        $this->assertDatabaseMissing('cache', ['key' => 'old']);
        $this->assertDatabaseHas('cache', ['key' => 'fresh']);
        $this->assertDatabaseMissing('sessions', ['id' => 'dead']);
        $this->assertDatabaseHas('sessions', ['id' => 'alive']);
    }

    public function test_the_log_search_is_injection_safe_and_login_protected(): void
    {
        $this->get('/admin/activity')->assertRedirect('/admin/login');
        Activity::log('update', 'normal', null, 'a@example.com');

        $this->asAdmin()->get('/admin/activity?q='.urlencode("' OR 1=1 --").'&action='.urlencode("x' OR '1'='1"))->assertOk()->assertSee('Nothing logged yet.');
        $this->assertSame(1, ActivityLog::count());
    }

    // ---------------------------------------------------------------- SEO, sitemap, error pages

    public function test_page_seo_is_saved_and_reaches_the_website(): void
    {
        $this->asAdmin()->put('/admin/seo/about', ['meta_title' => 'About Damvolt | Electrical experts', 'meta_description' => 'Who we are'])
            ->assertSessionHasNoErrors();

        $api = $this->getJson('/api/content', ['Origin' => self::ORIGIN])->assertOk();
        $this->assertSame('About Damvolt | Electrical experts', $api->json('pageSeo.about.title'));
        $this->assertSame('', $api->json('pageSeo.contact.title'));
        $this->assertNull(Setting::section('seo.about')['_serp'] ?? null);          // the preview box stores nothing
    }

    public function test_the_seo_pages_show_the_google_preview(): void
    {
        $admin = $this->admin();
        foreach (['/admin/seo', '/admin/home', '/admin/services/create', '/admin/legal/create'] as $url) {
            $this->actingAs($admin)->withSession(['admin_login_at' => time()])->get($url)->assertOk()->assertSee('Google preview');
        }
    }

    public function test_the_sitemap_lists_pages_services_and_legal_pages(): void
    {
        Service::create(['title' => 'Transformers', 'slug' => 'transformers', 'icon' => 'Zap', 'is_active' => true]);
        Service::create(['title' => 'Hidden', 'slug' => 'hidden-one', 'icon' => 'Zap', 'is_active' => false]);
        LegalPage::create(['title' => 'Privacy', 'slug' => 'privacy-policy', 'is_active' => true]);

        $r = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $r->headers->get('Content-Type'));
        $xml = $r->getContent();

        foreach (['http://localhost:5173/', '/about', '/services/transformers', '/privacy-policy'] as $needle) {
            $this->assertStringContainsString($needle, $xml);
        }
        $this->assertStringNotContainsString('hidden-one', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));                      // valid XML
    }

    public function test_error_pages_are_plain_and_carry_no_framework_branding(): void
    {
        $body = $this->get('/no-such-page')->assertNotFound()->getContent();

        $this->assertStringContainsString('404', $body);
        $this->assertStringNotContainsStringIgnoringCase('laravel', $body);
        $this->assertStringContainsString('noindex', $body);
    }
}
