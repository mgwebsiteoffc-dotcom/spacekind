<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    public const PURPOSES = ['welcome' => 'Studio welcome', 'enquiry' => 'New project enquiry', 'invoice' => 'Invoice notification'];

    protected $fillable = ['purpose', 'subject', 'body'];
}
