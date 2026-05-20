<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UserJoined implements ShouldBroadcastNow
{
    public $documentId;
    public $user;
    public $color;

    public function __construct($documentId, $user, $color)
    {
        $this->documentId = $documentId;
        $this->user = $user;
        $this->color = $color;
    }

    public function broadcastOn()
    {
        return new Channel('document.' . $this->documentId);
    }
}