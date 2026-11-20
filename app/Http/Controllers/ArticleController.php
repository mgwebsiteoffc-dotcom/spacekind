<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Services\ArticleWorkflow;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    public function index()
    {
        return $this->listing(false);
    }

    public function adminIndex()
    {
        return $this->listing(true);
    }

    private function listing(bool $admin)
    {
        $q = $admin ? BlogPost::withoutGlobalScope('business') : BlogPost::query();
        $filter = request()->validate(['filter' => ['nullable', Rule::in(['all', 'submitted', 'platform', 'hidden'])]])['filter'] ?? 'all';
        if ($filter === 'submitted') {
            $q->whereHas('latestRevision', fn ($q) => $q->where('status', 'submitted'));
        }
        if ($filter === 'platform' && $admin) {
            $q->whereNull('business_id');
        }
        if ($filter === 'hidden') {
            $q->where(fn ($q) => $q->where('is_visible', false)->orWhereNotNull('archived_at'));
        }

        return view('content.manage-articles', ['posts' => $q->with(['latestRevision', 'publicRevision', 'business'])->latest('updated_at')->latest('id')->paginate(12)->withQueryString(), 'platform' => $admin, 'filter' => $filter]);
    }

    public function create(ArticleWorkflow $workflow)
    {
        return $this->editor(null, $workflow);
    }

    public function edit(BlogPost $post, ArticleWorkflow $workflow)
    {
        return $this->editor($post, $workflow);
    }

    public function adminEdit(int $article, ArticleWorkflow $workflow)
    {
        return $this->editor(BlogPost::withoutGlobalScope('business')->whereNull('business_id')->findOrFail($article), $workflow);
    }

    private function editor(?BlogPost $post, ArticleWorkflow $workflow)
    {
        $platform = request()->is('admin/*');

        return view('content.article-editor', ['post' => $post, 'revision' => $post?->latestRevision, 'authors' => $workflow->authors($post), 'categories' => ArticleWorkflow::CATEGORIES, 'platform' => $platform]);
    }

    public function store(Request $r, ArticleWorkflow $workflow)
    {
        $post = $workflow->save($r);

        return redirect($this->prefix().'/'.$post->id.'/edit')->with('success', 'Article revision saved. Drafts and unapproved changes are never public.');
    }

    public function update(Request $r, BlogPost $post, ArticleWorkflow $workflow)
    {
        $workflow->save($r, $post);

        return back()->with('success', 'A new revision was saved. Any previously approved version remains unchanged until a new revision is approved.');
    }

    public function adminUpdate(Request $r, int $article, ArticleWorkflow $workflow)
    {
        $post = BlogPost::withoutGlobalScope('business')->whereNull('business_id')->findOrFail($article);
        $workflow->save($r, $post);

        return back()->with('success', 'Platform revision saved. Future-dated approved articles appear only when due.');
    }

    public function preview(BlogPost $post)
    {
        return $this->showPreview($post);
    }

    public function adminPreview(int $article)
    {
        return $this->showPreview(BlogPost::withoutGlobalScope('business')->findOrFail($article));
    }

    private function showPreview(BlogPost $post)
    {
        $revision = $post->revisions()->when(request()->filled('revision'), fn ($q) => $q->whereKey(request()->validate(['revision' => 'required|integer|min:1'])['revision']))->latest('id')->firstOrFail();

        return response()->view('public.article', ['post' => $post, 'revision' => $revision, 'preview' => true, 'related' => collect()])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function archive(BlogPost $post)
    {
        $post->forceFill(['is_visible' => false, 'archived_at' => now()])->save();
        Audit::record('article.archived', $post);

        return redirect('/workspace/blog')->with('success', 'Article archived and removed from public discovery. History is retained.');
    }

    public function review(Request $r, int $article, int $revision, ArticleWorkflow $workflow)
    {
        $v = $r->validate(['decision' => ['required', Rule::in(['approve', 'changes'])], 'review_note' => 'nullable|required_if:decision,changes|string|max:500', 'publish_at' => 'nullable|date']);
        $post = BlogPost::withoutGlobalScope('business')->whereNotNull('business_id')->findOrFail($article);
        $rev = $post->revisions()->findOrFail($revision);
        $workflow->review($post, $rev, $v);

        return back()->with('success', $v['decision'] === 'approve' ? 'Revision approved. The publication date controls when it becomes public.' : 'Changes requested with your review note.');
    }

    public function unpublish(Request $r, int $article)
    {
        $v = $r->validate(['reason' => 'required|string|min:5|max:500']);
        $post = BlogPost::withoutGlobalScope('business')->findOrFail($article);
        $post->forceFill(['is_visible' => false])->save();
        Audit::record('admin.article_unpublished', $post, ['reason' => $v['reason']]);

        return back()->with('success', 'Article unpublished. A later explicit approval/publication is needed to restore it.');
    }

    private function prefix(): string
    {
        return request()->is('admin/*') ? '/admin/blog' : '/workspace/blog';
    }
}
