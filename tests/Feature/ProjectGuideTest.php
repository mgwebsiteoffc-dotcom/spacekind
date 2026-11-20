<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_lucknow_finder_matches_city_service_and_overlapping_budget(): void
    {
        foreach ([['fits', 'Lucknow', 8, 16], ['outside', 'Lucknow', 25, 40], ['elsewhere', 'Noida', 8, 16]] as [$slug, $city, $min, $max]) {
            $studio = Business::create(['slug' => $slug, 'name' => 'Studio '.$slug, 'city' => $city, 'service' => 'home', 'budget_min' => $min, 'budget_max' => $max]);
            $studio->forceFill(['approval_status' => 'approved'])->save();
        }
        $this->get('/?city=Lucknow&service=home&budget=20')->assertOk()->assertSee('Studio fits')->assertDontSee('Studio outside')->assertDontSee('Studio elsewhere')->assertSee('Studios that match your brief.');
        $this->get('/')->assertSee(config('spacekind.positioning'));
        $this->get('/for-businesses')->assertSee(config('spacekind.positioning'));
    }

    public function test_guides_have_distinct_metadata_visible_answers_and_matching_json(): void
    {
        foreach (config('project_guides') as $slug => $guide) {
            $url = '/guides/'.$slug;
            $page = $this->get($url)->assertOk()->assertSee('href="'.url($url).'"', false)
                ->assertSee('<h1>'.$guide['title'].'.</h1>', false)->assertSee('Your checklist');
            preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $page->getContent(), $matches);
            $nodes = collect(json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR)['@graph'])->keyBy('@type');
            $this->assertArrayHasKey('BreadcrumbList', $nodes);
            foreach ($nodes['FAQPage']['mainEntity'] as $pair) {
                $page->assertSee('<h3>'.e($pair['name']).'</h3>', false)->assertSee('<p>'.e($pair['acceptedAnswer']['text']).'</p>', false);
            }
            $this->assertCount(count($guide['answers']), $nodes['FAQPage']['mainEntity']);
            $this->get('/')->assertSee('href="'.$url.'"', false);
        }
        $this->get('/guides/not-published')->assertNotFound();
        $this->get('/for-businesses')->assertOk()->assertSee('FAQPage');
    }

    public function test_guides_respect_indexing_gates_and_directory_city_counts_exclude_unpublished_listings(): void
    {
        $url = '/guides/compare-interior-quotes';
        $this->get('/sitemap.xml')->assertDontSee($url);
        $this->get($url)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        config(['spacekind.demo' => false, 'spacekind.allow_indexing' => true]);
        SiteSetting::updateOrCreate(['id' => 1], ['seo' => ['indexing_enabled' => true]]);
        app(\App\Services\Discovery::class)->reset();
        $this->get('/sitemap.xml')->assertSee(url($url), false);
        $this->get($url)->assertHeaderMissing('X-Robots-Tag');
        $this->get($url.'?q=test')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        foreach (['approved', 'pending'] as $status) {
            $studio = Business::create(['slug' => $status, 'name' => $status.' studio', 'city' => 'Noida']);
            $studio->forceFill(['approval_status' => $status])->save();
        }
        $this->get('/')->assertSee('Noida <span>1</span>', false)->assertDontSee('pending studio');
    }
}
