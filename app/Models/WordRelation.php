<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordRelation extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'word',
        'related_word',
        'relation_type',
        'strength',
        'context',
        'language',
        'is_verified'
    ];

    protected $casts = [
        'strength' => 'float',
        'is_verified' => 'boolean',
    ];
}
