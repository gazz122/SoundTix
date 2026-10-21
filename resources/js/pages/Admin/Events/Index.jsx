import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/components/AppLayout';
import StatusBadge from '@/components/StatusBadge';

const tabs = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'all', label: 'Semua' },
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'completed', label: 'Selesai' },
    { value: 'rejected', label: 'Ditolak' },
];

export default function Index({ events, current_status }) {
    const { flash } = usePage().props;
    const [rejectEvent, setRejectEvent] = useState(null);

    function changeTab(status) {
        router.get('/admin/events', { status }, { preserveState: true, replace: true });
    }

    return (
        <AppLayout>
            <div className="mb-8">
                <h1 className="text-2xl font-semibold text-slate-900">Verifikasi Event</h1>
                <p className="mt-1 text-sm text-slate-500">Setujui atau tolak event yang diajukan organizer.</p>
            </div>

            {flash.success && (
                <div className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50/60 px-4 py-3 text-sm text-emerald-700">
                    {flash.success}
                </div>
            )}

            <div className="mb-6 flex flex-wrap gap-2">
                {tabs.map((tab) => (
                    <button
                        key={tab.value}
                        type="button"
                        onClick={() => changeTab(tab.value)}
                        className={`text-sm font-medium px-4 py-2 rounded-lg transition-all ${
                            current_status === tab.value
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'bg-white hover:bg-slate-100 text-slate-700 border border-slate-300'
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {events.data.length === 0 ? (
                <div className="bg-white border border-slate-200/80 rounded-xl shadow-sm p-10 text-center">
                    <p className="text-sm text-slate-500">Tidak ada event pada filter ini.</p>
                </div>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    {events.data.map((event) => (
                        <EventItem
                            key={event.id}
                            event={event}
                            onReject={() => setRejectEvent(event)}
                        />
                    ))}
                </div>
            )}

            {rejectEvent && (
                <RejectModal event={rejectEvent} onClose={() => setRejectEvent(null)} />
            )}
        </AppLayout>
    );
}

function EventItem({ event, onReject }) {
    const pending = event.status === 'pending';

    return (
        <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-5 flex flex-col gap-3">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="font-semibold text-slate-900 truncate">{event.title}</h2>
                    <p className="mt-0.5 text-sm text-slate-500">
                        {event.venue_name} · {event.city} · {event.event_date}
                    </p>
                    <p className="text-xs text-slate-400">
                        Diajukan oleh {event.organizer.name} · {event.performers.length} performer ·{' '}
                        {event.ticket_categories_count} kategori tiket
                    </p>
                </div>
                <StatusBadge status={event.status} />
            </div>

            {event.rejection_reason && (
                <div className="rounded-lg border border-rose-200 bg-rose-50/60 px-3 py-2 text-xs text-rose-700">
                    Alasan penolakan: {event.rejection_reason}
                </div>
            )}

            <div className="flex flex-wrap items-center gap-2 mt-auto">
                <Link
                    href={`/events/${event.slug}`}
                    target="_blank"
                    className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-4 rounded-lg transition-all"
                >
                    {event.status === 'published' ? 'Lihat Halaman' : 'Pratinjau'}
                </Link>
                {pending && (
                    <>
                        <button
                            type="button"
                            onClick={() =>
                                router.post(`/admin/events/${event.id}/approve`, {}, { preserveScroll: true })
                            }
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm"
                        >
                            Setujui
                        </button>
                        <button
                            type="button"
                            onClick={onReject}
                            className="bg-white hover:bg-rose-50 text-rose-700 border border-rose-300 text-sm font-medium py-2 px-4 rounded-lg transition-all"
                        >
                            Tolak
                        </button>
                    </>
                )}
            </div>
        </div>
    );
}

function RejectModal({ event, onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({ rejection_reason: '' });

    function submit(modalEvent) {
        modalEvent.preventDefault();
        post(`/admin/events/${event.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-slate-900/40" onClick={onClose} />
            <div className="relative max-h-[90vh] w-full max-w-md overflow-y-auto bg-white border border-slate-200/80 rounded-xl shadow-sm p-6">
                <h3 className="text-lg font-semibold text-slate-900">Tolak Event</h3>
                <p className="mt-1 text-sm text-slate-500">
                    Tolak <span className="font-medium text-slate-700">{event.title}</span> dan berikan alasan yang jelas.
                </p>
                <form onSubmit={submit} className="mt-4 space-y-3">
                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">Alasan Penolakan</span>
                        <textarea
                            value={data.rejection_reason}
                            onChange={(changeEvent) => setData('rejection_reason', changeEvent.target.value)}
                            rows={3}
                            placeholder="cth: Deskripsi event terlalu singkat, mohon dilengkapi."
                            className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                        />
                        {errors.rejection_reason && <p className="text-sm text-rose-600">{errors.rejection_reason}</p>}
                    </label>
                    <div className="flex items-center justify-end gap-2 pt-1">
                        <button
                            type="button"
                            onClick={onClose}
                            className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-4 rounded-lg transition-all"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm disabled:opacity-60"
                        >
                            {processing ? 'Menyimpan...' : 'Tolak Event'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}