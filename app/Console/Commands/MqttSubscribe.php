<?php

namespace App\Console\Commands;

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
            $data = json_decode($message, true);

            // Example actions based on your topic
            if ($topic === 'device/status') {
                $this->handleDeviceStatus($data);
            } elseif ($topic === 'sensor/data') {
                $this->handleSensorData($data);
            }

        } catch (\Exception $e) {
            Log::error("Processing error: " . $e->getMessage());
        }
    }

    private function handleDeviceStatus($data)
    {
        // Your device status logic
        Log::info("Device status updated", $data);
        // Example: Update database, send notification, etc.
    }

    private function handleSensorData($data)
    {
        // Your sensor data logic
        Log::info("Sensor data received", $data);
        // Example: Check thresholds, store data, etc.
    }
}
