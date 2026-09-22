import { Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/components/AppLayout';
import StatusBadge from '@/components/StatusBadge';
import { formatDate } from '@/lib/format';

export default function Index({ events }) {
    const { flash } = usePage().props;

    return (
        <AppLayout>
            <div className="flex flex-wrap items-center justify-between gap-4 mb-8">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Event Saya</h1>
                    <p className="mt-1 text-sm text-slate-500">Kelola dan ajukan event Anda untuk verifikasi.</p>
                </div>
                <Link
                    href="/organizer/events/create"
                    className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm"
                >
                    + Buat Event
                </Link>
            </div>

            {flash.success && <Flash type="success">{flash.success}</Flash>}
            {flash.error && <Flash type="error">{flash.error}</Flash>}

            {events.length === 0 ? (
                <EmptyState />
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    {events.map((event) => (
                        <EventItem key={event.id} event={event} />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}

function EventItem({ event }) {
    const editable = event.status === 'draft' || event.status === 'rejected';

    return (
        <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-5 flex flex-col gap-4">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="font-semibold text-slate-900 truncate">{event.title}</h2>
                    <p className="mt-0.5 text-sm text-slate-500">
                        {event.venue_name} · {event.city}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-400">{formatDate(event.event_date)}</p>
                </div>
                <StatusBadge status={event.status} />
            </div>

            {event.rejection_reason && (
                <div className="rounded-lg border border-rose-200 bg-rose-50/60 px-3 py-2 text-xs text-rose-700">
                    Alasan penolakan: {event.rejection_reason}
                </div>
            )}

            <p className="text-sm text-slate-600">
                {event.performers_count > 0
                    ? `Performer: ${event.performers.join(', ')}`
                    : 'Belum ada performer ditambahkan.'}
            </p>

            <div className="flex flex-wrap items-center gap-2 mt-auto">
                {editable && (
                    <>
                        <Link
                            href={`/organizer/events/${event.id}/edit`}
                            className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-4 rounded-lg transition-all"
                        >
                            Edit
                        </Link>
                        <button
                            type="button"
                            onClick={() =>
                                router.post(`/organizer/events/${event.id}/submit`, {}, { preserveScroll: true })
                            }
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm"
                        >
                            {event.status === 'rejected' ? 'Ajukan Ulang' : 'Ajukan Verifikasi'}
                        </button>
                    </>
                )}

                <Link
                    href={`/organizer/events/${event.id}/categories`}
                    className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-4 rounded-lg transition-all"
                >
                    Kategori Tiket ({event.ticket_categories_count})
                </Link>

                {event.status === 'pending' && (
                    <span className="text-sm text-slate-500">Menunggu verifikasi oleh admin...</span>
                )}

                {event.status === 'published' && (
                    <Link
                        href={`/events/${event.slug}`}
                        className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-4 rounded-lg transition-all"
                    >
                        Lihat Halaman →
                    </Link>
                )}

                <Link
                    href={`/organizer/events/${event.id}`}
                    className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-4 rounded-lg transition-all"
                >
                    Detail
                </Link>
            </div>
        </div>
    );
}

function Flash({ type, children }) {
    const styles = {
        success: 'border-emerald-200 bg-emerald-50/60 text-emerald-700',
        error: 'border-rose-200 bg-rose-50/60 text-rose-700',
    };
    return <div className={`mb-6 rounded-lg border px-4 py-3 text-sm ${styles[type]}`}>{children}</div>;
}

function EmptyState() {
    return (
        <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm p-10 text-center">
            <p className="text-sm font-medium text-slate-700">Anda belum memiliki event.</p>
            <p className="mt-1 text-sm text-slate-500">Buat event pertama Anda dan mulai jual tiket.</p>
            <Link
                href="/organizer/events/create"
                className="mt-4 inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm"
            >
                + Buat Event
            </Link>
        </div>
    );
}