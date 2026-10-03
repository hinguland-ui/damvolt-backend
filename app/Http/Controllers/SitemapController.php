<?php

namespace App\Http\Controllers;

use App\Models\LegalPage;
use App\Models\Service;
use App\Models\Setting;
use App\Support\Media;
use Illuminate\Support\Carbon;

/**
 * /sitemap.xml — every public page of the WEBSITE (the first FRONTEND_URL), generated from the database,
 * so new services / legal pages appear automatically. robots.txt on the website points search engines here.
 * Each URL carries a last-modified date and the main picture of services, so search engines re-crawl what changed.
 */
class SitemapController extends Controller
{
    public function __invoke()
    {
        $base = rtrim((string) (config('cors.allowed_origins')[0] ?? config('app.url')), '/');

        // Fixed pages change whenever any content is saved in the admin panel.
        $contentChanged = Setting::query()->max('updated_at');
        $siteDate = $contentChanged ? Carbon::parse($contentChanged) : null;

        $urls = [];
        foreach ([
            '/' => [1.0, 'weekly'],
            '/services' => [0.9, 'weekly'],
            '/about' => [0.8, 'monthly'],
            '/contact' => [0.8, 'monthly'],
            '/industries' => [0.7, 'monthly'],
            '/faq' => [0.6, 'monthly'],
        ] as $path => [$priority, $freq]) {
            $urls[] = ['loc' => $base.$path, 'date' => $siteDate, 'priority' => $priority, 'freq' => $freq, 'image' => null];
        }
        foreach (Service::live()->get(['slug', 'image', 'title', 'updated_at']) as $s) {
            $urls[] = ['loc' => $base.'/services/'.$s->slug, 'date' => $s->updated_at, 'priority' => 0.8, 'freq' => 'monthly', 'image' => Media::url($s->image), 'title' => $s->title];
        }
        foreach (LegalPage::live()->get(['slug', 'updated_at']) as $p) {
            $urls[] = ['loc' => $base.'/'.$p->slug, 'date' => $p->updated_at, 'priority' => 0.3, 'freq' => 'yearly', 'image' => null];
        }

        $e = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.$e($u['loc']).'</loc>'
                .($u['date'] ? '<lastmod>'.$u['date']->toAtomString().'</lastmod>' : '')
                .'<changefreq>'.$u['freq'].'</changefreq><priority>'.number_format($u['priority'], 1).'</priority>'
                .($u['image'] ? '<image:image><image:loc>'.$e($u['image']).'</image:loc><image:title>'.$e($u['title'] ?? '').'</image:title></image:image>' : '')
                .'</url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
