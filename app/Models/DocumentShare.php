<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class DocumentShare extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function document()
    {
        return $this->belongsTo(FinancialDocument::class, 'document_id');
    }
}
