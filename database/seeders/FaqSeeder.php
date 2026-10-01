<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Pembelian Tiket', 'slug' => 'pembelian-tiket', 'icon' => 'Ticket'],
            ['name' => 'Verifikasi OTP & Akun', 'slug' => 'verifikasi-otp-akun', 'icon' => 'ShieldCheck'],
            ['name' => 'E-Ticket Gambar', 'slug' => 'e-ticket-gambar', 'icon' => 'QrCode'],
            ['name' => 'Pembayaran & Refund', 'slug' => 'pembayaran-refund', 'icon' => 'CreditCard'],
            ['name' => 'Event & Check-in', 'slug' => 'event-check-in', 'icon' => 'CalendarCheck'],
        ];

        foreach ($categories as $index => $category) {
            FaqCategory::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'order' => $index,
                ],
            );
        }

        $faqs = [
            [
                'slug' => 'pembelian-tiket',
                'question' => 'Bagaimana cara membeli tiket konser di SoundTix?',
                'answer' => 'Pilih event, tentukan kategori tiket dan jumlahnya, lalu masukkan data pemesan. Setelah itu Anda diarahkan ke pembayaran. Tiket digital langsung terbit setelah pembayaran diverifikasi.',
            ],
            [
                'slug' => 'pembelian-tiket',
                'question' => 'Apakah tiket bisa dibeli tanpa membuat akun?',
                'answer' => 'Tidak. Akun diperlukan agar tiket tersimpan rapi dan bisa diakses kembali kapan saja melalui menu Tiket Saya.',
            ],
            [
                'slug' => 'pembelian-tiket',
                'question' => 'Berapa maksimal tiket dalam satu transaksi?',
                'answer' => 'Maksimal tiket per transaksi mengikuti ketentuan kategori yang dipilih, umumnya 1 sampai 5 tiket per pesanan.',
            ],
            [
                'slug' => 'pembelian-tiket',
                'question' => 'Apakah tiket dapat dipindah ke akun lain atau dihadiahkan?',
                'answer' => 'Tiket melekat pada akun pembeli dan tidak dapat dipindahkan. Pastikan Anda login dengan akun yang benar saat membeli.',
            ],
            [
                'slug' => 'verifikasi-otp-akun',
                'question' => 'Mengapa saya harus verifikasi OTP?',
                'answer' => 'Verifikasi OTP memastikan nomor WhatsApp dan email yang terdaftar benar milik Anda, sehingga tiket tidak mudah jatuh ke orang lain.',
            ],
            [
                'slug' => 'verifikasi-otp-akun',
                'question' => 'Kode OTP tidak kunjung masuk, bagaimana solusinya?',
                'answer' => 'Periksa koneksi internet, pastikan nomor yang terdaftar aktif, lalu tekan kirim ulang. Kode OTP berlaku terbatas dan akan kedaluwarsa.',
            ],
            [
                'slug' => 'verifikasi-otp-akun',
                'question' => 'Saya lupa kata sandi akun, apa yang harus dilakukan?',
                'answer' => 'Gunakan tautan lupa kata sandi pada halaman masuk. Instruksi pemulihan dikirim melalui email terdaftar.',
            ],
            [
                'slug' => 'e-ticket-gambar',
                'question' => 'Di mana saya melihat e-ticket dan kode QR tiket?',
                'answer' => 'Masuk ke menu Tiket Saya, pilih pesanan yang aktif, lalu buka bagian E-Ticket. Kode QR akan ditampilkan dan tersedia dalam format PDF untuk dicetak.',
            ],
            [
                'slug' => 'e-ticket-gambar',
                'question' => 'Apakah e-ticket harus dicetak atau bisa tampil dari HP?',
                'answer' => 'Keduanya bisa. Petugas check-in menerima tampilan lewat layar HP maupun tiket cetak, selama kode QR terbaca jelas.',
            ],
            [
                'slug' => 'e-ticket-gambar',
                'question' => 'Kode QR e-ticket tidak terbaca petugas, apa yang harus saya lakukan?',
                'answer' => 'Tambah kecerahan layar, tutup aplikasi lain, gunakan data seluler, lalu coba buka ulang e-ticket. Jika tetap gagal, hubungi petugas venue atau layanan bantuan.',
            ],
            [
                'slug' => 'pembayaran-refund',
                'question' => 'Metode pembayaran apa saja yang tersedia?',
                'answer' => 'Pembayaran dapat dilakukan melalui berbagai saluran yang didukung sistem. Detail metode dan batas waktu pembayaran ditampilkan saat checkout.',
            ],
            [
                'slug' => 'pembayaran-refund',
                'question' => 'Saya sudah transfer tapi status masih menunggu pembayaran?',
                'answer' => 'Verifikasi pembayaran biasanya berlangsung beberapa menit. Jika lebih dari batas waktu, hubungi layanan bantuan dengan menyertakan bukti transfer.',
            ],
            [
                'slug' => 'pembayaran-refund',
                'question' => 'Bagaimana kebijakan refund jika event dibatalkan?',
                'answer' => 'Bila event dibatalkan oleh penyelenggara, admin akan memproses refund secara otomatis ke metode pembayaran awal sesuai ketentuan yang berlaku.',
            ],
            [
                'slug' => 'pembayaran-refund',
                'question' => 'Apakah tiket yang sudah dibeli bisa saya refund sendiri?',
                'answer' => 'Pengajuan refund hanya diproses dalam kondisi tertentu, misalnya perubahan jadwal atau pembatalan event. Pengajuan dilakukan lewat halaman detail pesanan.',
            ],
            [
                'slug' => 'event-check-in',
                'question' => 'Kapan gate venue dibuka untuk check-in?',
                'answer' => 'Waktu buka gate menyesuaikan jadwal event dan dapat dilihat pada halaman detail event beserta rundown hari tersebut.',
            ],
            [
                'slug' => 'event-check-in',
                'question' => 'Apakah saya perlu membawa identitas saat check-in?',
                'answer' => 'Identitas berupa KTP atau SIM disarankan untuk mempercepat verifikasi, terutama untuk tiket kategori khusus seperti VIP atau tiket dengan nama.',
            ],
            [
                'slug' => 'event-check-in',
                'question' => 'Tiket sudah digunakan tapi saya datang belakangan, bisa masuk?',
                'answer' => 'Tiket hanya berlaku untuk satu kali check-in. Jika kode QR telah dipakai, tiket tidak dapat digunakan kembali.',
            ],
        ];

        Faq::query()->delete();

        foreach ($faqs as $index => $faq) {
            $category = FaqCategory::where('slug', $faq['slug'])->firstOrFail();

            Faq::create([
                'category_id' => $category->id,
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'is_published' => true,
                'order' => $index,
            ]);
        }
    }
}
