<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentParticipant extends Model
{
    protected $fillable = ['document_id', 'user_id', 'last_active_at', 'cursor_color'];
    
    protected $casts = [
        'last_active_at' => 'datetime',
    ];
    
    public function document()
    {
        return $this->belongsTo(document::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}