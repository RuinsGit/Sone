<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIData extends Model
{
    protected $table = 'ai_data';
    
    protected $fillable = [
        'word',
        'sentence',
        'context',
        'category',
        'frequency',
        'language',
        'source',
        'confidence',
        'related_words',
        'usage_examples',
        'emotional_context',
        'metadata',
        'created_at',
        'updated_at'
    ];
    
    protected $casts = [
        'frequency' => 'integer',
        'confidence' => 'float',
        'related_words' => 'array',
        'usage_examples' => 'array',
        'emotional_context' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    public function scopeByLanguage($query, $language)
    {
        return $query->where('language', $language);
    }
    
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
    
    public function scopeFrequent($query, $limit = 1000)
    {
        return $query->orderBy('frequency', 'desc')->limit($limit);
    }
} 