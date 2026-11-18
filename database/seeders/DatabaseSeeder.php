<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk demo LOKAL. Jangan dijalankan di server produksi
 * (akunnya memakai password sederhana).
 *
 * Jalankan: php artisan db:seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@incase.test'],
            ['name' => 'Demo InCase', 'password' => 'password'],
        );

        $items = collect([
            ['Tas Carrier', null, false],
            ['Jaket', null, false],
            ['Botol Minum', null, false],
            ['Power Bank', null, true],
            ['Senter', null, false],
            ['Dompet', null, true],
        ])->map(fn ($row) => Item::updateOrCreate(
            ['user_id' => $user->id, 'name' => $row[0]],
            ['rfid_uid' => $row[1], 'is_valuable' => $row[2], 'quantity' => 1, 'status' => 'active'],
        ));

        $activity = Activity::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Hiking Gunung'],
            ['trip_date' => today()],
        );

        $activity->items()->sync($items->pluck('id'));
        $activity->activate();
    }
}
