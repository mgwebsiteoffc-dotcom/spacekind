<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\BlogRevision;
use App\Models\User;
use App\Support\Audit;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ArticleWorkflow
{
    public const CATEGORIES = ['Planning & budgets', 'Materials & details', 'Studio stories', 'For studios'];

    public function authors(?BlogPost $post = null): array
    {
        $business = app(Tenant::class)->business();
        if ($business) {
            $options = ['business' => 'Business name · '.$business->name];
            foreach ($business->users()->where('is_active', true)->orderBy('name')->get() as $u) {
                $options['user:'.$u->id] = $u->name.' · '.$business->name;
            }

            return $options;
        }

        return ['editorial' => 'Spacekind editorial', 'user:'.auth()->id() => auth()->user()->name.' · Named author'];
    }

    public function save(Request $r, ?BlogPost $post = null): BlogPost
    {
        $platform = auth()->user()->isAdmin();
        abort_if($platform && $post?->business_id, 403);
        $rules = [
            'title' => 'required|string|max:140', 'excerpt' => 'required|string|min:30|max:400', 'body' => 'required|string|min:80|max:50000',
            'faqs' => 'sometimes|array|max:12', 'faqs.*' => 'array:question,answer',
            'faqs.*.question' => 'nullable|required_with:faqs.*.answer|string|max:240',
            'faqs.*.answer' => 'nullable|required_with:faqs.*.question|string|max:2000', 'faq_editor' => 'sometimes|accepted',
            'category' => ['required', Rule::in(self::CATEGORIES)], 'tags' => 'nullable|string|max:160',
            'byline' => ['required', Rule::in(array_keys($this->authors($post)))],
            'seo_title' => 'nullable|string|max:70', 'seo_description' => 'nullable|string|max:170',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072|dimensions:min_width=300,min_height=200,max_width=6000,max_height=6000',
            'cover_alt' => 'nullable|required_with:cover|string|max:180', 'rights' => 'accepted',
            'intent' => ['required', Rule::in($platform ? ['draft', 'publish', 'schedule'] : ['draft', 'submit', 'schedule'])],
            'publish_at' => $r->input('intent') === 'schedule' ? 'required|date|after:now' : 'nullable|date', 'base_revision_id' => 'nullable|integer|min:1',
        ];
        if (! $post) {
            $rules['slug'] = ['required', 'string', 'min:3', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:blog_posts,slug'];
        }
        if (! $post && ! $r->filled('slug')) {
            $r->merge(['slug' => Str::slug(Str::limit((string) $r->input('title'), 145, '')) ?: 'story-'.Str::lower(Str::random(8))]);
        }
        $v = $r->validate($rules);
        $path = $r->file('cover')?->store('articles/'.(app(Tenant::class)->id() ?? 'platform'), 'local');
        try {
            return DB::transaction(function () use ($post, $v, $path, $platform) {
                if ($post) {
                    $post = BlogPost::withoutGlobalScope('business')->lockForUpdate()->findOrFail($post->id);
                    $latest = $post->revisions()->latest('id')->first();
                    if ((int) ($v['base_revision_id'] ?? 0) !== (int) $latest?->id) {
                        throw ValidationException::withMessages(['title' => 'A newer revision exists. Reload this editor before saving. Your changes were not published.']);
                    }
                } else {
                    $post = new BlogPost(['slug' => $v['slug']]);
                    $post->forceFill(['created_by' => auth()->id()])->save();
                    $latest = null;
                }
                $fields = Arr::only($v, ['title', 'excerpt', 'body', 'category', 'cover_alt', 'seo_title', 'seo_description']);
                $fields['faqs'] = isset($v['faq_editor']) || isset($v['faqs'])
                    ? array_values(array_filter($v['faqs'] ?? [], fn ($row) => filled($row['question'] ?? null)))
                    : ($latest?->faqs ?? []);
                $fields['cover_path'] = $path ?? $latest?->cover_path;
                $fields['cover_alt'] = $v['cover_alt'] ?? $latest?->cover_alt;
                $fields['tags'] = collect(explode(',', $v['tags'] ?? ''))->map(fn ($s) => trim($s))->filter()->unique()->take(5)->values()->all();
                $fields['created_by'] = auth()->id();
                $fields['byline_mode'] = str_starts_with($v['byline'], 'user:') ? 'person' : 'business';
                $fields['author_id'] = $fields['byline_mode'] === 'person' ? (int) substr($v['byline'], 5) : null;
                $fields['author_name'] = $fields['author_id'] ? User::findOrFail($fields['author_id'])->name : $post->publisher_name;
                $fields['status'] = $v['intent'] === 'draft' ? 'draft' : ($platform ? 'approved' : 'submitted');
                $fields['publish_at'] = ! empty($v['publish_at']) ? Carbon::parse($v['publish_at']) : ($fields['status'] === 'approved' ? now() : null);
                if ($fields['status'] === 'approved') {
                    $fields['reviewed_by'] = auth()->id();
                    $fields['reviewed_at'] = now();
                }
                $revision = $post->revisions()->create($fields);
                if ($fields['status'] === 'approved') {
                    $this->makeVisible($post, $revision);
                }
                Audit::record('article.revision_'.$fields['status'], $post, ['revision_id' => $revision->id, 'rights_confirmed' => true]);

                return $post;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }
    }

    public function review(BlogPost $post, BlogRevision $revision, array $v): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        DB::transaction(function () use ($post, $revision, $v) {
            $post = BlogPost::withoutGlobalScope('business')->lockForUpdate()->findOrFail($post->id);
            $revision = $post->revisions()->findOrFail($revision->id);
            if ($revision->status !== 'submitted' || (int) $post->revisions()->max('id') !== $revision->id) {
                throw ValidationException::withMessages(['decision' => 'This submission is no longer current. Refresh the review list.']);
            }
            $attrs = ['status' => $v['decision'] === 'approve' ? 'approved' : 'changes', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_note' => $v['review_note'] ?? null];
            if ($attrs['status'] === 'approved') {
                $attrs['publish_at'] = ! empty($v['publish_at']) ? Carbon::parse($v['publish_at']) : ($revision->publish_at ?? now());
            }
            $revision->update($attrs);
            if ($attrs['status'] === 'approved') {
                $this->makeVisible($post, $revision);
            }
            Audit::record('admin.article_'.$attrs['status'], $post, ['revision_id' => $revision->id, 'reason' => $attrs['review_note']]);
        });
    }

    private function makeVisible(BlogPost $post, BlogRevision $revision): void
    {
        $from = $post->is_visible && ! $post->archived_at && $post->visible_from ? min($post->visible_from, $revision->publish_at) : $revision->publish_at;
        $post->forceFill(['is_visible' => true, 'archived_at' => null, 'visible_from' => $from])->save();
    }
}
