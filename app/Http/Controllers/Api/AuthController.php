<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private UserRepository $users) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['sometimes', Rule::in(UserRole::selfRegistrable())],
        ]);

        $user = $this->users->create([
            ...$data,
            'role' => $data['role'] ?? UserRole::Customer->value,
        ]);

        return response()->json([
            'token' => $user->createToken('soundtix')->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->users->findByEmail($data['email']);

        if (! $user || ! password_verify($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Akun Anda sedang dinonaktifkan.'],
            ]);
        }

        if ($user->role === UserRole::Admin && ! $this->isInAdminEmailWhitelist($user->email)) {
            abort(403, 'Akses admin dibatasi hanya untuk email pada whitelist sistem.');
        }

        return response()->json([
            'token' => $user->createToken('soundtix')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    private function isInAdminEmailWhitelist(string $email): bool
    {
        return in_array(strtolower($email), config('access.admin_emails', []), true);
    }
}
