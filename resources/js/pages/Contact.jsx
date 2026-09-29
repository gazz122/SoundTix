import { Head } from '@inertiajs/react';
import PublicLayout from '@/components/PublicLayout';


const channels = [
    {
        name: 'Email',
        value: 'bagasdimas225@gmail.com',
        href: 'gmail:bagasdimas225@gmail.com',
        description: 'Pertanyaan, kerja sama, atau dukungan teknis â€” kirim lewat email dan tim kami balas dalam 1Ã—24 jam.',
        icon: 'mail',
    },
    {
        name: 'WhatsApp',
        value: '+62 815-1103-4467',
        href: 'https://wa.me/6281511034467',
        description: 'Chat cepat untuk kendala pembelian tiket dan info yang sifatnya mendesak.',
        icon: 'chat',
    },
    {
        name: 'Instagram',
        value: '@bagwan.jagung',
        href: 'https://instagram.com/bagwan.jagung',
        description: 'Ikuti kabar event terbaru, promosi, dan pengumuman dari SoundTix.',
        icon: 'instagram',
    },
    {
        name: 'X (Twitter)',
        value: '@bgasset_',
        href: 'https://x.com/bgasset_',
        description: 'Cek berita terbaru dan diskusi publik seputar layanan SoundTix.',
        icon: 'twitter',
    },
];

export default function Contact() {
    return (
        <PublicLayout>
            <Head title="Hubungi SoundTix" />

            <div className="mx-auto max-w-4xl">
                <section className="rounded-2xl border border-slate-200/80 bg-white px-6 py-10 shadow-sm sm:px-10 sm:py-12">
                    <p className="text-sm font-semibold uppercase tracking-[0.16em] text-indigo-600">Hubungi SoundTix</p>
                    <h1 className="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        Pilih channel yang paling nyaman untuk Anda.
                    </h1>
                    <p className="mt-4 max-w-2xl text-base leading-7 text-slate-600">
                        Tim kami siap membantu pertanyaan seputar event, pembelian tiket, hingga kerja sama. Pilih salah satu
                        channel di bawah dan kami akan merespons secepatnya.
                    </p>
                </section>

                <section className="mt-8 grid gap-4 sm:grid-cols-2">
                    {channels.map((channel) => (
                        <a
                            key={channel.name}
                            href={channel.href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="group rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm transition-all hover:border-indigo-600 hover:shadow-sm"
                        >
                            <div className="flex items-start gap-4">
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                                    <Icon name={channel.icon} />
                                </span>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h2 className="font-semibold text-slate-900">{channel.name}</h2>
                                        <span className="text-xs text-slate-400 transition-transform group-hover:translate-x-0.5">â†’</span>
                                    </div>
                                    <p className="mt-0.5 text-sm font-medium text-indigo-600">{channel.value}</p>
                                    <p className="mt-2 text-sm leading-6 text-slate-500">{channel.description}</p>
                                </div>
                            </div>
                        </a>
                    ))}
                </section>

                <section className="mt-8 rounded-2xl bg-indigo-600 px-6 py-8 text-white sm:px-10">
                    <div className="max-w-2xl">
                        <p className="text-sm font-medium text-indigo-200">Jam operasional</p>
                        <h2 className="mt-2 text-xl font-semibold">Seninâ€“Jumat, 09.00â€“18.00 WIB</h2>
                        <p className="mt-3 text-sm leading-6 text-indigo-100">
                            Di luar jam tersebut, email tetap kami terima dan akan diproses pada hari kerja berikutnya.
                            Untuk hal mendesak saat hari acara berlangsung, prioritas utama tetap pada petugas venue.
                        </p>
                    </div>
                </section>
            </div>
        </PublicLayout>
    );
}

function Icon({ name }) {
    const paths = {
        mail: <path d="M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm0 2v.2L12 13l8-5V8l-8 4.5L4 8Z" />,
        chat: <path d="M21 12a8 8 0 0 1-8 8H4.5l2.1-2.1A8 8 0 1 1 21 12ZM8 10h8m-8 4h4" />,
        instagram: (
            <>
                <rect x="3.5" y="3.5" width="17" height="17" rx="4.5" />
                <circle cx="12" cy="12" r="3.6" />
                <circle cx="17" cy="7" r="0.8" fill="currentColor" stroke="none" />
            </>
        ),
        twitter: <path d="M4 4l7.2 9.3L4.4 20h2.3l5.6-5.3 4.1 5.3H20l-7.4-9.6L19.2 4h-2.3l-5.1 4.9L7.8 4H4Zm3 1.6h1.7l7 8.8h-1.7L7 5.6Z" />,
    };

    return (
        <svg aria-hidden="true" className="h-5 w-5" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" viewBox="0 0 24 24">
            {paths[name]}
        </svg>
    );
}