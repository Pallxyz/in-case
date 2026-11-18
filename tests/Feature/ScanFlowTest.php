<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Item;
use App\Models\ItemResolution;
use App\Models\ScanLog;
use App\Models\User;
use App\Services\ScanModeService;
use App\Services\ScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Alur inti InCase: scan berangkat -> scan pulang -> barang tidak kembali.
 */
class ScanFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Activity, 2: Collection<int, Item>} */
    private function trip(int $count = 3, ?User $user = null): array
    {
        $user ??= User::factory()->create();

        $items = collect(range(1, $count))->map(fn ($n) => Item::create([
            'user_id' => $user->id,
            'name' => "Barang {$n}",
            'rfid_uid' => "UID-{$user->id}-{$n}",
            'quantity' => 1,
            'status' => 'active',
        ]));

        $activity = Activity::create([
            'user_id' => $user->id,
            'name' => 'Hiking Gunung',
            'trip_date' => today(),
        ]);
        $activity->items()->sync($items->pluck('id'));
        $activity->activate();

        return [$user, $activity, $items];
    }

    private function scan(Item|string $item): array
    {
        $uid = $item instanceof Item ? $item->rfid_uid : $item;

        return app(ScanService::class)->handle($uid);
    }

    public function test_stiker_tidak_dikenal_ditolak_dan_uid_disimpan_untuk_pendaftaran(): void
    {
        $result = $this->scan('TIDAK-ADA');

        $this->assertSame(404, $result['code']);
        $this->assertSame('unknown', $result['body']['status']);
        $this->assertSame('TIDAK-ADA', cache()->get('latest_unregistered_scan')['uid']);
        $this->assertSame(0, ScanLog::count());
    }

    public function test_scan_tanpa_kegiatan_aktif_tidak_dicatat(): void
    {
        [, $activity, $items] = $this->trip();
        $activity->update(['is_active' => false]);

        $result = $this->scan($items[0]);

        $this->assertSame(422, $result['code']);
        $this->assertSame('no_activity', $result['body']['status']);
        $this->assertSame(0, ScanLog::count());
    }

    public function test_barang_diarsipkan_tidak_dicatat(): void
    {
        [, , $items] = $this->trip();
        $items[0]->update(['status' => 'archived']);

        $result = $this->scan($items[0]);

        $this->assertSame('archived', $result['body']['status']);
        $this->assertSame(0, ScanLog::count());
    }

    public function test_scan_berangkat_melaporkan_barang_yang_masih_kurang_lalu_lengkap(): void
    {
        [, , $items] = $this->trip(3);

        $first = $this->scan($items[0]);
        $this->assertSame(200, $first['code']);
        $this->assertSame('packing', $first['body']['phase']);
        $this->assertSame('missing', $first['body']['status']);
        $this->assertEqualsCanonicalizing(['Barang 2', 'Barang 3'], $first['body']['missing_items']->all());

        $this->assertSame('missing', $this->scan($items[1])['body']['status']);

        $last = $this->scan($items[2]);
        $this->assertSame('complete', $last['body']['status']);
        $this->assertSame(3, ScanLog::where('phase', 'packing')->count());
    }

    public function test_scan_ganda_barang_yang_sama_tidak_mengubah_hitungan(): void
    {
        [, , $items] = $this->trip(2);

        $this->scan($items[0]);
        $again = $this->scan($items[0]);

        $this->assertSame('missing', $again['body']['status']);
        $this->assertSame(['Barang 2'], $again['body']['missing_items']->all());
    }

    public function test_barang_di_luar_daftar_tetap_dicatat_sebagai_extra(): void
    {
        [$user, , $items] = $this->trip(2);
        $extra = Item::create([
            'user_id' => $user->id, 'name' => 'Kamera', 'rfid_uid' => 'UID-EXTRA', 'quantity' => 1, 'status' => 'active',
        ]);

        $result = $this->scan($extra);

        $this->assertSame(200, $result['code']);
        $this->assertSame('extra', $result['body']['status']);
        $this->assertSame(1, ScanLog::where('item_id', $extra->id)->count());
        // Barang di daftar tetap dihitung kurang.
        $this->assertCount(2, $result['body']['missing_items']);
    }

    public function test_scan_pulang_memeriksa_barang_yang_dibawa_saat_berangkat(): void
    {
        [$user, , $items] = $this->trip(3);

        foreach ($items as $item) {
            $this->scan($item);
        }

        app(ScanModeService::class)->set($user, 'return');

        $r1 = $this->scan($items[0]);
        $this->assertSame('return', $r1['body']['phase']);
        $this->assertSame('missing', $r1['body']['status']);
        $this->assertEqualsCanonicalizing(['Barang 2', 'Barang 3'], $r1['body']['missing_items']->all());

        $this->scan($items[1]);
        $last = $this->scan($items[2]);

        $this->assertSame('complete', $last['body']['status']);
    }

    public function test_barang_yang_tidak_dibawa_saat_berangkat_tidak_dianggap_tertinggal_saat_pulang(): void
    {
        [$user, , $items] = $this->trip(3);

        // Hanya 2 barang yang dibawa.
        $this->scan($items[0]);
        $this->scan($items[1]);

        app(ScanModeService::class)->set($user, 'return');
        $this->scan($items[0]);
        $result = $this->scan($items[1]);

        $this->assertSame('complete', $result['body']['status']);
    }

    public function test_catatan_hilang_terhapus_otomatis_saat_barang_discan_lagi(): void
    {
        [$user, $activity, $items] = $this->trip(2);

        foreach ($items as $item) {
            $this->scan($item);
        }

        ItemResolution::create([
            'user_id' => $user->id,
            'item_id' => $items[1]->id,
            'activity_id' => $activity->id,
            'date' => $activity->trip_date->toDateString(),
            'status' => ItemResolution::LOST,
            'note' => 'terakhir di pos 2',
        ]);

        app(ScanModeService::class)->set($user, 'return');
        $this->scan($items[1]);

        $this->assertSame(0, ItemResolution::count());
    }

    public function test_barang_yang_sudah_dijelaskan_tidak_lagi_dianggap_tertinggal(): void
    {
        [$user, $activity, $items] = $this->trip(2);

        foreach ($items as $item) {
            $this->scan($item);
        }

        ItemResolution::create([
            'user_id' => $user->id,
            'item_id' => $items[1]->id,
            'activity_id' => $activity->id,
            'date' => $activity->trip_date->toDateString(),
            'status' => ItemResolution::LENT,
        ]);

        app(ScanModeService::class)->set($user, 'return');
        $result = $this->scan($items[0]);

        $this->assertSame('complete', $result['body']['status']);
    }

    public function test_mulai_baru_menghitung_scan_dari_awal(): void
    {
        [, $activity, $items] = $this->trip(2);

        // Perjalanan lama (kemarin) sudah lengkap.
        $activity->update(['trip_date' => today()->subDay()]);
        foreach ($items as $item) {
            $this->scan($item);
        }
        $this->assertCount(0, app(\App\Services\PackingChecklistService::class)->missingItems($activity->fresh()));

        $activity->fresh()->restart();

        $this->assertCount(2, app(\App\Services\PackingChecklistService::class)->missingItems($activity->fresh()));
    }

    public function test_kegiatan_hanya_satu_yang_aktif_per_pengguna(): void
    {
        [$user, $first] = $this->trip(1);
        $second = Activity::create(['user_id' => $user->id, 'name' => 'Liburan', 'trip_date' => today()]);

        $second->activate();

        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame($second->id, $user->fresh()->activeActivity->id);
    }

    public function test_scan_pengguna_lain_tidak_bercampur(): void
    {
        [, , $itemsA] = $this->trip(2);
        [, , $itemsB] = $this->trip(2);

        $this->scan($itemsA[0]);
        $result = $this->scan($itemsB[0]);

        // B baru scan 1 dari 2, jadi masih kurang 1 (bukan terpengaruh scan milik A).
        $this->assertSame(['Barang 2'], $result['body']['missing_items']->all());
    }

    public function test_endpoint_api_butuh_api_key(): void
    {
        [, , $items] = $this->trip(2);
        config(['services.esp32.api_key' => 'rahasia']);

        $this->postJson('/api/scan', ['rfid_uid' => $items[0]->rfid_uid])->assertStatus(401);

        $this->postJson('/api/scan', ['rfid_uid' => $items[0]->rfid_uid], ['X-API-KEY' => 'rahasia'])
            ->assertOk()
            ->assertJsonPath('status', 'missing');
    }
}
