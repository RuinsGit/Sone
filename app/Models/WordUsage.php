<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'word',
        'usage_example',
        'context_type',
        'emotion',
        'formality',
        'source',
        'frequency',
        'language',
        'is_verified'
    ];

    protected $casts = [
        'frequency' => 'integer',
        'is_verified' => 'boolean',
    ];
}
