import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/components/AppLayout';
import StatCard from '@/components/ui/StatCard';

export default function Admin({ stats, pending_events: pendingEvents }) {
    const [rejectEvent, setRejectEvent] = useState(null);

    return (
        <AppLayout>
            <Header name="Dashboard System Admin" description="Statistik makro platform dan pendapatan global." />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Total Pengguna" value={stats.total_users} hint="Semua role" />
                <StatCard label="Total Event" value={stats.total_events} hint="Semua status" />
                <StatCard label="Event Menunggu Approval" value={stats.pending_events} hint="Status PENDING" />
                <StatCard label="Pendapatan Platform" value={rupiah(stats.revenue)} hint="Dari pesanan PAID" />
            </div>

            <Panel title="Event Menunggu Verifikasi" action="/admin/events">
                {pendingEvents.length === 0 ? (
                    <p className="py-8 text-center text-sm text-slate-500">Tidak ada event yang menunggu verifikasi.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Event</th>
                                    <th className="px-4 py-3 font-medium">Penyelenggara</th>
                                    <th className="px-4 py-3 font-medium">Jadwal</th>
                                    <th className="px-4 py-3 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {pendingEvents.map((event) => (
                                    <tr key={event.id} className="align-middle hover:bg-slate-50/70">
                                        <td className="px-4 py-4">
                                            <p className="font-medium text-slate-900">{event.title}</p>
                                            <p className="mt-1 text-xs text-slate-500">{event.venue_name} · {event.city}</p>
                                        </td>
                                        <td className="px-4 py-4 text-slate-600">{event.organizer}</td>
                                        <td className="whitespace-nowrap px-4 py-4 text-slate-600">{event.event_date}</td>
                                        <td className="px-4 py-4">
                                            <div className="flex justify-end gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => router.post(`/admin/events/${event.id}/approve`, {}, { preserveScroll: true })}
                                                    className="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white shadow-sm transition-colors hover:bg-indigo-700"
                                                >
                                                    Approve
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setRejectEvent(event)}
                                                    className="rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-medium text-rose-700 transition-colors hover:bg-rose-50"
                                                >
                                                    Reject
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Panel>

            {rejectEvent && <RejectModal event={rejectEvent} onClose={() => setRejectEvent(null)} />}
        </AppLayout>
    );
}

function Header({ name, description }) {
    return (
        <div className="mb-8">
            <h1 className="text-2xl font-semibold text-slate-900">{name}</h1>
            <p className="mt-1 text-sm text-slate-500">{description}</p>
        </div>
    );
}

function Panel({ title, action, children }) {
    return (
        <section className="mt-8 overflow-hidden rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
            <div className="flex items-center justify-between gap-4">
                <h2 className="text-sm font-semibold text-slate-900">{title}</h2>
                {action && <a href={action} className="text-sm font-medium text-indigo-600 hover:text-indigo-700">Lihat semua</a>}
            </div>
            {children}
        </section>
    );
}

function RejectModal({ event, onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({ rejection_reason: '' });

    function submit(submitEvent) {
        submitEvent.preventDefault();
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
            <div className="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl border border-slate-200/80 bg-white p-6 shadow-sm">
                <h3 className="text-lg font-semibold text-slate-900">Reject Event</h3>
                <p className="mt-1 text-sm text-slate-500">Berikan alasan penolakan untuk {event.title}.</p>
                <form onSubmit={submit} className="mt-4 space-y-3">
                    <textarea
                        value={data.rejection_reason}
                        onChange={(changeEvent) => setData('rejection_reason', changeEvent.target.value)}
                        rows={3}
                        placeholder="Contoh: Deskripsi event perlu dilengkapi."
                        className="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-slate-900 placeholder-slate-400 transition-all focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                    />
                    {errors.rejection_reason && <p className="text-sm text-rose-600">{errors.rejection_reason}</p>}
                    <div className="flex justify-end gap-2">
                        <button type="button" onClick={onClose} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Batal</button>
                        <button type="submit" disabled={processing} className="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-60">{processing ? 'Menyimpan...' : 'Reject Event'}</button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function rupiah(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);
}