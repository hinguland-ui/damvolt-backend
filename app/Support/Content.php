<?php

namespace App\Support;

use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Industry;
use App\Models\LegalPage;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the single JSON payload the React site loads on start-up.
 * Cached until something is saved in the admin panel (see ClearsContentCache).
 */
class Content
{
    const KEY = 'api.content.v1';

    public static function flush(): void
    {
        Cache::forget(self::KEY);
    }

    public static function get(): array
    {
        return Cache::rememberForever(self::KEY, fn () => self::build());
    }

    private static function link(?string $label, ?string $url): array
    {
        return ['label' => $label, 'to' => $url];
    }

    private static function build(): array
    {
        $brand = Setting::section('brand');
        $contact = Setting::section('contact');
        $business = Setting::section('business');
        $social = Setting::section('social');
        $offices = Setting::section('offices')['items'] ?? [];

        $site = [
            'name' => $brand['site_name'] ?? '',
            'shortName' => $brand['short_name'] ?? '',
            'tagline' => $brand['tagline'] ?? '',
            'description' => $brand['description'] ?? '',
            'logo' => Media::url($brand['logo'] ?? null),
            'footerLogo' => Media::url($brand['footer_logo'] ?? null),
            'favicon' => Media::url($brand['favicon'] ?? null),
            'emails' => array_values(array_filter([$contact['email_1'] ?? null, $contact['email_2'] ?? null])),
            'phones' => array_values(array_filter([$contact['phone_1'] ?? null, $contact['phone_2'] ?? null])),
            'whatsapp' => $contact['whatsapp'] ?? '',
            'gst' => $business['gst_number'] ?? '',
            'cin' => $business['cin'] ?? '',
            'hours' => $business['hours'] ?? '',
            'offices' => array_map(fn ($o) => [
                'label' => $o['label'] ?? '',
                'address' => $o['address'] ?? '',
                'map' => $o['map'] ?? null,
                'mapLink' => $o['map_link'] ?? null,
            ], $offices),
            'social' => [
                'facebook' => $social['facebook'] ?? '#',
                'instagram' => $social['instagram'] ?? '#',
                'linkedin' => $social['linkedin'] ?? '#',
                'youtube' => $social['youtube'] ?? '#',
            ],
            'recaptcha' => ['enabled' => Recaptcha::enabled(), 'siteKey' => Recaptcha::enabled() ? Recaptcha::siteKey() : ''],
        ];

        $seo = Setting::section('home.seo');
        $about = Setting::section('home.about');
        $stats = Setting::section('home.stats');
        $why = Setting::section('home.why');
        $process = Setting::section('home.process');

        $home = [
            'seo' => [
                'title' => $seo['meta_title'] ?? '',
                'description' => $seo['meta_description'] ?? '',
                'keywords' => $seo['meta_keywords'] ?? '',
                'ogImage' => Media::url($seo['og_image'] ?? null),
            ],
            'slides' => HeroSlide::live()->get()->map(fn ($s) => [
                'image' => Media::url($s->image),
                'kicker' => $s->kicker,
                'title' => $s->title,
                'text' => $s->text,
                'cta' => self::link($s->btn1_label, $s->btn1_url),
                'cta2' => self::link($s->btn2_label, $s->btn2_url),
            ])->all(),
            'trust' => Setting::section('home.trust')['items'] ?? [],
            'about' => [
                'eyebrow' => $about['eyebrow'] ?? '',
                'title' => $about['title'] ?? '',
                'lead' => $about['lead'] ?? '',
                'text' => $about['text'] ?? '',
                'image' => Media::url($about['image'] ?? null),
                'badgeValue' => $about['badge_value'] ?? '',
                'badgeLabel' => $about['badge_label'] ?? '',
                'points' => array_values(array_filter(array_column($about['points'] ?? [], 'text'))),
                'buttonLabel' => $about['button_label'] ?? '',
                'buttonUrl' => $about['button_url'] ?? '/about',
            ],
            'servicesHead' => Setting::section('home.services'),
            'statsHead' => ['eyebrow' => $stats['eyebrow'] ?? '', 'title' => $stats['title'] ?? ''],
            'stats' => array_map(fn ($s) => [
                'value' => (int) ($s['value'] ?? 0),
                'suffix' => $s['suffix'] ?? '',
                'label' => $s['label'] ?? '',
            ], $stats['items'] ?? []),
            'whyHead' => ['eyebrow' => $why['eyebrow'] ?? '', 'title' => $why['title'] ?? '', 'text' => $why['text'] ?? ''],
            'why' => $why['items'] ?? [],
            'processHead' => ['eyebrow' => $process['eyebrow'] ?? '', 'title' => $process['title'] ?? ''],
            'process' => collect($process['items'] ?? [])->values()->map(fn ($p, $i) => [
                'step' => str_pad($i + 1, 2, '0', STR_PAD_LEFT),
                'title' => $p['title'] ?? '',
                'text' => $p['text'] ?? '',
            ])->all(),
            'industriesHead' => Setting::section('home.industries'),
            'reviewsHead' => Setting::section('home.reviews'),
            'cta' => Setting::section('home.cta'),
        ];

        $categories = ServiceCategory::live()->get();
        $categoryNames = $categories->pluck('name', 'id');

        return [
            'version' => Setting::section('system')['content_version'] ?? 0,
            'site' => $site,
            'home' => $home,
            'pageSeo' => collect(['about', 'services', 'industries', 'contact', 'careers', 'faq'])->mapWithKeys(fn ($k) => [$k => [
                'title' => Setting::section("seo.$k")['meta_title'] ?? '',
                'description' => Setting::section("seo.$k")['meta_description'] ?? '',
            ]])->all(),
            'categories' => $categories->map(fn ($c) => [
                'key' => $c->name,
                'title' => $c->heading ?: $c->name,
                'text' => $c->description,
            ])->all(),
            'services' => Service::live()->get()->map(fn ($s) => [
                'slug' => $s->slug,
                'title' => $s->title,
                'category' => $categoryNames[$s->service_category_id] ?? '',
                'icon' => $s->icon,
                'image' => Media::url($s->image),
                'short' => $s->short,
                'intro' => $s->intro,
                'offerings' => $s->offerings ?? [],
                'benefits' => $s->benefits ?? [],
                'applications' => $s->applications ?? [],
                'metaTitle' => $s->meta_title,
                'metaDescription' => $s->meta_description,
            ])->all(),
            'industries' => Industry::live()->get()->map(fn ($i) => [
                'title' => $i->title,
                'icon' => $i->icon,
                'image' => Media::url($i->image),
            ])->all(),
            'reviews' => Testimonial::live()->get()->map(fn ($t) => [
                'name' => $t->name,
                'role' => $t->role,
                'text' => $t->text,
                'rating' => $t->rating,
            ])->all(),
            'faqs' => Faq::live()->get()->map(fn ($f) => ['q' => $f->question, 'a' => $f->answer])->all(),
            'legal' => LegalPage::live()->get()->map(fn ($p) => [
                'slug' => $p->slug,
                'title' => $p->title,
                'content' => $p->content,
                'description' => $p->meta_description,
                'updated' => $p->updated_at?->format('F Y'),
            ])->all(),
        ];
    }
}
