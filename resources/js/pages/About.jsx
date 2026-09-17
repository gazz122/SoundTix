import { Head } from '@inertiajs/react';
import PublicLayout from '@/components/PublicLayout';

const highlights = [
    {
        title: 'Tiket digital, tanpa antre',
        description: 'Pesan tiket event favorit dan simpan semuanya secara digital dalam satu akun.',
        icon: 'ticket',
    },
    {
        title: 'QR aman untuk setiap tiket',
        description: 'Setiap tiket memiliki QR unik yang diverifikasi saat masuk venue untuk mencegah duplikasi.',
        icon: 'scan',
    },
    {
        title: 'Transaksi yang lebih tenang',
        description: 'Dana ditahan melalui sistem escrow dan dicairkan sesuai status event yang telah diselesaikan.',
        icon: 'shield',
    },
];

export default function About() {
    return (
        <PublicLayout>
            <Head title="Tentang SoundTix" />

            <div className="mx-auto max-w-4xl">
                <section className="rounded-2xl border border-indigo-100 bg-white px-6 py-10 shadow-sm sm:px-10 sm:py-14">
                    <p className="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">Tentang SoundTix</p>
                    <h1 className="mt-4 max-w-3xl text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        Cara yang lebih mudah untuk menemukan dan menghadiri event.
                    </h1>
                    <p className="mt-5 max-w-2xl text-base leading-7 text-slate-600">
                        SoundTix adalah platform e-ticketing yang mempertemukan penikmat musik dengan event terbaik.
                        Kami membantu pembeli mendapatkan tiket dengan praktis, sekaligus memberi penyelenggara sistem
                        yang rapi untuk mengelola penjualan hingga hari acara.
                    </p>
                </section>

                <section className="mt-8 grid gap-4 sm:grid-cols-3">
                    {highlights.map((highlight) => (
                        <article key={highlight.title} className="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <Icon name={highlight.icon} />
                            <h2 className="mt-4 font-semibold text-slate-900">{highlight.title}</h2>
                            <p className="mt-2 text-sm leading-6 text-slate-500">{highlight.description}</p>
                        </article>
                    ))}
                </section>

                <section className="mt-8 rounded-2xl bg-indigo-600 px-6 py-8 text-white sm:px-10">
                    <h2 className="text-xl font-semibold">Satu alur untuk pengalaman event yang lebih baik.</h2>
                    <p className="mt-3 max-w-2xl text-sm leading-6 text-indigo-100">
                        Dari publikasi event, pembayaran, penerbitan tiket, sampai validasi di pintu masuk, SoundTix
                        dirancang agar setiap langkah mudah dipahami dan dapat dipantau.
                    </p>
                </section>
            </div>
        </PublicLayout>
    );
}

function Icon({ name }) {
    const paths = {
        ticket: <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5V9a2 2 0 0 0 0 4v1.5a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 14.5V13a2 2 0 0 0 0-4V7.5Z" />,
        scan: <path d="M7 3H5a2 2 0 0 0-2 2v2m14-4h2a2 2 0 0 1 2 2v2m0 10v2a2 2 0 0 1-2 2h-2M3 17v2a2 2 0 0 0 2 2h2m2-12h6v6H9V9Z" />,
        shield: <path d="M12 3 20 6v5c0 4.8-3.4 8.8-8 10-4.6-1.2-8-5.2-8-10V6l8-3Zm-3 9 2 2 4-4" />,
    };

    return (
        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
            <svg aria-hidden="true" className="h-5 w-5" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" viewBox="0 0 24 24">
                {paths[name]}
            </svg>
        </span>
    );
}