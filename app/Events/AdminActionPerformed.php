<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminActionPerformed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $name;
    public $email;
    public $activity;
    public $role;

    /**
     * @param $name
     * @param $email
     * @param $activity
     * @param $role
     */
    public function __construct($name, $email, $activity, $role)
    {
        $this->name = $name;
        $this->email = $email;
        $this->activity = $activity;
        $this->role = $role;
    }


//    /**
//     * Get the channels the event should broadcast on.
//     *
//     * @return array<int, \Illuminate\Broadcasting\Channel>
//     */
//    public function broadcastOn(): array
//    {
//        return [
//            new PrivateChannel('channel-name'),
//        ];
//    }
}
