<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRevision extends Model
{
    protected $fillable = ['document_id', 'user_id', 'content', 'version_number', 'metadata'];
    
    protected $casts = [
        'metadata' => 'array',
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