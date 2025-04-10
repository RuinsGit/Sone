<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordDefinition extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'word',
        'definition',
        'examples',
        'source',
        'language',
        'is_verified'
    ];

    protected $casts = [
        'examples' => 'array',
        'is_verified' => 'boolean',
    ];
} 