import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/components/PublicLayout';

const steps = [
    ['01', 'Buat akun penyelenggara', 'Daftar atau masuk ke akun SoundTix, lalu pastikan profil penyelenggara Anda sudah lengkap.'],
    ['02', 'Siapkan detail event', 'Isi nama event, jadwal, lokasi, deskripsi, poster, dan kategori tiket yang ingin dijual.'],
    ['03', 'Tunggu verifikasi', 'Tim SoundTix meninjau informasi event agar calon pembeli mendapatkan informasi yang jelas dan tepercaya.'],
    ['04', 'Promosikan dan pantau', 'Bagikan halaman event, pantau penjualan dari dashboard, dan siapkan tim untuk validasi QR di venue.'],
];

export default function OrganizerGuide() {
    return (
        <PublicLayout>
            <Head title="Panduan Penyelenggara" />

            <div className="mx-auto max-w-4xl">
                <header className="max-w-2xl">
                    <p className="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">Panduan Penyelenggara</p>
                    <h1 className="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Mulai jual tiket event Anda dengan terarah.</h1>
                    <p className="mt-4 text-base leading-7 text-slate-600">Ikuti alur singkat ini untuk menyiapkan event, menjual tiket, dan menyambut audiens dengan percaya diri.</p>
                </header>

                <section className="mt-8 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                    {steps.map(([number, title, description], index) => (
                        <div key={number} className={`flex gap-5 px-6 py-6 sm:px-8 ${index < steps.length - 1 ? 'border-b border-slate-200/80' : ''}`}>
                            <span className="shrink-0 text-sm font-semibold text-indigo-600">{number}</span>
                            <div>
                                <h2 className="font-semibold text-slate-900">{title}</h2>
                                <p className="mt-2 text-sm leading-6 text-slate-500">{description}</p>
                            </div>
                        </div>
                    ))}
                </section>

                <section className="mt-8 grid gap-4 sm:grid-cols-2">
                    <InfoCard title="Pastikan informasi siap" items={['Gunakan poster dengan resolusi baik.', 'Tulis jadwal dan lokasi secara lengkap.', 'Atur kuota serta harga tiap kategori.']} />
                    <InfoCard title="Siapkan hari acara" items={['Tetapkan petugas di pintu masuk.', 'Gunakan scanner untuk memvalidasi QR.', 'Cek laporan penjualan dan tiket sebelum acara.']} />
                </section>

                <div className="mt-8 flex flex-col items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50 p-6 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold text-slate-900">Siap menyiapkan event?</h2>
                        <p className="mt-1 text-sm text-slate-600">Masuk ke dashboard untuk mulai membuat event.</p>
                    </div>
                    <Link href="/register" className="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-indigo-700">Mulai sekarang</Link>
                </div>
            </div>
        </PublicLayout>
    );
}

function InfoCard({ title, items }) {
    return (
        <article className="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <h2 className="font-semibold text-slate-900">{title}</h2>
            <ul className="mt-4 grid gap-3 text-sm text-slate-600">
                {items.map((item) => <li key={item} className="flex gap-3"><span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-600" />{item}</li>)}
            </ul>
        </article>
    );
}