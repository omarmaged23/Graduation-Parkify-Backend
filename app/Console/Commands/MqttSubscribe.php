<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Public_Spot;
use App\Models\Reservable_Spot;
use App\Services\MqttService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MqttSubscribe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:subscribe {topic}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe to mqtt topic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $topic = $this->argument('topic');

        $this->info("Listening to MQTT topic: $topic");
        Log::info("MQTT: Starting listener for topic: $topic");

        // Define what happens when message arrives
        $callback = function ($receivedTopic, $message) {
            Log::info("MQTT: Message received on $receivedTopic: $message");
            $this->line("Message: $message");

            // Your action here - this runs instantly when message arrives
            $this->processMessage($receivedTopic, $message);
        };

        try {
            // This keeps running forever and listens for messages
            MQTTService::subscribe($topic, $callback);
        } catch (\Exception $e) {
            Log::error("MQTT Error: " . $e->getMessage());
            $this->error("Connection failed: " . $e->getMessage());
        }
    }
    private function processMessage($topic, $message)
    {
        // Put your instant actions here
        try {
            if ($topic === 'garage/spots/request_init') {
                $this->handleResponse($message);
            } else {
                $this->triggerError($message);
            }

        } catch (\Exception $e) {
            Log::error("Processing error: " . $e->getMessage());
        }
    }

    private function handleResponse($locationName)
    {
        // Your device status logic
        $locationID = Location::where('name', $locationName)->pluck('id')->first();
        $publicLocationSpots = Public_Spot::where([['location_id', $locationID],['is_active',1]])->select('spot_code')->get();
        $reservableLocationSpots = Reservable_Spot::where([['location_id', $locationID],['is_active',1]])->select('spot_code')->get();
        $result = [
            'public' => $publicLocationSpots,
            'reservable' => $reservableLocationSpots,
        ];
        $result = json_encode($result);
        MqttService::publish(sprintf('garage/%s/spots/init',$locationName),$result);
        Log::info("MQTT: Response Completed ");
    }

    private function triggerError($data)
    {
        // Your sensor data logic
        Log::error("Sensor error data received", $data);
        MqttService::publish(sprintf('garage/%s/spots/init',$data),'server is not subscribed to this topic');
    }
}
