<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Business;
use App\Models\Reel;
use App\Services\ArticleWorkflow;
use App\Services\Discovery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PublicContentController extends Controller
{
    public function blog(Request $r)
    {
        $v = $r->validate(['category' => ['nullable', Rule::in(ArticleWorkflow::CATEGORIES)], 'publisher' => ['nullable', Rule::in(['all', 'platform', 'studios'])]]);
        $q = BlogPost::withoutGlobalScope('business')->publiclyVisible()->with(['publicRevision', 'business']);
        if (($v['publisher'] ?? '') === 'platform') {
            $q->whereNull('business_id');
        }
        if (($v['publisher'] ?? '') === 'studios') {
            $q->whereNotNull('business_id');
        }
        if (! empty($v['category'])) {
            $q->whereHas('publicRevision', fn ($q) => $q->where('category', $v['category']));
        }

        return view('public.blog', ['posts' => $q->orderByDesc('visible_from')->latest('id')->paginate(9)->withQueryString(), 'filters' => $v, 'categories' => ArticleWorkflow::CATEGORIES]);
    }

    public function platformArticle(string $slug)
    {
        return $this->article(BlogPost::withoutGlobalScope('business')->whereNull('business_id')->where('slug', $slug)->publiclyVisible()->with(['publicRevision', 'business'])->firstOrFail());
    }

    public function studioArticle(Business $business, string $slug)
    {
        return $this->article(BlogPost::withoutGlobalScope('business')->where('business_id', $business->id)->where('slug', $slug)->publiclyVisible()->with(['publicRevision', 'business'])->firstOrFail());
    }

    private function article(BlogPost $post)
    {
        $related = BlogPost::withoutGlobalScope('business')->publiclyVisible()->where('id', '!=', $post->id)->with(['publicRevision', 'business'])->latest('visible_from')->latest('id')->limit(3)->get();

        return view('public.article', ['post' => $post, 'revision' => $post->publicRevision, 'preview' => false, 'related' => $related, 'schema' => app(Discovery::class)->articleSchema($post)]);
    }

    public function cover(Request $r, int $article, int $revision)
    {
        $post = BlogPost::withoutGlobalScope('business')->with(['publicRevision', 'business'])->findOrFail($article);
        $rev = $post->revisions()->findOrFail($revision);
        $public = BlogPost::withoutGlobalScope('business')->whereKey($post->id)->publiclyVisible()->exists() && $post->publicRevision?->id === $rev->id;
        $allowed = $r->user()?->is_active && ($r->user()->isAdmin() || $r->user()->business_id === $post->business_id && $post->business?->account_status === 'pilot');
        abort_unless($public || $allowed, 404);
        abort_unless($rev->cover_path && Storage::disk('local')->exists($rev->cover_path), 404);

        return response()->file(Storage::disk('local')->path($rev->cover_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function reels()
    {
        return view('public.reels', ['reels' => Reel::withoutGlobalScope('business')->publiclyVisible()->with('business')->orderBy('position')->latest('id')->paginate(12)]);
    }

    public function reel(int $video)
    {
        $reel = Reel::withoutGlobalScope('business')->publiclyVisible()->with('business')->findOrFail($video);
        $q = Reel::withoutGlobalScope('business')->publiclyVisible();

        return view('public.reel', ['reel' => $reel, 'previous' => (clone $q)->where('id', '<', $reel->id)->max('id'), 'next' => (clone $q)->where('id', '>', $reel->id)->min('id'), 'preview' => false]);
    }

    public function previewReel(Reel $reel)
    {
        return response()->view('public.reel', ['reel' => $reel->load('business'), 'previous' => null, 'next' => null, 'preview' => true])->header('Cache-Control', 'private, no-store');
    }

    public function reelMedia(Request $r, int $video, string $kind)
    {
        abort_unless(in_array($kind, ['video', 'cover']), 404);
        $reel = Reel::withoutGlobalScope('business')->with('business')->findOrFail($video);
        $public = Reel::withoutGlobalScope('business')->whereKey($video)->publiclyVisible()->exists();
        $owner = $r->user()?->is_active && ($r->user()->isAdmin() || $r->user()->business_id === $reel->business_id && $reel->business->account_status === 'pilot');
        abort_unless($public || $owner, 404);
        $relative = $kind === 'video' ? $reel->video_path : $reel->cover_path;
        $path = $reel->disk === 'public-assets' ? public_path($relative) : Storage::disk('local')->path($relative);
        abort_unless(is_file($path), 404);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($kind === 'video') {
            $headers['Content-Type'] = $reel->mime;
        }

        return response()->file($path,$headers);
    }
}
