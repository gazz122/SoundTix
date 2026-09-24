<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OtpVerificationController extends Controller
{
    public function __construct(private OtpService $otp) {}

    public function show(Request $request): Response
    {
        abort_if($request->user()->is_verified, 403, 'Akun Anda sudah terverifikasi.');

        return Inertia::render('Auth/Verify', [
            'email' => $request->user()->email,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        abort_if($request->user()->is_verified, 403, 'Akun Anda sudah terverifikasi.');

        $this->otp->send($request->user());

        return redirect()->route('verify.show')->with('status', 'Kode verifikasi baru telah dikirim ke email Anda.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        abort_if($request->user()->is_verified, 403, 'Akun Anda sudah terverifikasi.');

        $code = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ])['code'];

        if (! $this->otp->verify($request->user(), $code)) {
            return redirect()->route('verify.show')->withErrors([
                'code' => 'Kode verifikasi salah atau sudah kedaluwarsa.',
            ])->withInput();
        }

        return redirect()->route('dashboard')->with('success', 'Email berhasil diverifikasi. Selamat berbelanja tiket!');
    }
}
