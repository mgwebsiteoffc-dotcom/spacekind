<?php

namespace App\Models;

use App\Support\ArticleMarkup;
use Illuminate\Database\Eloquent\Model;

class BlogRevision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['faqs' => 'array', 'tags' => 'array', 'publish_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function post()
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id')->withoutGlobalScope('business');
    }

    public function getCoverUrlAttribute(): string
    {
        return $this->cover_path ? '/article-media/'.$this->blog_post_id.'/'.$this->id : '/assets/interiors/hero.webp';
    }

    public function getRenderedBodyAttribute(): string
    {
        return ArticleMarkup::render($this->body);
    }

    public function getReadingMinutesAttribute(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($this->rendered_body)) / 200));
    }

    public function getBylineAttribute(): string
    {
        $publisher = $this->post->publisher_name;

        return $this->byline_mode === 'person' && $this->post->business_id ? 'By '.$this->author_name.' · '.$publisher : 'By '.$this->author_name;
    }
}
