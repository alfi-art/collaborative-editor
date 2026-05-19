<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CursorMove implements ShouldBroadcastNow
{
    public $documentId;
    public $userId;
    public $userName;
    public $position;
    public $color;
    
    public function __construct($documentId, $userId, $userName, $position, $color)
    {
        $this->documentId = $documentId;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->position = $position;
        $this->color = $color;
    }
    
    public function broadcastOn()
    {
        return new Channel('document.' . $this->documentId);
    }
}