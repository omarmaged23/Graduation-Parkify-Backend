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
    protected static bool $useTls;
    protected static ?ConnectionSettings $connectionSettings = null;
    protected static ?MqttClient $mqttClient = null;

    private static function init()
    {
        self::$server = env('MQTT_HOST', 'broker.hivemq.com');
        self::$port     = env('MQTT_PORT', 8883);
        self::$username = env('MQTT_USERNAME', null);
        self::$password = env('MQTT_PASSWORD', null);
        self::$clientId = env('MQTT_CLIENT_ID', 'default-client-id');
        self::$useTls   = env('MQTT_TLS', true);

        self::$connectionSettings = (new ConnectionSettings())
            ->setUsername(self::$username)
            ->setPassword(self::$password)
            ->setKeepAliveInterval(60)
            ->setUseTls(self::$useTls);

        self::$mqttClient = new MqttClient(self::$server, self::$port, self::$clientId);

        Log::info("MQTT: Initialized with " . self::$server . ':' . self::$port);
    }

    private static function getConnection()
    {
        if (self::$connectionSettings === null) {
            self::init();
        }

        if (!self::$mqttClient->isConnected()) {
            self::$mqttClient->connect(self::$connectionSettings,true);
        }
        $server = self::$server;
        $port = self::$port;

        Log::info("MQTT: Connected to $server:$port...");

        return self::$mqttClient;
    }

    public static function publish($topic, $message)
    {
        try {
            $mqtt = self::getConnection();

            $mqtt->publish($topic, $message, 0,true);
//            $mqtt->disconnect();

            Log::info("MQTT: Message published successfully!");
        } catch (\Exception $e) {
            Log::error("MQTT Publish Error: " . $e->getMessage());
        }
    }
}
