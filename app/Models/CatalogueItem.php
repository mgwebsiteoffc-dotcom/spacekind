<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class CatalogueItem extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'rate_minor' => 'integer', 'tax_bps' => 'integer'];
    }
}
