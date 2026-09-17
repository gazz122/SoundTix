<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Repositories\EventRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TicketCategoryRepository;
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
        private TicketCategoryRepository $ticketCategories,
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
                'pending_events' => $this->events->pendingForAdmin()
                    ->map(fn ($event) => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'venue_name' => $event->venue_name,
                        'city' => $event->city,
                        'event_date' => $event->event_date?->format('d M Y'),
                        'organizer' => $event->organizer?->name,
                    ])
                    ->values()
                    ->all(),
            ]),
            UserRole::Gatekeeper => Inertia::render('Dashboard/Organizer', [
                'stats' => $this->gatekeeperStats($user->id),
                'dashboard_mode' => 'gatekeeper',
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
            'sales_by_day' => $this->orders->dailyPaidRevenueForOrganizer($userId, 30),
            'top_categories' => $this->orders->topSellingCategoriesForOrganizer($userId),
            'quota_fill' => $this->ticketCategories->quotaFillForOrganizer($userId),
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
