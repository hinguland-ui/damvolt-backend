{{--
    "How it looks on Google" preview. Updates live while you type.
    $cfg keys (all optional, CSS selectors are looked up inside the same <form>):
      titleInput, descInput, fallbackTitleInput, fallbackDescInput, slugInput, prefix, path, fallbackTitle, fallbackDesc, homepage
--}}
@php
    $origin = rtrim($frontendUrl ?? config('app.url'), '/');
    $host = preg_replace('#^https?://#', '', $origin);
    $scheme = str_starts_with($origin, 'https') ? 'https://' : 'http://';
@endphp

<div class="serp"
    data-site-name="{{ $site['name'] }}"
    data-site-host="{{ $scheme }}{{ $host }}"
    data-title-input="{{ $cfg['titleInput'] ?? '' }}"
    data-desc-input="{{ $cfg['descInput'] ?? '' }}"
    data-fallback-title-input="{{ $cfg['fallbackTitleInput'] ?? '' }}"
    data-fallback-desc-input="{{ $cfg['fallbackDescInput'] ?? '' }}"
    data-slug-input="{{ $cfg['slugInput'] ?? '' }}"
    data-prefix="{{ $cfg['prefix'] ?? '' }}"
    data-path="{{ $cfg['path'] ?? '' }}"
    data-fallback-title="{{ $cfg['fallbackTitle'] ?? ($cfg['homepage'] ?? false ? $site['name'] : '') }}"
    data-fallback-desc="{{ $cfg['fallbackDesc'] ?? '' }}">
    <div class="serp-head"><i class="bi bi-google"></i> Google preview <small>— how this page can appear in search results</small></div>
    <div class="serp-card">
        <div class="serp-site">
            <span class="serp-fav">@if ($site['favicon']) <img src="{{ $site['favicon'] }}" alt=""> @else <i class="bi bi-globe2"></i> @endif</span>
            <span class="serp-site-text">
                <span class="serp-name"></span>
                <span class="serp-url"></span>
            </span>
        </div>
        <div class="serp-title"></div>
        <div class="serp-desc"></div>
    </div>
</div>
