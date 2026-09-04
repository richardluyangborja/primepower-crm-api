<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Notifications\ReminderDueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send-due {--days=1 : Notify for reminders due within N days}';

    protected $description = 'Dispatch database notifications for due and upcoming reminders.';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $cutoff = Carbon::today()->addDays($days);

        $reminders = Reminder::query()
            ->where('is_completed', false)
            ->whereNotNull('user_id')
            ->whereDate('due_date', '<=', $cutoff)
            ->get();

        $dispatched = 0;
        foreach ($reminders as $reminder) {
            $user = $reminder->user;
            if (! $user) {
                continue;
            }

            $alreadySent = $user->notifications()
                ->where('type', ReminderDueNotification::class)
                ->where('data->reminder_id', $reminder->id)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $user->notify(new ReminderDueNotification($reminder));
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} reminder notification(s).");

        return self::SUCCESS;
    }
}
