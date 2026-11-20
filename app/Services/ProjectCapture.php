<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\Request;

class ProjectCapture
{
    public const DEFAULTS = ['entry' => false, 'exit' => false, 'floating' => false, 'delay' => 12, 'heading' => 'Find a studio for your space', 'button' => 'Plan my project'];

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

    public function widget(Request $r): bool
    {
        return ! $r->user() && $r->routeIs('home', 'inspiration', 'reels') && $this->enabled();
    }
}
