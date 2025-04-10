<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordCategory extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'level',
        'usage_count',
        'emotional_context',
        'metadata'
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'level' => 'integer',
        'usage_count' => 'integer',
        'emotional_context' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Bu kategorinin alt kategorileri
     */
    public function children()
    {
        return $this->hasMany(WordCategory::class, 'parent_id');
    }

    /**
     * Bu kategorinin üst kategorisi
     */
    public function parent()
    {
        return $this->belongsTo(WordCategory::class, 'parent_id');
    }

    /**
     * Bu kategoriye ait kelimeler
     */
    public function words()
    {
        return $this->hasMany(WordCategoryItem::class, 'category_id');
    }
} 