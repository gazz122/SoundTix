import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';

export default function Index({ notifications }) {
    const unreadCount = notifications.data.filter((notification) => !notification.read_at).length;

    return (
        <AppLayout>
            <Head title="Notifikasi" />

            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Notifikasi</h1>
                    <p className="mt-1 text-sm text-slate-500">Informasi terbaru tentang pesanan, pembayaran, dan event Anda.</p>
                </div>
                {unreadCount > 0 && (
                    <button
                        type="button"
                        onClick={() => router.post('/notifications/read-all')}
                        className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100"
                    >
                        Tandai semua sudah dibaca
                    </button>
                )}
            </div>

            <section className="mt-8 overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                {notifications.data.length > 0 ? (
                    notifications.data.map((notification) => <NotificationItem key={notification.id} notification={notification} />)
                ) : (
                    <div className="px-6 py-14 text-center">
                        <p className="font-medium text-slate-800">Belum ada notifikasi</p>
                        <p className="mt-2 text-sm text-slate-500">Kami akan memberi tahu Anda tentang aktivitas penting di akun ini.</p>
                    </div>
                )}
            </section>

            {notifications.links?.length > 3 && <Pagination links={notifications.links} />}
        </AppLayout>
    );
}

function NotificationItem({ notification }) {
    const content = (
        <div className={`flex gap-4 px-6 py-5 sm:px-8 ${notification.read_at ? '' : 'bg-indigo-50/50'}`}>
            <span className={`mt-1 h-2 w-2 shrink-0 rounded-full ${notification.read_at ? 'bg-slate-200' : 'bg-indigo-600'}`} />
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 className="font-semibold text-slate-900">{notification.data.title}</h2>
                    <time className="text-xs text-slate-400">{notification.created_at}</time>
                </div>
                <p className="mt-1 text-sm leading-6 text-slate-600">{notification.data.message}</p>
                {notification.data.action_url && (
                    <span className="mt-3 inline-block text-sm font-medium text-indigo-600">{notification.data.action_label ?? 'Lihat detail'} →</span>
                )}
            </div>
        </div>
    );

    if (notification.data.action_url) {
        return (
            <Link
                href={notification.data.action_url}
                onClick={(event) => {
                    if (!notification.read_at) {
                        event.preventDefault();
                        router.post(`/notifications/${notification.id}/read`, {}, {
                            onSuccess: () => router.visit(notification.data.action_url),
                        });
                    }
                }}
                className="block border-b border-slate-200/80 transition-colors last:border-b-0 hover:bg-slate-50"
            >
                {content}
            </Link>
        );
    }

    return (
        <div className="border-b border-slate-200/80 last:border-b-0">
            {content}
            {!notification.read_at && (
                <button type="button" onClick={() => router.post(`/notifications/${notification.id}/read`)} className="mb-4 ml-12 text-xs font-medium text-indigo-600 hover:text-indigo-700">
                    Tandai sudah dibaca
                </button>
            )}
        </div>
    );
}

function Pagination({ links }) {
    return (
        <nav className="mt-6 flex flex-wrap justify-center gap-2">
            {links.map((link, index) => (
                <Link
                    key={index}
                    href={link.url ?? '#'}
                    className={`rounded-lg border px-3.5 py-2 text-sm ${link.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'}`}
                >
                    {Number.isFinite(Number(link.label)) ? link.label : link.label.includes('Previous') ? '←' : '→'}
                </Link>
            ))}
        </nav>
    );
}