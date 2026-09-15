import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const navigation = {
    customer: [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Cari Event', href: '/' },
        { label: 'Tiket Saya', href: '/orders' },
    ],
    organizer: [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Event Saya', href: '/organizer/events' },
        { label: 'Buat Event', href: '/organizer/events/create' },
        { label: 'Laporan', href: '/organizer/reports' },
    ],
    gatekeeper: [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Scanner', href: '/gatekeeper/scanner' },
    ],
    admin: [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Verifikasi Event', href: '/admin/events' },
        { label: 'Kelola Pengguna', href: '/admin/users' },
        { label: 'Scanner Tiket', href: '/gatekeeper/scanner' },
    ],
};

export default function AppLayout({ children }) {
    const { auth } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const user = auth?.user;
    const items = user ? navigation[user.role] ?? [] : [];

    return (
        <div className="min-h-screen bg-slate-50 text-slate-800 font-sans">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex min-h-screen">
                <aside className="hidden lg:flex w-64 flex-col gap-1 py-8 pr-8">
                    <Brand />
                    <nav className="flex flex-col gap-1 mt-8">
                        {items.map((item) => (
                            <NavItem key={item.label} {...item} />
                        ))}
                    </nav>
                    <div className="mt-auto pt-8">
                        <LogoutButton />
                    </div>
                </aside>

                <div className="flex-1 flex flex-col min-w-0">
                    <header className="lg:hidden flex items-center justify-between py-4">
                        <Brand compact />
                        <button
                            type="button"
                            onClick={() => setSidebarOpen((open) => !open)}
                            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium"
                        >
                            Menu
                        </button>
                    </header>

                    {sidebarOpen && (
                        <div className="lg:hidden rounded-xl border border-slate-200/80 bg-white p-4 mb-4 shadow-sm">
                            <nav className="flex flex-col gap-1">
                                {items.map((item) => (
                                    <NavItem key={item.label} {...item} />
                                ))}
                                <LogoutButton />
                            </nav>
                        </div>
                    )}

                    <main className="flex-1 py-8">{children}</main>
                </div>
            </div>
        </div>
    );
}

function Brand() {
    return (
        <Link href="/dashboard" className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-lg shadow-sm">
                S
            </div>
            <div>
                <p className="text-lg font-semibold leading-tight text-slate-900">SoundTix</p>
                <p className="text-xs text-slate-500">Konser Digital</p>
            </div>
        </Link>
    );
}

function NavItem({ label, href }) {
    if (!href) {
        return (
            <span className="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-400 cursor-not-allowed">
                {label}
                <span className="text-[10px] font-medium uppercase tracking-wider bg-slate-100 border border-slate-200 text-slate-500 px-2 py-0.5 rounded-md">
                    Segera
                </span>
            </span>
        );
    }

    return (
        <Link
            href={href}
            className="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-white hover:border hover:border-slate-200/80 hover:text-slate-900 hover:shadow-sm transition-all"
        >
            {label}
        </Link>
    );
}

function LogoutButton() {
    return (
        <button
            type="button"
            onClick={() => router.post('/logout')}
            className="w-full bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 py-2.5 px-5 rounded-lg transition-all"
        >
            Keluar
        </button>
    );
}