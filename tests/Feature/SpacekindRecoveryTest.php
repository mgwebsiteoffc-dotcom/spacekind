<?php

namespace Tests\Feature;

use App\Models\PlatformBillingSetting;
use App\Models\SiteSetting;
use App\Models\TrackingVersion;
use App\Models\User;
use App\Services\Discovery;
use App\Services\PreviewState;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpacekindRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function snapshotter(): PreviewState
    {
        $this->assertSame('spacekind_test', DB::connection()->getDatabaseName());
        $path = storage_path('framework/testing/recovery-'.Str::uuid().'.json');

        // Exercise the actual serializer/restorer, never the real demo's private snapshot.
        return new class($path) extends PreviewState
        {
            public function __construct(private string $testPath) {}

            public function enabled(): bool
            {
                return true;
            }

            public function path(): string
            {
                return $this->testPath;
            }
        };
    }

    public function test_format_three_round_trips_all_tables_with_foreign_keys_enabled(): void
    {
        config(['spacekind.demo' => true]);
        $this->seed(DatabaseSeeder::class);
        SiteSetting::create(['seo' => Discovery::DEFAULTS]);
        TrackingVersion::create(['user_id' => User::where('role', 'super_admin')->value('id'), 'status' => 'draft', 'note' => 'Synthetic recovery test only', 'settings' => ['enabled' => false, 'scopes' => []]]);
        $service = $this->snapshotter();
        try {
            $service->save();
            $saved = json_decode(file_get_contents($service->path()), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(3, $saved['format']);
            $this->assertCount(26, $saved['tables']);
            $this->assertSame(0600, fileperms($service->path()) & 0777);
            foreach (['businesses', 'blog_posts', 'blog_revisions', 'reels', 'tracking_versions', 'site_settings', 'leads', 'clients', 'projects'] as $table) {
                $this->assertNotEmpty($saved['tables'][$table]);
            }
            $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
            foreach (array_reverse(array_keys($saved['tables'])) as $table) {
                DB::table($table)->delete();
            }
            $this->assertSame(array_sum(array_map('count', $saved['tables'])), $service->restore());
            foreach ($saved['tables'] as $table => $rows) {
                $actual = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
                $this->assertSame($rows, $actual, $table);
            }
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Recovery requires empty application tables');
            $service->restore();
        } finally {
            @unlink($service->path());
            @unlink($service->path().'.lock');
        }
    }

    public function test_legacy_format_one_restores_foundation_without_fabricating_content(): void
    {
        config(['spacekind.demo' => true]);
        $this->seed(DatabaseSeeder::class);
        $service = $this->snapshotter();
        try {
            $service->save();
            $saved = json_decode(file_get_contents($service->path()), true, 512, JSON_THROW_ON_ERROR);
            $legacy = $saved;
            $legacy['format'] = 1;
            $legacy['tables'] = array_intersect_key($saved['tables'], array_flip(['businesses', 'users', 'subscriptions', 'leads', 'lead_activities', 'clients', 'projects', 'gallery_images', 'audit_events']));
            file_put_contents($service->path(), json_encode($legacy, JSON_THROW_ON_ERROR));
            foreach (array_reverse(array_keys($saved['tables'])) as $table) {
                DB::table($table)->delete();
            }
            $this->assertSame(array_sum(array_map('count', $legacy['tables'])), $service->restore());
            $this->assertDatabaseCount('blog_posts', 0);
            $this->assertDatabaseCount('reels', 0);
            $this->assertDatabaseCount('leads', count($legacy['tables']['leads']));
        } finally {
            @unlink($service->path());
            @unlink($service->path().'.lock');
        }
    }

    public function test_legacy_format_two_restores_content_without_fabricating_financial_rows(): void
    {
        config(['spacekind.demo' => true]);
        $this->seed(DatabaseSeeder::class);
        $service = $this->snapshotter();
        try {
            $service->save();
            $saved = json_decode(file_get_contents($service->path()), true, 512, JSON_THROW_ON_ERROR);
            $legacy = $saved;
            $legacy['format'] = 2;
            $legacy['tables'] = array_intersect_key($saved['tables'], array_flip(['businesses', 'users', 'subscriptions', 'leads', 'lead_activities', 'clients', 'projects', 'gallery_images', 'blog_posts', 'blog_revisions', 'reels', 'campaign_links', 'site_settings', 'tracking_versions', 'audit_events']));
            file_put_contents($service->path(), json_encode($legacy, JSON_THROW_ON_ERROR));
            foreach (array_reverse(array_keys($saved['tables'])) as $table) {
                DB::table($table)->delete();
            }$this->assertSame(array_sum(array_map('count', $legacy['tables'])), $service->restore());
            $this->assertDatabaseCount('blog_posts', count($legacy['tables']['blog_posts']));
            $this->assertDatabaseCount('financial_documents', 0);
            $this->assertDatabaseCount('customer_payments', 0);
        } finally {
            @unlink($service->path());
            @unlink($service->path().'.lock');
        }
    }

    public function test_recovery_refuses_an_occupied_financial_singleton_even_when_foundation_is_empty(): void
    {
        PlatformBillingSetting::create(['id' => 1, 'data' => ['purpose' => 'Synthetic occupied guard']]);
        $service = $this->snapshotter();
        if (! is_dir(dirname($service->path()))) {
            mkdir(dirname($service->path()), 0700, true);
        }file_put_contents($service->path(), '{}');
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('empty application tables');
            $service->restore();
        } finally {
            @unlink($service->path());
        }
    }

    public function test_unknown_recovery_format_is_rejected_before_any_insert(): void
    {
        $service = $this->snapshotter();
        if (! is_dir(dirname($service->path()))) {
            mkdir(dirname($service->path()), 0700, true);
        }
        try {
            file_put_contents($service->path(), json_encode(['format' => 2, 'tables' => ['businesses' => []]], JSON_THROW_ON_ERROR));
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unrecognised recovery format');
            $service->restore();
        } finally {
            $this->assertDatabaseCount('businesses', 0);
            @unlink($service->path());
        }
    }
}
