<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use BelongsToBusiness;

    public const STAGES = ['New', 'Contacted', 'Qualified', 'Site Visit', 'Quote Sent', 'Won', 'Lost'];

    protected $guarded = ['id', 'business_id'];

    protected $hidden = ['plan_path', 'submission_key'];

    protected function casts(): array
    {
        return ['entry_context' => 'array', 'utm' => 'array', 'contact_consent_at' => 'datetime', 'attribution_consent_at' => 'datetime', 'next_follow_up_at' => 'datetime', 'marketing_consent' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class)->latest('id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to')->where('business_id', $this->business_id);
    }

    public function project()
    {
        return $this->hasOne(Project::class);
    }

    public function getReferenceAttribute(): string
    {
        return 'SK-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function getInitialsAttribute(): string
    {
        return mb_strtoupper(collect(explode(' ', $this->name))->filter()->take(2)->map(fn ($v) => mb_substr($v, 0, 1))->join(''));
    }
}
