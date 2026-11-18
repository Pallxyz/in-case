<?php

/*
|--------------------------------------------------------------------------
| MQTT broker untuk listener RFID (php artisan mqtt:listen)
|--------------------------------------------------------------------------
| Semua nilai diambil dari .env supaya IP/password tidak ikut ke GitHub.
| Alat RFID (ESP32) harus memakai broker, topic, user, dan password yang sama.
*/
return [
    'host' => env('MQTT_HOST', '127.0.0.1'),
    'port' => (int) env('MQTT_PORT', 1883),
    'username' => env('MQTT_USER'),
    'password' => env('MQTT_PASS'),
    'topic' => env('MQTT_TOPIC', 'incase-mybook2026/rfid/scan'),
];
