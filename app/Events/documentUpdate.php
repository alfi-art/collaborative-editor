<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class DocumentUpdate implements ShouldBroadcastNow
{
    public $documentId;
    public $content;
    public $userId;
    public $userName;
    
    public function __construct($documentId, $content, $userId, $userName)
    {
        $this->documentId = $documentId;
        $this->content = $content;
        $this->userId = $userId;
        $this->userName = $userName;
    }
    
    public function broadcastOn()
    {
        return new Channel('document.' . $this->documentId);
    }
}