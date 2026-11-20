<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\Request;

class ProjectCapture
{
    public const DEFAULTS = ['entry' => false, 'exit' => false, 'floating' => true, 'delay' => 12, 'heading' => 'Find a studio for your space', 'button' => 'Plan my project'];

    private ?array $settings = null;

    public function settings(): array
    {
        return $this->settings ??= array_replace(self::DEFAULTS, SiteSetting::find(1)?->capture ?? []);
    }

    public function enabled(): bool
    {
        $s = $this->settings();

        return $s['entry'] || $s['exit'] || $s['floating'];
    }

    public function embedsForm(Request $r): bool
    {
        if ($r->user() || ! $r->routeIs('home', 'inspiration', 'reels')) {
            return false;
        }
        $settings = $this->settings();

        return $settings['entry'] || $settings['exit'];
    }

    public function floatingLink(Request $r): bool
    {
        return ! $r->user() && $r->routeIs('home', 'inspiration', 'reels', 'project.guide') && $this->settings()['floating'];
    }
}
