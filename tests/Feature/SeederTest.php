<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_gets_content_pictures_and_an_admin_from_env(): void
    {
        Storage::fake('public');
        config(['app.seed_admin_email' => 'owner@example.com', 'app.seed_admin_password' => 'Chosen-Pass-123']);

        $this->seed();

        $admin = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('Chosen-Pass-123', $admin->password));

        $this->assertGreaterThan(0, Service::count());
        Storage::disk('public')->assertExists('images/transformers.webp');   // starter pictures are published into storage
        Storage::disk('public')->assertExists('brand/logo.png');
    }

    public function test_reseeding_never_resets_an_existing_admin_password(): void
    {
        Storage::fake('public');
        config(['app.seed_admin_email' => 'owner@example.com', 'app.seed_admin_password' => 'First-Pass-123']);
        $this->seed();

        User::where('email', 'owner@example.com')->first()->update(['password' => 'Changed-By-Owner-1']);
        config(['app.seed_admin_password' => 'First-Pass-123']);
        $this->seed();

        $this->assertTrue(Hash::check('Changed-By-Owner-1', User::where('email', 'owner@example.com')->first()->password));
        $this->assertSame(1, User::where('email', 'owner@example.com')->count());
    }

    public function test_without_an_admin_password_a_random_one_is_generated_not_a_default(): void
    {
        Storage::fake('public');
        config(['app.seed_admin_email' => 'owner@example.com', 'app.seed_admin_password' => null]);

        $this->seed();

        $admin = User::where('email', 'owner@example.com')->first();
        foreach (['password', 'admin@123', 'admin123', 'secret'] as $guess) {
            $this->assertFalse(Hash::check($guess, $admin->password), "default password '{$guess}' must not work");
        }
    }
}
