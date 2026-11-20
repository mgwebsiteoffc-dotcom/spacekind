<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Business;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class Discovery
{
    private ?array $settings = null;

    public const DEFAULTS = [
        'home_title' => 'Spacekind — Find your kind of interior studio',
        'home_description' => 'Discover interior-design studios by city, service and project range. Explore their work and share a qualified project brief with your chosen studio.',
        'business_title' => 'Spacekind for interior-design businesses',
        'business_description' => 'A beautiful studio presence and a connected workspace for qualified enquiries, consistent follow-ups and recorded campaign outcomes.',
        'organisation_description' => 'Spacekind connects people planning interior projects with independent studios, and gives those businesses a connected workspace.',
        'indexing_enabled' => false,
    ];

    public function settings(): array
    {
        return $this->settings ??= array_merge(self::DEFAULTS, SiteSetting::find(1)?->seo ?? []);
    }

    public function reset(): void
    {
        $this->settings = null;
    }

    public function canIndex(): bool
    {
        return ! config('spacekind.demo') && config('spacekind.allow_indexing', false) && (bool) $this->settings()['indexing_enabled'];
    }

    public function pageGroup(Request $r): ?string
    {
        return match ($r->route()?->getName()) {
            'home' => 'marketplace','studio' => 'studio','for-businesses' => 'business','blog','blog.article','studio.article' => 'articles','reels','reels.show' => 'reels',default => null
        };
    }

    public function indexable(Request $r): bool
    {
        return $this->canIndex() && ($this->pageGroup($r) !== null || $r->routeIs('project.guide')) && ! $r->getQueryString();
    }

    public function title(Request $r): string
    {
        return $r->routeIs('for-businesses') ? $this->settings()['business_title'] : $this->settings()['home_title'];
    }

    public function description(Request $r): string
    {
        return $r->routeIs('for-businesses') ? $this->settings()['business_description'] : $this->settings()['home_description'];
    }

    public function organisation(): array
    {
        return ['@type' => 'Organization', '@id' => url('/').'#organisation', 'name' => 'Spacekind', 'url' => url('/'), 'description' => $this->settings()['organisation_description']];
    }

    public function website(): array
    {
        return ['@context' => 'https://schema.org', '@graph' => [$this->organisation(), ['@type' => 'WebSite', '@id' => url('/').'#website', 'name' => 'Spacekind', 'url' => url('/'), 'publisher' => ['@id' => url('/').'#organisation']]]];
    }

    public function faqSchema(array $answers, string $id): array
    {
        $questions = [];
        foreach ($answers as $question => $answer) {
            $questions[] = ['@type' => 'Question', 'name' => $question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]];
        }

        return ['@type' => 'FAQPage', '@id' => $id, 'mainEntity' => $questions];
    }

    public function breadcrumbs(array $items): array
    {
        $rows = [];
        foreach ($items as $name => $path) {
            $rows[] = ['@type' => 'ListItem', 'position' => count($rows) + 1, 'name' => $name, 'item' => url($path)];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $rows];
    }

    public function businessSchema(Business $b): array
    {
        return ['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'ProfessionalService', '@id' => url('/studios/'.$b->slug).'#studio', 'name' => $b->name, 'url' => url('/studios/'.$b->slug), 'description' => $b->bio, 'areaServed' => ['@type' => 'City', 'name' => $b->city]],
            $this->breadcrumbs(['Spacekind' => '/', $b->name => '/studios/'.$b->slug]),
        ]];
    }

    public function articleSchema(BlogPost $p): array
    {
        $v = $p->publicRevision;
        $author = ['@type' => $v->byline_mode === 'person' ? 'Person' : 'Organization', 'name' => $v->author_name];

        $graph = [
            ['@type' => 'BlogPosting', 'headline' => $v->title, 'articleBody' => trim(html_entity_decode(strip_tags($v->rendered_body), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'description' => $v->excerpt, 'mainEntityOfPage' => url($p->public_url), 'url' => url($p->public_url), 'image' => url($v->cover_url), 'datePublished' => $v->publish_at->toIso8601String(), 'dateModified' => max($v->publish_at, $v->updated_at)->toIso8601String(), 'author' => $author, 'publisher' => $this->organisation()],
            $this->breadcrumbs(['Spacekind' => '/', 'The Spacekind journal' => '/blog', $v->title => $p->public_url]),
        ];
        if ($v->faqs) {
            $graph[] = ['@type' => 'FAQPage', '@id' => url($p->public_url).'#faqs', 'mainEntity' => array_map(fn ($faq) => [
                '@type' => 'Question', 'name' => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
            ], $v->faqs)];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }
}
