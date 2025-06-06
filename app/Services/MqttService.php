<?php
namespace App\Services;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\Log;

class MqttService
{
    protected static string $server;
    protected static int $port;
    protected static ?string $username;
    protected static ?string $password;
    protected static string $clientId;
    protected static string $clientSubsctiberId;
    protected static bool $useTls;
    protected ?ConnectionSettings $connectionSettings = null;
    protected static ?MqttClient $mqttClient = null;
    protected static ?MqttClient $mqttSubscriber = null;

    private function init()
    {
        self::$server = env('MQTT_HOST', 'broker.hivemq.com');
        self::$port     = env('MQTT_PORT', 8883);
        self::$username = env('MQTT_USERNAME', null);
        self::$password = env('MQTT_PASSWORD', null);
        self::$clientId = env('MQTT_CLIENT_ID', 'default-client-id');
        self::$clientSubsctiberId = env('MQTT_CLIENT_SUBSCRIBER_ID', 'default-subscriber-id');
        self::$useTls   = env('MQTT_TLS', true);

        $this->connectionSettings = (new ConnectionSettings())
            ->setUsername(self::$username)
            ->setPassword(self::$password)
            ->setKeepAliveInterval(10)
            ->setUseTls(self::$useTls);

        self::$mqttClient = new MqttClient(self::$server, self::$port, self::$clientId);
        self::$mqttSubscriber = new MqttClient(self::$server, self::$port, self::$clientSubsctiberId);

        Log::info("MQTT: Initialized with " . self::$server . ':' . self::$port);
    }

    private function getConnection($publisher=true)
    {
        if ($this->connectionSettings === null) {
            self::init();
        }

        if (!self::$mqttClient->isConnected() && !self::$mqttSubscriber->isConnected()) {
            self::$mqttClient->connect($this->connectionSettings,true);
            self::$mqttSubscriber->connect($this->connectionSettings,true);
        }
        $server = self::$server;
        $port = self::$port;

        Log::info("MQTT: Connected to $server:$port...");

        if($publisher){
            return self::$mqttClient;
        }
            return self::$mqttSubscriber;
    }

    public function publish($topic, $message ,$retain = true)
    {
        try {
            $mqtt = $this->getConnection();

            $mqtt->publish($topic, $message, 0,$retain);
//            $mqtt->disconnect();

            Log::info("MQTT: Message published successfully!");
        } catch (\Exception $e) {
            Log::error("MQTT Publish Error: " . $e->getMessage());
        }
    }
    public function subscribe($topic, callable $callback)
    {
        try {
            $mqtt = $this->getConnection(false);

            Log::info("MQTT: Subscribing to topic: $topic");

            $mqtt->subscribe($topic, function ($topic, $message) use ($callback) {
                Log::info("MQTT: Received message on topic '$topic': $message");
                call_user_func($callback, $topic, $message);
            }, 0);

            Log::info("MQTT: Successfully subscribed to $topic");

            // Keep listening forever
            $mqtt->loop(true);

        } catch (\Exception $e) {
            Log::error("MQTT Subscribe Error: " . $e->getMessage());
            throw $e;
        }
    }
}
