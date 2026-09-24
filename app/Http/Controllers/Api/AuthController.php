<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private UserRepository $users,
        private OtpService $otp,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $this->users->create([
            ...$data,
            'role' => UserRole::Customer->value,
            'is_verified' => false,
        ]);

        $this->otp->send($user);

        return response()->json([
            'token' => $user->createToken('soundtix')->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    public function verifySend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_verified) {
            return response()->json(['message' => 'Akun Anda sudah terverifikasi.'], 422);
        }

        $this->otp->send($user);

        return response()->json(['message' => 'Kode verifikasi baru telah dikirim ke email Anda.']);
    }

    public function verifyConfirm(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->is_verified) {
            return response()->json(['message' => 'Akun Anda sudah terverifikasi.'], 422);
        }

        $code = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ])['code'];

        if (! $this->otp->verify($user, $code)) {
            throw ValidationException::withMessages([
                'code' => ['Kode verifikasi salah atau sudah kedaluwarsa.'],
            ]);
        }

        return response()->json([
            'message' => 'Email berhasil diverifikasi.',
            'user' => new UserResource($user->fresh()),
        ]);
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
