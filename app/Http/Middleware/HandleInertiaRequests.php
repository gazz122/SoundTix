<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                ] : null,
                'notifications' => $user ? [
                    'unread_count' => $user->unreadNotifications()->count(),
                    'latest' => $user->unreadNotifications()
                        ->latest()
                        ->limit(5)
                        ->get()
                        ->map(fn ($notification) => [
                            'id' => $notification->id,
                            'title' => $notification->data['title'] ?? 'Notifikasi SoundTix',
                            'message' => $notification->data['message'] ?? '',
                            'action_url' => $notification->data['action_url'] ?? null,
                            'created_at' => $notification->created_at?->diffForHumans(),
                        ])
                        ->values()
                        ->all(),
                ] : null,
            ],
            'flash' => [
                'success' => Session::get('success'),
                'error' => Session::get('error'),
                'scan_result' => Session::get('scan_result'),
            ],
        ];
    }
}
