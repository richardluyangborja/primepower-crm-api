<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReminderNotificationResource;
use App\Models\Reminder;
use App\Models\ReminderView;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Overdue follow-up reminders with the current user's viewed state.
     *
     * Derived live from the reminders table (long-poll friendly) instead of
     * scheduler-written rows: admin and manager see every overdue reminder,
     * sales reps see only their own.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $reminders = $this->withViewedState($this->visibleOverdue($user), $user->id)
            ->when($request->boolean('unread_only'), fn (Builder $q) => $q->whereNull('reminder_views.viewed_at'))
            ->orderByRaw('CASE WHEN reminder_views.viewed_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('due_date')
            ->paginate(20);

        return ReminderNotificationResource::collection($reminders);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();

        $count = $this->withViewedState($this->visibleOverdue($user), $user->id)
            ->whereNull('reminder_views.viewed_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $reminder = Reminder::findOrFail($id);
        $this->authorize('view', $reminder);

        ReminderView::updateOrCreate(
            ['reminder_id' => $reminder->id, 'user_id' => $request->user()->id],
            ['viewed_at' => now()]
        );

        $reminder->setAttribute('viewed_at', now());

        return new ReminderNotificationResource($reminder);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        $now = now();

        $ids = $this->visibleOverdue($user)->pluck('reminders.id');

        ReminderView::upsert(
            $ids->map(fn ($id) => [
                'reminder_id' => $id,
                'user_id' => $user->id,
                'viewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
            ['reminder_id', 'user_id'],
            ['viewed_at', 'updated_at']
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Incomplete reminders past their due date, scoped by role.
     */
    private function visibleOverdue(User $user): Builder
    {
        return Reminder::query()
            ->where('is_completed', false)
            ->whereDate('due_date', '<', today()->toDateString())
            ->when(
                ! $user->isAdmin() && ! $user->isManager(),
                fn (Builder $q) => $q->where('reminders.user_id', $user->id)
            );
    }

    private function withViewedState(Builder $query, string $userId): Builder
    {
        return $query
            ->leftJoin('reminder_views', function ($join) use ($userId) {
                $join->on('reminder_views.reminder_id', '=', 'reminders.id')
                    ->where('reminder_views.user_id', '=', $userId);
            })
            ->select('reminders.*', 'reminder_views.viewed_at as viewed_at');
    }
}
