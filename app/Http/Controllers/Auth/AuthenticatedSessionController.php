<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canRegister' => Route::has('register'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user->is_active) {
            $this->forgetSession($request);

            abort(403, 'Akun Anda sedang dinonaktifkan.');
        }

        if ($user->role === UserRole::Admin && ! $this->isInAdminEmailWhitelist($user->email)) {
            $this->forgetSession($request);

            abort(403, 'Akses admin dibatasi hanya untuk email pada whitelist sistem.');
        }

        if ($user->role === UserRole::Gatekeeper) {
            return redirect()->route('dashboard.scan');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function forgetSession(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function isInAdminEmailWhitelist(string $email): bool
    {
        return in_array(strtolower($email), config('access.admin_emails', []), true);
    }
}
