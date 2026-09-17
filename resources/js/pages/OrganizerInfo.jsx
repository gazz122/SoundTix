import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/components/PublicLayout';

const features = [
    ['Kelola event dalam satu tempat', 'Atur detail event, kategori tiket, harga, dan kuota dari dashboard penyelenggara.'],
    ['Pembayaran dengan escrow', 'Dana transaksi dikelola secara bertahap dan dicairkan mengikuti status serta penyelesaian event.'],
    ['Laporan yang mudah dipantau', 'Lihat ringkasan tiket terjual dan pendapatan untuk membantu mengambil keputusan promosi.'],
    ['Validasi QR di venue', 'Gunakan QR unik pada setiap e-ticket untuk mempercepat pemeriksaan dan mencegah tiket digunakan ulang.'],
];

export default function OrganizerInfo() {
    return (
        <PublicLayout>
            <Head title="Informasi Penyelenggara" />

            <div className="mx-auto max-w-4xl">
                <section className="rounded-2xl border border-slate-200/80 bg-white px-6 py-10 shadow-sm sm:px-10 sm:py-12">
                    <p className="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">Informasi Penyelenggara</p>
                    <h1 className="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Semua yang Anda butuhkan untuk menyelenggarakan event.</h1>
                    <p className="mt-4 max-w-2xl text-base leading-7 text-slate-600">SoundTix membantu promotor mengurangi pekerjaan administratif agar lebih fokus menghadirkan pengalaman terbaik untuk audiens.</p>
                </section>

                <section className="mt-8 grid gap-4 sm:grid-cols-2">
                    {features.map(([title, description]) => (
                        <article key={title} className="rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
                            <h2 className="font-semibold text-slate-900">{title}</h2>
                            <p className="mt-2 text-sm leading-6 text-slate-500">{description}</p>
                        </article>
                    ))}
                </section>

                <section className="mt-8 rounded-2xl bg-slate-800 px-6 py-8 text-white sm:px-10">
                    <div className="max-w-2xl">
                        <p className="text-sm font-medium text-indigo-300">Dukungan untuk promotor</p>
                        <h2 className="mt-2 text-2xl font-semibold">Event Anda dimulai dari persiapan yang jelas.</h2>
                        <p className="mt-3 text-sm leading-6 text-slate-300">Pelajari alur publikasi dan operasional event, kemudian gunakan dashboard untuk memantau perjalanan tiket Anda.</p>
                        <Link href="/organizer-guide" className="mt-6 inline-flex rounded-lg bg-white px-4 py-2.5 text-sm font-medium text-slate-800 transition-colors hover:bg-slate-100">Baca panduan penyelenggara</Link>
                    </div>
                </section>
            </div>
        </PublicLayout>
    );
}