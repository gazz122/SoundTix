<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private EventRepository $events,
        private OrderRepository $orders,
        private TicketRepository $tickets,
        private UserRepository $users,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return match ($user->role) {
            UserRole::Organizer => Inertia::render('Dashboard/Organizer', [
                'stats' => $this->organizerStats($user->id),
            ]),
            UserRole::Admin => Inertia::render('Dashboard/Admin', [
                'stats' => $this->adminStats(),
            ]),
            UserRole::Gatekeeper => Inertia::render('Dashboard/Gatekeeper', [
                'stats' => $this->gatekeeperStats($user->id),
            ]),
            default => Inertia::render('Dashboard/Customer', [
                'stats' => $this->customerStats($user->id),
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function organizerStats(int $userId): array
    {
        $sales = $this->orders->paidStatsForOrganizer($userId);

        return [
            'total_events' => $this->events->countForOrganizer($userId),
            'published_events' => $this->events->countForOrganizerByStatus($userId, 'published'),
            'pending_events' => $this->events->countForOrganizerByStatus($userId, 'pending'),
            'tickets_sold' => $sales['quantity'],
            'revenue' => $sales['revenue'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminStats(): array
    {
        return [
            'total_users' => $this->users->countAll(),
            'total_events' => $this->events->countAll(),
            'pending_events' => $this->events->countByStatus(EventStatus::Pending->value),
            'total_orders' => $this->orders->countAll(),
            'active_tickets' => $this->tickets->countActive(),
            'revenue' => $this->orders->paidRevenue(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gatekeeperStats(int $userId): array
    {
        return [
            'validated_today' => $this->tickets->countValidatedBySince($userId, today()),
            'validated_total' => $this->tickets->countValidatedBy($userId),
            'active_tickets' => $this->tickets->countActive(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerStats(int $userId): array
    {
        return [
            'total_orders' => $this->orders->countByCustomer($userId),
            'paid_orders' => $this->orders->countPaidByCustomer($userId),
            'active_tickets' => $this->tickets->countActiveByCustomer($userId),
        ];
    }
}
