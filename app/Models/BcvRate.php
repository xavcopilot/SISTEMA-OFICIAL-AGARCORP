<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BcvRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'rate_date',
        'rate',
        'source',
        'source_url',
        'fetched_at',
        'payload',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'rate' => 'decimal:6',
        'fetched_at' => 'datetime',
        'payload' => 'array',
    ];
}
