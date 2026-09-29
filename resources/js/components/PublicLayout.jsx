import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Logo from '@/components/Logo';

const TABS = [
    { label: 'Beranda', icon: 'M3 10.75 12 3l9 7.75V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z', href: '/' },
    { label: 'Tiket Saya', icon: 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm2 4V9h3m-3 4h3m-3 4h3m7-8h.01M16 14h2', href: '/orders' },
    { label: 'Notifikasi', icon: 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4', href: '/notifications' },
    { label: 'Profil', icon: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm-7 9a7 7 0 0 1 14 0', href: '/dashboard' },
];

export default function PublicLayout({ children }) {
    const user = usePage().props.auth?.user;
    const { url } = usePage();

    return (
        <div className="min-h-screen overflow-x-hidden bg-slate-50 text-slate-800 font-sans">
            <header className="sticky top-0 z-20 border-b border-slate-300 bg-sky-100">
                <div className="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between gap-3 py-3 min-w-0 sm:gap-4 md:flex-1 md:gap-8">
                        <Link href="/" className="flex shrink-0 items-center gap-2.5 md:flex-1">
                            <Logo iconClassName="h-9 w-9" textClassName="hidden text-lg font-semibold min-[400px]:inline" />
                        </Link>

                        <div className="hidden md:absolute md:left-1/2 md:-translate-x-1/2 md:block md:w-full md:max-w-md">
                            <SearchBar />
                        </div>

                        <nav className="flex shrink-0 items-center gap-2 sm:gap-3 md:flex-1 md:justify-end">
                            {user ? (
                                <Link
                                    href="/dashboard"
                                    className="bg-white text-indigo-700 hover:bg-indigo-50 font-medium py-2 px-3 sm:px-5 rounded-lg transition-all shadow-sm text-sm sm:text-base"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href="/login"
                                        className="bg-white/60 hover:bg-white text-slate-700 border border-slate-300 py-2 px-3 sm:px-5 rounded-lg transition-all text-sm sm:text-base"
                                    >
                                        Masuk
                                    </Link>
                                    <Link
                                        href="/register"
                                        className="bg-white text-indigo-700 hover:bg-indigo-50 font-medium py-2 px-3 sm:px-5 rounded-lg transition-all shadow-sm text-sm sm:text-base"
                                    >
                                        Daftar
                                    </Link>
                                </>
                            )}
                        </nav>
                    </div>

                    <div className="pb-3 md:hidden">
                        <SearchBar />
                    </div>
                </div>
            </header>

            <main className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">{children}</main>

            {user && (
                <nav aria-label="Navigasi utama" className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200/80 bg-white/95 backdrop-blur-xl lg:hidden" style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}>
                    <div className="mx-auto grid max-w-md grid-cols-4">
                        {TABS.map((tab) => {
                            const active = url === tab.href || url.startsWith(`${tab.href}/`);
                            return (
                                <Link
                                    key={tab.label}
                                    href={tab.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={`flex flex-col items-center gap-0.5 py-2.5 text-[11px] font-medium transition-colors ${
                                        active ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600'
                                    }`}
                                >
                                    <svg aria-hidden="true" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth={active ? 2.2 : 1.8} viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" d={tab.icon} />
                                    </svg>
                                    {tab.label}
                                </Link>
                            );
                        })}
                    </div>
                </nav>
            )}

            <footer className={`border-t border-slate-200/80 bg-white ${user ? 'pb-20 lg:pb-0' : ''}`}>
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10">
                    <div className="grid gap-8 md:grid-cols-3">
                        <div>
                            <Link href="/" className="flex items-center gap-2.5">
                                <Logo iconClassName="h-10 w-10" textClassName="text-lg font-semibold" />
                            </Link>
                            <p className="mt-3 max-w-xs text-sm text-slate-500">
                                Platform e-ticketing konser digital. Temukan event, pesan tiket, dan masuk venue
                                dengan mudah lewat tiket QR aman.
                            </p>
                        </div>

                        <div>
                            <h4 className="text-sm font-semibold text-slate-900">Jelajahi SoundTix</h4>
                            <ul className="mt-3 space-y-2 text-sm text-slate-500">
                                <li><Link href="/about" className="hover:text-indigo-600 transition-colors">Tentang SoundTix</Link></li>
                                <li><Link href="/" className="hover:text-indigo-600 transition-colors">Mencari Event</Link></li>
                                <li><Link href="/organizer-guide" className="hover:text-indigo-600 transition-colors">Panduan Penyelenggara</Link></li>
                            </ul>
                        </div>

                        <div>
                            <h4 className="text-sm font-semibold text-slate-900">Bantuan</h4>
                            <ul className="mt-3 space-y-2 text-sm text-slate-500">
                                <li>
                                    <Link href="/contact" className="hover:text-indigo-600 transition-colors">
                                        Hubungi SoundTix
                                    </Link>
                                </li>
                                <li><Link href="/organizer-info" className="hover:text-indigo-600 transition-colors">Informasi Penyelenggara</Link></li>
                            </ul>
                        </div>
                    </div>

                    <div className="mt-8 border-t border-slate-200/80 pt-6 text-xs text-slate-400">
                        <p>© {new Date().getFullYear()} SoundTix. Seluruh hak cipta dilindungi.</p>
                    </div>
                </div>
            </footer>
        </div>
    );
}

function SearchBar() {
    const { url } = usePage();
    const [q, setQ] = useState(queryParam(url, 'q') ?? '');
    const [composing, setComposing] = useState(false);

    function submit(event) {
        event.preventDefault();
        if (composing) return;
        router.get('/', { q }, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="relative">
            <svg
                className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
                type="search"
                value={q}
                onChange={(event) => setQ(event.target.value)}
                onCompositionStart={() => setComposing(true)}
                onCompositionEnd={() => setComposing(false)}
                placeholder="Cari event, artis, atau lokasi..."
                aria-label="Cari event"
                className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
            />
        </form>
    );
}

function queryParam(url, key) {
    try {
        return new URL(url, window.location.origin).searchParams.get(key);
    } catch {
        return null;
    }
}