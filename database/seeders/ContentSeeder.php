<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Industry;
use App\Models\LegalPage;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Loads the website's original content (taken from the old hard-coded React data)
 * so the admin panel starts with everything already filled in.
 * Safe to re-run: it only fills tables/sections that are still empty.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->publishAssets();

        $d = json_decode(file_get_contents(__DIR__.'/data/content.json'), true);

        foreach (['brand', 'contact', 'business', 'offices', 'social'] as $key) {
            $this->section($key, $d[$key]);
        }
        foreach ($d['home'] as $key => $data) {
            $this->section("home.$key", $data);
        }

        if (! HeroSlide::exists()) {
            foreach ($d['slides'] as $i => $s) {
                HeroSlide::create($s + ['sort_order' => $i, 'is_active' => true]);
            }
        }

        if (! ServiceCategory::exists()) {
            foreach ($d['categories'] as $i => $c) {
                ServiceCategory::create($c + ['sort_order' => $i]);
            }
        }

        if (! Service::exists()) {
            $cats = ServiceCategory::pluck('id', 'name');
            foreach ($d['services'] as $i => $s) {
                Service::create([
                    'service_category_id' => $cats[$s['category']] ?? null,
                    'title' => $s['title'],
                    'slug' => $s['slug'],
                    'icon' => $s['icon'],
                    'image' => $s['image'],
                    'short' => $s['short'],
                    'intro' => $s['intro'],
                    'offerings' => $s['offerings'],
                    'benefits' => $s['benefits'],
                    'applications' => $s['applications'],
                    'sort_order' => $i,
                ]);
            }
        }

        if (! Industry::exists()) {
            foreach ($d['industries'] as $i => $r) {
                Industry::create($r + ['sort_order' => $i]);
            }
        }

        if (! Testimonial::exists()) {
            foreach ($d['reviews'] as $i => $r) {
                Testimonial::create([
                    'name' => $r['name'], 'role' => $r['role'], 'text' => $r['text'],
                    'rating' => $r['rating'], 'sort_order' => $i,
                ]);
            }
        }

        if (! Faq::exists()) {
            foreach ($d['faqs'] as $i => $f) {
                Faq::create($f + ['sort_order' => $i]);
            }
        }

        if (! LegalPage::exists()) {
            foreach ($d['legal'] as $i => $p) {
                LegalPage::create([
                    'title' => $p['title'], 'slug' => $p['slug'], 'content' => $p['content'],
                    'meta_description' => $p['description'], 'sort_order' => $i,
                ]);
            }
        }
    }

    /**
     * The starter pictures / logos (database/seeders/assets) are copied into storage/app/public on first run.
     * Files uploaded later through the admin panel go to storage/app/public/uploads and are never in git.
     */
    private function publishAssets(): void
    {
        $source = __DIR__.'/assets';
        if (! is_dir($source)) {
            return;
        }

        foreach (File::allFiles($source) as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            if (! Storage::disk('public')->exists($path)) {
                Storage::disk('public')->put($path, File::get($file->getPathname()));
            }
        }
    }

    private function section(string $key, array $data): void
    {
        if (! Setting::where('key', $key)->exists()) {
            Setting::put($key, $data);
        }
    }
}
