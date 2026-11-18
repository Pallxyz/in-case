<?php

namespace App\Console\Commands;

use App\Services\ScanService;
use Illuminate\Console\Command;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttListen extends Command
{
    protected $signature = 'mqtt:listen';
    protected $description = 'Dengerin pesan RFID dari broker MQTT';

    public function handle(ScanService $scanService): void
    {
        $server = config('mqtt.host');
        $port = config('mqtt.port');
        $topic = config('mqtt.topic');
        $clientId = 'incase-laravel-' . uniqid();

        $settings = (new ConnectionSettings)->setKeepAliveInterval(60);

        // Username/password dipakai kalau broker sudah diamankan (allow_anonymous false).
        if (config('mqtt.username')) {
            $settings = $settings
                ->setUsername(config('mqtt.username'))
                ->setPassword(config('mqtt.password'));
        }

        $mqtt = new MqttClient($server, $port, $clientId);
        $mqtt->connect($settings, true);

        $this->info("Tersambung ke broker MQTT ({$server}:{$port}), dengerin topic: {$topic}");

        $mqtt->subscribe($topic, function (string $topic, string $message) use ($scanService) {
            $this->info("Topic asli: [{$topic}] | Pesan: [{$message}]");

            try {
                $rfidUid = trim($message);
                $result = $scanService->handle($rfidUid);
                $body = $result['body'];

                match ($body['status']) {
                    'unknown' => $this->error("❌ RFID {$rfidUid} tidak terdaftar di database."),
                    'archived', 'no_activity', 'extra' => $this->warn("⚠ {$body['message']}"),
                    'missing' => $this->warn("⚠️  {$body['message']}"),
                    'complete' => $this->info("✅ {$body['message']}"),
                    default => $this->info("✅ {$body['message']}"),
                };
            } catch (\Throwable $e) {
                $this->error("💥 Error: " . $e->getMessage());
                $this->error($e->getTraceAsString());
            }
        }, 0);

        $mqtt->loop(true);
    }
}
