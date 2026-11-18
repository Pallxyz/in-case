<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Item;
use App\Models\User;
use App\Notifications\DailyPackingReminderNotification;
use App\Services\ScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    private function activityFor(User $user, $date, int $count = 2, bool $active = true): array
    {
        $items = collect(range(1, $count))->map(fn ($n) => Item::create([
            'user_id' => $user->id, 'name' => "B{$n}", 'rfid_uid' => "R-{$user->id}-{$n}", 'quantity' => 1, 'status' => 'active',
        ]));

        $activity = Activity::create(['user_id' => $user->id, 'name' => 'Trip', 'trip_date' => $date]);
        $activity->items()->sync($items->pluck('id'));

        if ($active) {
            $activity->activate();
        }

        return [$activity, $items];
    }

    public function test_pengingat_dikirim_kalau_ada_barang_belum_discan(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->activityFor($user, today());

        $this->artisan('incase:send-item-reminders')->assertSuccessful();

        Notification::assertSentTo($user, DailyPackingReminderNotification::class);
    }

    public function test_pengingat_juga_untuk_kegiatan_besok(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->activityFor($user, today()->addDay());

        $this->artisan('incase:send-item-reminders');

        Notification::assertSentTo($user, DailyPackingReminderNotification::class);
    }

    public function test_tidak_ada_pengingat_kalau_semua_sudah_discan(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        [, $items] = $this->activityFor($user, today());

        foreach ($items as $item) {
            app(ScanService::class)->handle($item->rfid_uid);
        }

        $this->artisan('incase:send-item-reminders');

        Notification::assertNothingSent();
    }

    public function test_tidak_ada_pengingat_untuk_kegiatan_jauh_atau_tidak_aktif(): void
    {
        Notification::fake();

        $far = User::factory()->create();
        $this->activityFor($far, today()->addDays(5));

        $inactive = User::factory()->create();
        $this->activityFor($inactive, today(), 2, false);

        $past = User::factory()->create();
        $this->activityFor($past, today()->subDays(3));

        $this->artisan('incase:send-item-reminders');

        Notification::assertNothingSent();
    }
}
