<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public const EXPIRES_IN_MINUTES = 10;

    public function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'otp_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
        ]);

        Mail::to($user)->send(new OtpMail($user, $code));
    }

    public function verify(User $user, string $code): bool
    {
        if ($user->is_verified) {
            return true;
        }

        if ($user->otp_hash === null || $user->otp_expires_at === null || $user->otp_expires_at->isPast()) {
            return false;
        }

        if (! Hash::check($code, $user->otp_hash)) {
            return false;
        }

        $user->update([
            'is_verified' => true,
            'otp_hash' => null,
            'otp_expires_at' => null,
        ]);

        return true;
    }
}
