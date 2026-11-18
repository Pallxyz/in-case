<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Notifications\DailyPackingReminderNotification;
use App\Services\PackingChecklistService;
use Illuminate\Console\Command;

class SendItemReminders extends Command
{
    protected $signature = 'incase:send-item-reminders';

    protected $description = 'Kirim pengingat "siapkan barangmu" untuk kegiatan aktif yang berlangsung hari ini atau besok';

    public function __construct(private PackingChecklistService $checklist)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        Activity::with('user')
            ->where('is_active', true)
            ->whereDate('trip_date', '>=', today())
            ->whereDate('trip_date', '<=', today()->addDay())
            ->chunkById(100, function ($activities) {
                foreach ($activities as $activity) {
                    $missing = $this->checklist->missingItems($activity);

                    if ($missing->isEmpty()) {
                        continue;
                    }

                    $activity->user->notify(
                        new DailyPackingReminderNotification($activity->trip_date, $missing->pluck('name'))
                    );
                }
            });
    }
}
