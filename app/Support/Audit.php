<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(string $action, ?Model $subject = null, array $changes = []): void
    {
        $user = auth()->user();
        if ($user && ! $user->isAdmin() && $user->business_id !== app(Tenant::class)->id()) {
            $user = null;
        }
        AuditEvent::create(['business_id' => app(Tenant::class)->id() ?? ($subject?->getTable() === 'businesses' ? $subject->getKey() : $subject?->getAttribute('business_id')), 'user_id' => $user?->id, 'action' => $action, 'subject' => $subject ? class_basename($subject).':'.$subject->getKey() : null, 'changes' => $changes ?: null]);
    }
}
