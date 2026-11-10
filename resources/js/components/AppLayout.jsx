import { Link, router, usePage } from '@inertiajs/react';
import { Bell, Menu, Plus } from 'lucide-react';
import { useState } from 'react';
import Logo from '@/components/Logo';
import { RoleBadge } from '@/components/RoleBadge';

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
        { label: 'Refund', href: '/organizer/refunds' },
        { label: 'Penarikan Dana', href: '/organizer/payouts' },
    ],
    gatekeeper: [
        { label: 'Scan Tiket', href: '/dashboard/scan' },
    ],
    admin: [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Verifikasi Event', href: '/admin/events' },
        { label: 'Kelola Pengguna', href: '/admin/users' },
        { label: 'Penarikan Dana', href: '/admin/payouts' },
        { label: 'Refund', href: '/admin/refunds' },
        { label: 'Kelola FAQ', href: '/admin/faqs' },
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
                        {user && (
                            <div className="mb-3 flex items-center gap-3 rounded-lg border border-slate-200/80 bg-black px-3 py-2 shadow-sm">
                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                                    {user.name.charAt(0).toUpperCase()}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-slate-900">{user.name}</p>
                                    <RoleBadge role={user.role} />
                                </div>
                            </div>
                        )}
                        <LogoutButton />
                    </div>
                </aside>

                <div className="flex-1 flex flex-col min-w-0">
                    <header className="lg:hidden flex items-center justify-between py-4">
                        <Brand compact />
                        <div className="flex items-center gap-3">
                            <NotificationBell />
<button
                                    type="button"
                                    onClick={() => setSidebarOpen((open) => !open)}
                                    className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium"
                                    aria-label="Buka menu"
                                >
                                    <Menu aria-hidden="true" className="h-5 w-5" />
                                </button>
                        </div>
                    </header>

                    <div className="hidden justify-end py-4 lg:flex">
                        <NotificationBell />
                    </div>

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

                    {user?.role === 'organizer' && (
                        <Link
                            href="/organizer/events/create"
                            className="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-xl shadow-slate-900/10 transition-transform hover:scale-105 lg:hidden"
                            aria-label="Buat event baru"
                        >
                            <Plus aria-hidden="true" className="h-7 w-7" />
                        </Link>
                    )}
                </div>
            </div>
        </div>
    );
}

function Brand({ compact = false }) {
    return (
        <Link href="/dashboard" className="flex items-center gap-3">
            <Logo
                iconClassName={`h-10 w-10 ${compact ? '!h-9 !w-9' : ''}`}
                textClassName={`font-semibold leading-tight ${compact ? '!text-base' : 'text-lg'}`}
                subText="Konser Digital"
            />
        </Link>
    );
}

function NavItem({ label, href }) {
    if (!href) {
        return (
            <span className="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-slate-400 cursor-not-allowed">
                {label}
                <span className="text-[11px] font-medium uppercase tracking-wider bg-slate-100 border border-slate-200 text-slate-500 px-2 py-0.5 rounded-md">
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

function NotificationBell() {
    const notifications = usePage().props.auth?.notifications;
    const unreadCount = notifications?.unread_count ?? 0;

    return (
        <Link
            href="/notifications"
            aria-label={unreadCount > 0 ? `${unreadCount} notifikasi belum dibaca` : 'Notifikasi'}
            title="Notifikasi"
            className="relative flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 transition-colors hover:border-indigo-300 hover:text-indigo-600"
        >
            <Bell aria-hidden="true" className="h-5 w-5" />
            {unreadCount > 0 && (
                <span className="absolute -right-1 -top-1 flex min-w-5 items-center justify-center rounded-full bg-indigo-600 px-1.5 py-0.5 text-[11px] font-semibold text-white">
                    {unreadCount > 9 ? '9+' : unreadCount}
                </span>
            )}
        </Link>
    );
}