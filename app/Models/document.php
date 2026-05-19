<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class document extends Model
{
    protected $table = 'documents';
    
    protected $fillable = ['title', 'content', 'owner_id', 'current_version'];
    
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
    
    public function participants()
    {
        return $this->hasMany(DocumentParticipant::class);
    }
    
    public function revisions()
    {
        return $this->hasMany(DocumentRevision::class);
    }
}