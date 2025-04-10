<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingPattern extends Model
{
    use HasFactory;
    
    protected $table = 'training_patterns';
    
    protected $fillable = [
        'input',
        'output',
        'category',
        'context',
        'priority',
        'created_at',
        'updated_at'
    ];
    
    /**
     * Bir kalıbın eğitim verilerini aldık
     * 
     * @param array $data
     * @return int
     */
    public static function insertPattern(array $data)
    {
        return self::insertGetId($data);
    }
    
    /**
     * Son eğitim kalıplarını al
     * 
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getRecentPatterns($limit = 20)
    {
        return self::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Kategoriye göre eğitim kalıplarını al
     * 
     * @param string $category
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPatternsByCategory($category, $limit = 20)
    {
        return self::where('category', $category)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
} 