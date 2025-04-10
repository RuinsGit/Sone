<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'status',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * Chat'e ait mesajları getir
     */
    public function messages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * Chat'in sahibi olan kullanıcıyı getir
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 