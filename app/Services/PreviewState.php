<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Development recovery only: MySQL stays the application database, not this file. */
class PreviewState
{
    private const LEGACY_TABLES = ['businesses', 'users', 'subscriptions', 'leads', 'lead_activities', 'clients', 'projects', 'gallery_images', 'audit_events'];

    private const V2_TABLES = ['businesses', 'users', 'subscriptions', 'leads', 'lead_activities', 'clients', 'projects', 'gallery_images', 'blog_posts', 'blog_revisions', 'reels', 'campaign_links', 'site_settings', 'tracking_versions', 'audit_events'];

    private const TABLES = ['businesses', 'users', 'subscriptions', 'leads', 'lead_activities', 'clients', 'projects', 'gallery_images', 'blog_posts', 'blog_revisions', 'reels', 'campaign_links', 'site_settings', 'tracking_versions', 'billing_settings', 'catalogue_items', 'document_sequences', 'financial_documents', 'customer_payments', 'payment_allocations', 'document_shares', 'platform_billing_settings', 'platform_sequences', 'platform_invoices', 'platform_payments', 'audit_events'];

    public function enabled(): bool
    {
        return config('spacekind.demo') && app()->environment('local');
    }

    public function path(): string
    {
        return storage_path('app/private/preview-state.json');
    }

    public function save(): void
    {
        if (! $this->enabled()) {
            return;
        }
        $path = $this->path();
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $lock = fopen($path.'.lock', 'c');
        if (! $lock) {
            return;
        }
        try {
            if (! flock($lock, LOCK_EX)) {
                return;
            }
            $state = DB::transaction(function () {
                $data = [];
                foreach (self::TABLES as $table) {
                    $data[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
                }

                return ['format' => 3, 'purpose' => 'Spacekind local development recovery; contains private studio records', 'saved_at' => now()->toIso8601String(), 'tables' => $data];
            });
            $temporary = $path.'.'.getmypid().'.tmp';
            file_put_contents($temporary, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
            chmod($temporary, 0600);
            rename($temporary, $path);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function restore(): int
    {
        if (! $this->enabled()) {
            throw new \RuntimeException('Recovery is available only in a local, demo-enabled environment.');
        }
        if (! is_file($this->path())) {
            throw new \RuntimeException('No saved development state exists. Use the demo seeder instead.');
        }
        foreach (self::TABLES as $table) {
            if (DB::table($table)->exists()) {
                throw new \RuntimeException('Recovery requires empty application tables. It will not overwrite or merge existing data.');
            }
        }
        $state = json_decode(file_get_contents($this->path()), true, 512, JSON_THROW_ON_ERROR);
        $legacy = ($state['format'] ?? null) === 1 && array_keys($state['tables'] ?? []) === self::LEGACY_TABLES;
        $v2 = ($state['format'] ?? null) === 2 && array_keys($state['tables'] ?? []) === self::V2_TABLES;
        if (! $legacy && ! $v2 && (($state['format'] ?? null) !== 3 || array_keys($state['tables'] ?? []) !== self::TABLES)) {
            throw new \RuntimeException('Unrecognised recovery format.');
        }

        if ($legacy || $v2) {
            $tables = [];
            foreach (self::TABLES as $table) {
                $tables[$table] = $state['tables'][$table] ?? [];
            }$state['tables'] = $tables;
        }

        return DB::transaction(function () use ($state) {
            $count = 0;
            foreach (self::TABLES as $table) {
                foreach (array_chunk($state['tables'][$table], 100) as $rows) {
                    DB::table($table)->insert($rows);
                    $count += count($rows);
                }
            }

            return $count;
        });
    }
}
