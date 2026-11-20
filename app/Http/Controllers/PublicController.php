<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnquiryRequest;
use App\Models\BlogPost;
use App\Models\Business;
use App\Models\GalleryImage;
use App\Models\Reel;
use App\Models\Testimonial;
use App\Services\Discovery;
use App\Services\LeadWorkflow;
use App\Services\PublicEntryContext;
use App\Support\CampaignLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PublicController extends Controller
{
    public function index(Request $r)
    {
        $filters = $r->validate(['city' => ['nullable', Rule::in(config('spacekind.cities'))], 'service' => ['nullable', Rule::in(array_keys(config('spacekind.services')))], 'budget' => ['nullable', Rule::in(['10', '20', '35', '50'])], 'q' => 'nullable|string|max:80', 'sort' => ['nullable', Rule::in(['recommended', 'budget', 'name'])]]);
        $q = Business::published()->with(['galleryImages' => fn ($q) => $q->withoutGlobalScope('business')->where('is_visible', true)->where('kind', '!=', 'inspiration')->orderBy('position')->orderBy('id')]);
        if ($r->filled('city')) {
            $q->where('city', $filters['city']);
        }
        if ($r->filled('service')) {
            $q->where('service', $filters['service']);
        }
        if ($r->filled('q')) {
            $q->where(function ($q) use ($filters) {
                $needle = '%'.addcslashes($filters['q'], '%_\\').'%';
                $q->where('name', 'like', $needle)->orWhere('bio', 'like', $needle)->orWhere('locality', 'like', $needle);
            });
        }
        if ($r->filled('budget')) {
            [$lo,$hi] = ['10' => [0, 10], '20' => [10, 20], '35' => [20, 35], '50' => [35, 100000]][$filters['budget']];
            $q->where('budget_min', '<=', $hi)->where('budget_max', '>=', $lo);
        }
        match ($filters['sort'] ?? 'recommended') {
            'budget' => $q->orderBy('budget_min'), 'name' => $q->orderBy('name'), default => $q->orderBy('id')
        };

        $studios = $q->paginate(12)->withQueryString();
        $cities = Business::published()->whereIn('city', config('spacekind.cities'))->groupBy('city')->orderBy('city')->selectRaw('city, COUNT(*) as listings')->pluck('listings', 'city');
        $guides = config('project_guides');
        $answers = [];
        foreach ($guides as $guide) {
            $question = array_key_first($guide['answers']);
            $answers[$question] = $guide['answers'][$question];
        }
        $discovery = app(Discovery::class);
        $schema = $discovery->website();
        $schema['@graph'][] = $discovery->faqSchema($answers, url('/').'#questions');

        return view('public.home', compact('studios', 'cities', 'guides', 'answers', 'filters', 'schema') + [
            'clientVoices' => Testimonial::withoutGlobalScope('business')->where('is_visible', true)->where('show_on_home', true)
                ->whereNotNull('permission_confirmed_at')->whereHas('business', fn ($q) => $q->published())
                ->with('business:id,name,city,slug')->latest('id')->limit(3)->get(),
            'inspiration' => DB::table('inspiration_edits')->orderBy('position')->limit(3)->get(),
            'journalPosts' => BlogPost::withoutGlobalScope('business')->publiclyVisible()->with(['publicRevision', 'business'])->latest('visible_from')->limit(3)->get(),
            'featuredReels' => Reel::withoutGlobalScope('business')->publiclyVisible()->with('business')->orderBy('position')->orderByDesc('id')->limit(12)->get(),
        ]);
    }

    public function inspiration(Discovery $discovery)
    {
        return view('public.inspiration', [
            'edits' => DB::table('inspiration_edits')->orderByDesc('id')->get(),
            'images' => GalleryImage::withoutGlobalScope('business')->where('kind', 'inspiration')->where('is_visible', true)->whereNotNull('rights_confirmed_at')->whereHas('business', fn ($q) => $q->published())->with('business')->latest('id')->paginate(12, ['*'], 'images')->withQueryString(),
            'films' => Reel::withoutGlobalScope('business')->publiclyVisible()->whereIn('kind', ['concept','render'])->with('business')->latest('id')->paginate(6, ['*'], 'films')->withQueryString(),
            'schema' => ['@context' => 'https://schema.org'] + $discovery->breadcrumbs(['Spacekind' => '/', 'Inspiration' => '/inspiration']),
        ]);
    }

    public function guide(string $slug, Discovery $discovery)
    {
        $guides = config('project_guides');
        abort_unless(isset($guides[$slug]), 404);
        $guide = $guides[$slug];
        $schema = ['@context' => 'https://schema.org', '@graph' => [
            $discovery->faqSchema($guide['answers'], url('/guides/'.$slug).'#questions'),
            $discovery->breadcrumbs(['Spacekind' => '/', $guide['title'] => '/guides/'.$slug]),
        ]];

        return view('public.guide', compact('guide', 'guides', 'slug', 'schema'));
    }

    public function business(Discovery $discovery)
    {
        $answers = config('studio_questions');
        $schema = ['@context' => 'https://schema.org'] + $discovery->faqSchema($answers, url('/for-businesses').'#questions');

        return view('public.for-businesses', compact('answers', 'schema'));
    }

    public function profile(Business $business)
    {
        $this->published($business);
        $gallery = GalleryImage::withoutGlobalScope('business')->where('business_id', $business->id)->where('is_visible', true)->orderBy('position')->orderBy('id')->get();

        $inspirations = $gallery->where('kind', 'inspiration')->values();
        $gallery = $gallery->where('kind', '!=', 'inspiration')->values();
        $testimonials = Testimonial::withoutGlobalScope('business')->where('business_id', $business->id)->where('is_visible', true)->latest('id')->get();

        $articles = BlogPost::withoutGlobalScope('business')->where('business_id', $business->id)->publiclyVisible()->with(['publicRevision', 'business'])->latest('visible_from')->limit(4)->get();
        $reels = Reel::withoutGlobalScope('business')->where('business_id', $business->id)->publiclyVisible()->with('business')->orderBy('position')->limit(4)->get();
        $entryContext = app(PublicEntryContext::class)->resolve($business, request()->query('entry_context'));

        return view('public.profile', compact('business', 'gallery', 'inspirations', 'testimonials', 'articles', 'reels', 'entryContext') + ['schema' => app(Discovery::class)->businessSchema($business)]);
    }

    public function enquiry(Request $r, Business $business)
    {
        $this->published($business);
        $utm = CampaignLabels::fromQuery($r);
        $entryContext = app(PublicEntryContext::class)->resolve($business, $r->query('entry_context'));

        return view('public.enquire', compact('business', 'utm', 'entryContext'));
    }

    public function submit(EnquiryRequest $r, Business $business, LeadWorkflow $workflow)
    {
        $lead = $workflow->capturePublic($business, $r->validated(), $r->file('plan'));
        $r->session()->flash('enquiry_reference', $lead->reference);

        return redirect('/studios/'.$business->slug.'/enquire/thanks');
    }

    public function thanks(Business $business)
    {
        $this->published($business);

        return view('public.thanks', compact('business'));
    }

    public function media(Request $r, int $image)
    {
        $image = GalleryImage::withoutGlobalScope('business')->with('business')->findOrFail($image);
        $published = $image->is_visible && $image->business->approval_status === 'approved' && $image->business->account_status === 'pilot';
        $owner = $r->user()?->is_active && $r->user()?->business_id === $image->business_id && $image->business->account_status === 'pilot';
        abort_unless($published || $owner, 404);
        $path = $image->disk === 'public-assets' ? public_path($image->path) : Storage::disk('local')->path($image->path);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Cache-Control' => $published ? 'public, max-age=300' : 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function privacy()
    {
        return view('public.privacy');
    }

    public function robots(Discovery $discovery)
    {
        $text = "User-agent: *\n";
        if (! $discovery->canIndex()) {
            $text .= "Disallow: /\n";
        } else {
            $text .= "Disallow: /workspace\nDisallow: /admin\nDisallow: /api\nDisallow: /login\nDisallow: /register\nDisallow: /article-media\nDisallow: /reel-media\nDisallow: /media\nDisallow: /studios/*/enquire\nSitemap: ".url('/sitemap.xml')."\n";
        }

        return response($text, 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(Discovery $discovery)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        if ($discovery->canIndex()) {
            $urls = [url('/'), url('/for-businesses'), url('/inspiration'), url('/blog'), url('/reels')];
            foreach (array_keys(config('project_guides')) as $slug) {
                $urls[] = url('/guides/'.$slug);
            }
            foreach (Business::published()->get() as $b) {
                $urls[] = url('/studios/'.$b->slug);
            }
            foreach (BlogPost::withoutGlobalScope('business')->publiclyVisible()->with(['publicRevision', 'business'])->get() as $p) {
                $urls[] = url($p->public_url);
            }
            foreach (Reel::withoutGlobalScope('business')->publiclyVisible()->get() as $v) {
                $urls[] = url($v->public_url);
            }
            foreach (array_unique($urls) as $url) {
                $xml .= '<url><loc>'.htmlspecialchars($url, ENT_XML1, 'UTF-8').'</loc></url>';
            }
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }

    private function published(Business $business): void
    {
        abort_unless($business->approval_status === 'approved' && $business->account_status === 'pilot', 404);
    }
}
