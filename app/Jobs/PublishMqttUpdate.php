<?php
namespace App\Jobs;

use App\Services\MqttService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishMqttUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $plate;
    public $location;
    public $enteredAt;

    public function __construct($plate, $location, $enteredAt)
    {
        $this->plate = $plate;
        $this->location = $location;
        $this->enteredAt = $enteredAt;
    }

    public function handle()
    {
        MqttService::publish("parking/spots", [
            'license_plate' => $this->plate,
            'location' => $this->location,
            'entered_at' => $this->enteredAt,
            'exited_at' => null
        ]);
    }
}
