Hai {{ $user->name }},

Terima kasih telah mendaftar di SoundTix.

Kode verifikasi akun Anda adalah:

    {{ $code }}

Masukkan kode tersebut untuk menyelesaikan verifikasi email. Kode berlaku selama {{ \App\Services\OtpService::EXPIRES_IN_MINUTES }} menit.

Jika Anda tidak merasa mendaftar, abaikan email ini.

- Tim SoundTix