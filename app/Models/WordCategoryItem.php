<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordCategoryItem extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'word',
        'nov',
        'category_id',
        'strength',
        'usage_count',
        'context',
        'examples',
        'language',
        'is_verified'
    ];

    protected $casts = [
        'category_id' => 'integer',
        'strength' => 'float',
        'usage_count' => 'integer',
        'examples' => 'array',
        'is_verified' => 'boolean',
    ];

    /**
     * Bu kelimenin ait olduğu kategori
     */
    public function category()
    {
        return $this->belongsTo(WordCategory::class, 'category_id');
    }

    /**
     * İlişkili kelime tanımını getir
     */
    public function definition()
    {
        return $this->hasOne(WordDefinition::class, 'word', 'word');
    }

    /**
     * İlişkili kelime ilişkilerini getir
     */
    public function relations()
    {
        return $this->hasMany(WordRelation::class, 'word', 'word');
    }
} 