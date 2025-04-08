<?php
namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttService
{
//    public static function publish($topic, $message)
//    {
//        try {
//            $mqttBroker = env('MQTT_BROKER', 'broker.hivemq.com');
//            $mqttPort = env('MQTT_PORT', 1883);
//            $clientId = 'laravel_publisher_' . uniqid();
//
//            $mqttClient = new MqttClient($mqttBroker, $mqttPort, $clientId);
//            $mqttClient->connect();
//            $mqttClient->publish($topic, json_encode($message), 0);
//            $mqttClient->disconnect();
//        } catch (\Exception $e) {
//            Log::error("MQTT Publish Error: " . $e->getMessage());
//        }
//    }

    public static function publish($topic, $message)
    {
        $server   = env('MQTT_HOST', 'broker.hivemq.com');
        $port     = env('MQTT_PORT', 1883);
        $username = env('MQTT_USERNAME', null);
        $password = env('MQTT_PASSWORD', null);
        $clientId = env('MQTT_CLIENT_ID', 'default-client-id');
        $useTls   = env('MQTT_TLS', true);

        $connectionSettings = new ConnectionSettings();
        $connectionSettings = $connectionSettings
            ->setUsername($username)
            ->setPassword($password)
            ->setKeepAliveInterval(60)
            ->setUseTls($useTls === 'true'); // Convert string "true"/"false" to boolean

        Log::info("MQTT: Connecting to $server:$port...");

        try {
            $mqtt = new MqttClient($server, $port, $clientId);
            $mqtt->connect($connectionSettings);

            Log::info("MQTT: Connected successfully!");

            $mqtt->publish($topic, json_encode($message), 0);
            $mqtt->disconnect();

            Log::info("MQTT: Message published successfully!");
        } catch (\Exception $e) {
            Log::error("MQTT Publish Error: " . $e->getMessage());
        }
    }
}
