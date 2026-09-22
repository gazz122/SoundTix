<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Notifications\SoundTixNotification;
use App\Repositories\EventRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizerRefundController extends Controller
{
    public function __construct(private EventRepository $events) {}

    public function requestEventCancellation(Request $request, int $eventId): RedirectResponse
    {
        $event = $this->events->findOwnedByOrganizer($request->user()->id, $eventId);

        if (! $this->isCancellable($event)) {
            return redirect()->route('organizer.events.show', $event->id)->with(
                'error',
                'Hanya event berstatus Published dan belum lewat tanggalnya yang dapat diajukan pembatalan.',
            );
        }

        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        $event->update([
            'status' => EventStatus::PendingCancellation->value,
            'cancellation_reason' => $reason,
        ]);

        $admins = User::where('role', UserRole::Admin->value)->get();

        foreach ($admins as $admin) {
            $admin->notify(new SoundTixNotification([
                'title' => 'Pengajuan pembatalan event',
                'message' => "Organizer mengajukan pembatalan event \"{$event->title}\". Alasan: {$reason}",
                'type' => 'event_cancellation_requested',
                'action_url' => route('admin.refunds.index'),
                'action_label' => 'Tinjau permintaan',
            ]));
        }

        return redirect()->route('organizer.events.show', $event->id)->with(
            'success',
            'Pengajuan pembatalan event terkirim dan menunggu persetujuan admin.',
        );
    }

    private function isCancellable(Event $event): bool
    {
        return $event->status === EventStatus::Published->value
            && ($event->event_date === null || ! $event->event_date->isPast());
    }
}
