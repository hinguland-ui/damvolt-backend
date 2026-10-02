<?php

namespace App\Http\Controllers;

use App\Models\LegalPage;
use App\Models\Service;

/**
 * /sitemap.xml — every public page of the WEBSITE (the first FRONTEND_URL), generated from the database,
 * so new services / legal pages appear automatically. robots.txt on the website points search engines here.
 */
class SitemapController extends Controller
{
    public function __invoke()
    {
        $base = rtrim((string) (config('cors.allowed_origins')[0] ?? config('app.url')), '/');

        $urls = [];
        foreach (['/' => 1.0, '/about' => 0.8, '/services' => 0.9, '/industries' => 0.7, '/careers' => 0.5, '/faq' => 0.6, '/contact' => 0.8] as $path => $priority) {
            $urls[] = [$base.$path, null, $priority];
        }
        foreach (Service::live()->get(['slug', 'updated_at']) as $s) {
            $urls[] = [$base.'/services/'.$s->slug, $s->updated_at, 0.8];
        }
        foreach (LegalPage::live()->get(['slug', 'updated_at']) as $p) {
            $urls[] = [$base.'/'.$p->slug, $p->updated_at, 0.3];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($urls as [$loc, $updated, $priority]) {
            $xml .= '  <url><loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'
                .($updated ? '<lastmod>'.$updated->toAtomString().'</lastmod>' : '')
                .'<priority>'.number_format($priority, 1).'</priority></url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
