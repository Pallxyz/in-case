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
use Tests\TestCase;

/**
 * Halaman, kepemilikan data, dan form (kegiatan, barang, cek pulang).
 */
class ActivityPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function item(User $user, string $name, array $extra = []): Item
    {
        return Item::create($extra + [
            'user_id' => $user->id,
            'name' => $name,
            'rfid_uid' => 'UID-' . $user->id . '-' . $name,
            'quantity' => 1,
            'status' => 'active',
        ]);
    }

    private function activity(User $user, array $items, bool $active = true): Activity
    {
        $activity = Activity::create(['user_id' => $user->id, 'name' => 'Hiking', 'trip_date' => today()]);
        $activity->items()->sync(collect($items)->pluck('id'));

        if ($active) {
            $activity->activate();
        }

        return $activity;
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/activities')->assertRedirect('/login');
        $this->get('/items')->assertRedirect('/login');
        $this->get('/scan-history')->assertRedirect('/login');
    }

    public function test_dasbor_mengarah_ke_kegiatan_aktif_atau_daftar_kegiatan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('activities.index'));

        $activity = $this->activity($user, []);

        // Objek user baru: relasi activeActivity di objek lama masih tersimpan kosong.
        $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('activities.show', $activity));
    }

    public function test_semua_halaman_utama_tampil(): void
    {
        $user = User::factory()->create();
        $a = $this->item($user, 'Jaket');
        $b = $this->item($user, 'Laptop', ['is_valuable' => true]);
        $activity = $this->activity($user, [$a, $b]);

        $this->actingAs($user);

        $this->get('/activities')->assertOk()->assertSee('Hiking');
        $this->get(route('activities.show', $activity))->assertOk()->assertSee('Jaket')->assertSee('Laptop');
        $this->get('/items')->assertOk()->assertSee('Jaket');
        $this->get('/scan-history')->assertOk();
        $this->get('/profile')->assertOk();
    }

    public function test_halaman_kegiatan_menampilkan_progres_dan_barang_di_luar_daftar(): void
    {
        $user = User::factory()->create();
        $a = $this->item($user, 'Jaket');
        $b = $this->item($user, 'Senter');
        $extra = $this->item($user, 'Kamera');
        $activity = $this->activity($user, [$a, $b]);

        app(ScanService::class)->handle($a->rfid_uid);
        app(ScanService::class)->handle($extra->rfid_uid);

        $this->actingAs($user)
            ->get(route('activities.show', $activity))
            ->assertOk()
            ->assertSee('1 / 2')
            ->assertSee('50%')
            ->assertSee('Kamera');
    }

    public function test_pengguna_tidak_bisa_membuka_atau_mengubah_kegiatan_orang_lain(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $activity = $this->activity($owner, []);

        $this->actingAs($other);

        $this->get(route('activities.show', $activity))->assertForbidden();
        $this->put(route('activities.update', $activity), ['name' => 'X'])->assertForbidden();
        $this->post(route('activities.activate', $activity))->assertForbidden();
        $this->post(route('activities.restart', $activity))->assertForbidden();
        $this->delete(route('activities.destroy', $activity))->assertForbidden();
    }

    public function test_buat_kegiatan_pertama_langsung_aktif_dan_barang_orang_lain_ditolak(): void
    {
        $user = User::factory()->create();
        $mine = $this->item($user, 'Jaket');
        $stranger = $this->item(User::factory()->create(), 'Milik Orang');

        $this->actingAs($user)
            ->post(route('activities.store'), ['name' => 'Liburan', 'item_ids' => [$stranger->id]])
            ->assertSessionHasErrors('item_ids.0');

        $this->post(route('activities.store'), ['name' => 'Liburan', 'item_ids' => [$mine->id]])
            ->assertRedirect();

        $activity = Activity::firstWhere('name', 'Liburan');
        $this->assertTrue($activity->is_active);
        $this->assertSame([$mine->id], $activity->items()->pluck('items.id')->all());
    }

    public function test_ubah_kegiatan_mengganti_daftar_barang(): void
    {
        $user = User::factory()->create();
        $a = $this->item($user, 'A');
        $b = $this->item($user, 'B');
        $activity = $this->activity($user, [$a]);

        $this->actingAs($user)
            ->put(route('activities.update', $activity), ['name' => 'Baru', 'item_ids' => [$b->id]])
            ->assertRedirect();

        $this->assertSame('Baru', $activity->fresh()->name);
        $this->assertSame([$b->id], $activity->items()->pluck('items.id')->all());
    }

    public function test_barang_crud_dan_batas_kepemilikan(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post('/items', [
            'name' => 'Power Bank', 'rfid_uid' => 'RF-1', 'quantity' => 1, 'is_valuable' => '1', 'status' => 'active',
        ])->assertRedirect('/items');

        $item = Item::firstWhere('name', 'Power Bank');
        $this->assertTrue($item->is_valuable);

        // Nama dobel dan UID dobel ditolak.
        $this->post('/items', ['name' => 'Power Bank', 'quantity' => 1, 'status' => 'active'])->assertSessionHasErrors('name');
        $this->post('/items', ['name' => 'Lain', 'rfid_uid' => 'RF-1', 'quantity' => 1, 'status' => 'active'])->assertSessionHasErrors('rfid_uid');

        $this->put("/items/{$item->id}", ['name' => 'Power Bank 2', 'rfid_uid' => 'RF-1', 'quantity' => 2, 'is_valuable' => '0', 'status' => 'active'])
            ->assertRedirect('/items');
        $this->assertSame('Power Bank 2', $item->fresh()->name);
        $this->assertFalse($item->fresh()->is_valuable);

        // Orang lain tidak boleh mengubah/menghapus.
        $this->actingAs($other)->put("/items/{$item->id}", ['name' => 'x', 'quantity' => 1, 'status' => 'active'])->assertForbidden();
        $this->delete("/items/{$item->id}")->assertForbidden();
        $this->assertNotNull(Item::find($item->id));

        $this->actingAs($user)->delete("/items/{$item->id}")->assertRedirect('/items');
        $this->assertNull(Item::find($item->id));
        // Hapus ulang (klik dobel) tetap sukses.
        $this->delete("/items/{$item->id}")->assertRedirect('/items');
    }

    // ---------------------------------------------------------------------
    // Cek pulang: dipinjam / hilang
    // ---------------------------------------------------------------------

    /** Siapkan perjalanan yang sudah selesai scan berangkat dan mulai scan pulang. */
    private function returnScenario(bool $valuable): array
    {
        $user = User::factory()->create();
        $a = $this->item($user, 'Buku');
        $b = $this->item($user, 'Laptop', ['is_valuable' => $valuable]);
        $activity = $this->activity($user, [$a, $b]);

        $svc = app(ScanService::class);
        $svc->handle($a->rfid_uid);
        $svc->handle($b->rfid_uid);

        app(ScanModeService::class)->set($user, 'return');
        $svc->handle($a->rfid_uid); // hanya Buku yang kembali, Laptop tertinggal

        return [$user, $activity, $a, $b];
    }

    public function test_cek_pulang_menampilkan_barang_yang_belum_kembali(): void
    {
        [$user, $activity] = $this->returnScenario(false);

        $this->actingAs($user)
            ->get(route('activities.show', $activity))
            ->assertOk()
            ->assertSee('Cek Pulang')
            ->assertSee('Ke mana Laptop-mu?');
    }

    public function test_cek_pulang_belum_tampil_sebelum_scan_pulang_dimulai(): void
    {
        $user = User::factory()->create();
        $a = $this->item($user, 'Buku');
        $activity = $this->activity($user, [$a]);
        app(ScanService::class)->handle($a->rfid_uid);

        $this->actingAs($user)
            ->get(route('activities.show', $activity))
            ->assertOk()
            ->assertDontSee('Cek Pulang');
    }

    public function test_barang_biasa_boleh_dicatat_dipinjam_atau_hilang_tanpa_keterangan(): void
    {
        [$user, $activity, , $laptop] = $this->returnScenario(false);

        $this->actingAs($user)
            ->post(route('items.resolve', [$activity, $laptop]), ['status' => 'lent', 'confirmed' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame('lent', ItemResolution::firstWhere('item_id', $laptop->id)->status);

        $this->post(route('items.resolve', [$activity, $laptop]), ['status' => 'lost', 'confirmed' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame('lost', ItemResolution::firstWhere('item_id', $laptop->id)->status);
        $this->assertSame(1, ItemResolution::count()); // diralat, bukan dobel
    }

    public function test_barang_berharga_hilang_wajib_keterangan(): void
    {
        [$user, $activity, , $laptop] = $this->returnScenario(true);

        $this->actingAs($user)
            ->post(route('items.resolve', [$activity, $laptop]), ['status' => 'lost', 'confirmed' => 1])
            ->assertSessionHasErrors('note');
        $this->assertSame(0, ItemResolution::count());

        $this->post(route('items.resolve', [$activity, $laptop]), ['status' => 'lost', 'confirmed' => 1, 'note' => 'terakhir di pos 2 jam 10'])
            ->assertSessionHasNoErrors();

        $this->assertSame('terakhir di pos 2 jam 10', ItemResolution::first()->note);

        // Dipinjam tidak butuh keterangan, dan catatan lama dibersihkan.
        $this->post(route('items.resolve', [$activity, $laptop]), ['status' => 'lent', 'confirmed' => 1])
            ->assertSessionHasNoErrors();
        $this->assertNull(ItemResolution::first()->note);
    }

    public function test_konfirmasi_wajib_sebelum_jawaban_disimpan(): void
    {
        [$user, $activity, , $laptop] = $this->returnScenario(false);

        $this->actingAs($user)
            ->post(route('items.resolve', [$activity, $laptop]), ['status' => 'lost'])
            ->assertSessionHasErrors('confirmed');

        $this->assertSame(0, ItemResolution::count());
    }

    public function test_jawaban_ditolak_kalau_barang_sebenarnya_sudah_kembali_atau_cek_pulang_belum_dibuka(): void
    {
        [$user, $activity, $buku] = $this->returnScenario(false);

        // Buku sudah kembali, jadi tidak bisa dijelaskan hilang.
        $this->actingAs($user)
            ->post(route('items.resolve', [$activity, $buku]), ['status' => 'lost', 'confirmed' => 1])
            ->assertSessionHasErrors('item');

        // Cek pulang belum dibuka pada perjalanan lain.
        $other = User::factory()->create();
        $x = $this->item($other, 'X');
        $act = $this->activity($other, [$x]);
        app(ScanService::class)->handle($x->rfid_uid);

        $this->actingAs($other)
            ->post(route('items.resolve', [$act, $x]), ['status' => 'lost', 'confirmed' => 1])
            ->assertSessionHasErrors('item');
    }

    public function test_tidak_bisa_menjelaskan_barang_atau_kegiatan_orang_lain(): void
    {
        [$user, $activity, , $laptop] = $this->returnScenario(false);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('items.resolve', [$activity, $laptop]), ['status' => 'lost', 'confirmed' => 1])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Halaman Pindai
    // ---------------------------------------------------------------------

    public function test_mode_pindai_butuh_kegiatan_aktif(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/scan-history/mode', ['mode' => 'return'])->assertStatus(422);

        $this->activity($user, []);
        $this->actingAs($user->fresh());

        $this->postJson('/scan-history/mode', ['mode' => 'return'])->assertOk()->assertJsonPath('mode', 'return');
        $this->postJson('/scan-history/mode', ['mode' => 'none'])->assertOk()->assertJsonPath('mode', null);
        $this->postJson('/scan-history/mode', ['mode' => 'ngawur'])->assertStatus(422);
    }

    public function test_riwayat_pindai_dan_ekspor_csv(): void
    {
        [$user] = $this->returnScenario(false);

        $this->actingAs($user)->get('/scan-history')->assertOk()->assertSee('Scan Berangkat')->assertSee('Scan Pulang');

        $response = $this->get('/scan-history/export');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Hiking', $response->streamedContent());

        $this->getJson('/scan-history/latest')->assertOk()->assertJsonStructure(['latestId', 'latestAt']);
    }

    public function test_riwayat_hanya_menampilkan_scan_milik_sendiri(): void
    {
        [$owner] = $this->returnScenario(false);
        $other = User::factory()->create();

        $this->actingAs($other)->get('/scan-history')->assertOk()->assertDontSee('Hiking');
        $this->assertGreaterThan(0, ScanLog::where('user_id', $owner->id)->count());
    }

    // ---------------------------------------------------------------------
    // Menunggu stiker baru di halaman Barang (perbandingan zona waktu)
    // ---------------------------------------------------------------------

    public function test_scan_poll_membandingkan_waktu_dengan_benar_lintas_zona_waktu(): void
    {
        $user = User::factory()->create();

        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');

        cache()->put('latest_unregistered_scan', [
            'uid' => 'BARU-1',
            // Format server: zona waktu aplikasi (+07:00)
            'at' => now()->toIso8601String(),
        ], now()->addMinutes(3));

        $this->actingAs($user);

        // Browser mengirim UTC ("Z"). Mulai menunggu SESUDAH scan -> tidak boleh dianggap baru.
        $after = now()->addSeconds(30)->utc()->format('Y-m-d\TH:i:s.v\Z');
        $this->getJson('/items/scan-poll?since=' . urlencode($after))->assertJsonPath('found', false);

        // Mulai menunggu SEBELUM scan -> terbaca.
        $before = now()->subMinute()->utc()->format('Y-m-d\TH:i:s.v\Z');
        $this->getJson('/items/scan-poll?since=' . urlencode($before))
            ->assertJsonPath('found', true)
            ->assertJsonPath('uid', 'BARU-1');
    }
}
