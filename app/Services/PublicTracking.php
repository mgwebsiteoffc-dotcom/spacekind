<?php

namespace App\Services;

use App\Models\TrackingVersion;
use Illuminate\Http\Request;

class PublicTracking
{
    private bool $loaded = false;

    private ?TrackingVersion $published = null;

    public const SCOPES = ['marketplace' => 'Marketplace', 'studio' => 'Studio profiles', 'business' => 'Business website', 'articles' => 'Public articles', 'reels' => 'Public videos'];

    public function published(): ?TrackingVersion
    {
        if (! $this->loaded) {
            $this->published = TrackingVersion::where('status', 'published')->latest('id')->first();
            $this->loaded = true;
        }

return $this->published;
    }

    public function reset(): void
    {
        $this->loaded = false;
        $this->published = null;
    }

    public function deploymentAllowed(): bool
    {
        return config('spacekind.tracking_allowed', false) && ! config('spacekind.demo') && app()->environment('production');
    }

    public function payload(Request $r): array
    {
        $version = $this->published();
        $s = $version?->settings ?? [];
        $group = app(Discovery::class)->pageGroup($r);
        $base = ['version' => 'public-consent-v1-'.($version?->id ?? 0), 'active' => false, 'tags' => [], 'canonical' => url($r->path() === '/' ? '/' : '/'.$r->path()), 'group' => $group];
        // No tags for authenticated visitors, form/private routes, arbitrary query strings or preview/draft pages.
        if (! $group || $r->user() || $r->getQueryString() || ! $this->deploymentAllowed() || empty($s['enabled']) || ! in_array($group, $s['scopes'] ?? [], true)) {
            return $base;
        }
        $tags = [];
        if (! empty($s['gtm_id']) && preg_match('/^GTM-[A-Z0-9]{4,12}$/', $s['gtm_id'])) {
            $tags[] = ['provider' => 'gtm', 'id' => $s['gtm_id'], 'category' => 'marketing'];
        } else {
            if (! empty($s['ga4_id']) && preg_match('/^G-[A-Z0-9]{5,15}$/', $s['ga4_id'])) {
                $tags[] = ['provider' => 'ga4', 'id' => $s['ga4_id'], 'category' => 'analytics', 'debug' => (bool) ($s['debug_mode'] ?? false)];
            }
            if (! empty($s['meta_pixel_id']) && preg_match('/^[0-9]{5,25}$/', $s['meta_pixel_id'])) {
                $tags[] = ['provider' => 'meta', 'id' => $s['meta_pixel_id'], 'category' => 'marketing'];
            }
        }
        $url = $s['custom_script_url'] ?? '';
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        if ($url && in_array($host, config('spacekind.approved_script_hosts', []), true) && in_array($s['custom_category'] ?? '', ['analytics', 'marketing'], true)) {
            $tags[] = ['provider' => 'custom', 'url' => $url, 'category' => $s['custom_category']];
        }

        return array_merge($base, ['active' => count($tags) > 0, 'tags' => $tags]);
    }

    public function csp(Request $r): array
    {
        $result = ['script' => [], 'connect' => [], 'image' => []];
        $payload = $this->payload($r);
        if (! $payload['active']) {
            return $result;
        }
        foreach ($payload['tags'] as $tag) {
            if (in_array($tag['provider'], ['ga4', 'gtm'])) {
                $result['script'][] = 'https://www.googletagmanager.com';
                $result['connect'] = array_merge($result['connect'], ['https://*.google-analytics.com', 'https://analytics.google.com', 'https://www.googletagmanager.com']);
                $result['image'][] = 'https://*.google-analytics.com';
            }
            if (in_array($tag['provider'], ['meta', 'gtm'])) {
                $result['script'][] = 'https://connect.facebook.net';
                $result['connect'][] = 'https://www.facebook.com';
                $result['image'][] = 'https://www.facebook.com';
            }
            if ($tag['provider'] === 'custom') {
                $origin = 'https://'.parse_url($tag['url'], PHP_URL_HOST);
                $result['script'][] = $origin;
                $result['connect'][] = $origin;
                $result['image'][] = $origin;
            }
        }

        return array_map(fn ($a) => array_values(array_unique($a)), $result);
    }
}
