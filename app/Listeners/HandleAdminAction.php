<?php

namespace App\Listeners;

use App\Events\AdminActionPerformed;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleAdminAction
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AdminActionPerformed $event): void
    {
            ActivityLog::create([
                'name' => $event->name,
                'email' => $event->email,
                'activity' => $event->activity,
                'role' => $event->role,
            ]);
    }
}
