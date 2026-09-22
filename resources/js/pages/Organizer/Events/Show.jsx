import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/components/AppLayout';
import StatusBadge from '@/components/StatusBadge';
import { formatDate, rupiah } from '@/lib/format';

export default function Show({ event }) {
    const { flash } = usePage().props;
    const [modalOpen, setModalOpen] = useState(false);

    return (
        <AppLayout>
            <div className="mb-8">
                <Link href="/organizer/events" className="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                    ← Kembali ke Event Saya
                </Link>
            </div>

            {flash.success && <Flash type="success">{flash.success}</Flash>}
            {flash.error && <Flash type="error">{flash.error}</Flash>}

            <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <h1 className="text-2xl font-semibold text-slate-900 break-words">{event.title}</h1>
                        <p className="mt-1 text-sm text-slate-500">{event.venue_name} · {event.city}</p>
                    </div>
                    <StatusBadge status={event.status} />
                </div>

                <dl className="mt-6 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <Info label="Tanggal" value={event.event_date ? formatDate(event.event_date) : '-'} />
                    <Info label="Waktu" value={event.start_time} />
                    <Info label="Alamat" value={event.address} />
                    <Info label="Kategori Tiket" value={String(event.ticket_categories_count ?? 0)} />
                </dl>

                {event.performers?.length > 0 && (
                    <p className="mt-4 text-sm text-slate-600">
                        Performer: <span className="font-medium text-slate-800">{event.performers.join(', ')}</span>
                    </p>
                )}

                {event.status === 'pending_cancellation' && (
                    <div className="mt-6 rounded-lg border border-orange-200 bg-orange-50/60 px-4 py-3 text-sm text-orange-700">
                        <p className="font-medium">Pengajuan pembatalan sedang menunggu persetujuan admin.</p>
                        {event.cancellation_reason && (
                            <p className="mt-1">Alasan: {event.cancellation_reason}</p>
                        )}
                    </div>
                )}

                {event.cancellable && (
                    <div className="mt-6 flex justify-end">
                        <button
                            type="button"
                            onClick={() => setModalOpen(true)}
                            className="bg-white hover:bg-rose-50 text-rose-600 border border-rose-300 font-medium py-2.5 px-5 rounded-lg transition-all"
                        >
                            Ajukan Pembatalan Event
                        </button>
                    </div>
                )}
            </div>

            <div className="mt-8 bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm">
                <div className="px-6 py-5 border-b border-slate-200/80">
                    <h2 className="font-semibold text-slate-900">Refund Tiket Event Ini</h2>
                    <p className="mt-0.5 text-sm text-slate-500">
                        Refund yang disetujui akan mengurangi dana penarikan event ini.
                    </p>
                </div>
                {event.refunds.length === 0 ? (
                    <p className="px-6 py-8 text-center text-sm text-slate-400">Belum ada refund untuk event ini.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-100 text-sm">
                            <thead className="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3 text-left">Customer</th>
                                    <th className="px-4 py-3 text-left">Order</th>
                                    <th className="px-4 py-3 text-right">Nominal</th>
                                    <th className="px-4 py-3 text-left">Status</th>
                                    <th className="px-4 py-3 text-left">Alasan</th>
                                    <th className="px-4 py-3 text-left">Diajukan</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {event.refunds.map((refund) => (
                                    <tr key={refund.id} className="hover:bg-slate-50/50">
                                        <td className="px-4 py-2.5 font-medium text-slate-800">{refund.customer_name}</td>
                                        <td className="px-4 py-2.5 font-mono text-slate-600">{refund.order_code}</td>
                                        <td className="px-4 py-2.5 text-right font-medium tabular-nums">{rupiah(refund.amount)}</td>
                                        <td className="px-4 py-2.5">{refund.status_label}</td>
                                        <td className="px-4 py-2.5 text-slate-600">{refund.reason}</td>
                                        <td className="px-4 py-2.5 text-slate-500">{refund.created_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {modalOpen && <CancelModal event={event} onClose={() => setModalOpen(false)} />}
        </AppLayout>
    );
}

function CancelModal({ event, onClose }) {
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    function submit() {
        if (reason.trim().length === 0) return;
        setSubmitting(true);
        router.post(`/organizer/events/${event.id}/cancel`, { reason }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div className="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <h3 className="text-lg font-semibold text-slate-900">Konfirmasi Pembatalan Event</h3>
                <p className="mt-1 text-sm text-slate-500">
                    Pengajuan ini akan menunggu persetujuan admin. Jika disetujui, semua pesanan yang sudah lunas
                    akan di-refund otomatis kepada customer.
                </p>
                <label htmlFor="cancellation-reason" className="mt-4 block text-sm font-medium text-slate-700">
                    Alasan pembatalan <span className="text-rose-500">*</span>
                </label>
                <textarea
                    id="cancellation-reason"
                    rows={4}
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                    className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-600 focus:outline-none focus:ring-1 focus:ring-indigo-600"
                    placeholder="Contoh: pembatalan karena calon artis berhalangan hadir."
                />
                <div className="mt-5 flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 text-sm font-medium py-2 px-5 rounded-lg transition-all"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        disabled={submitting || reason.trim().length === 0}
                        onClick={submit}
                        className="bg-rose-600 hover:bg-rose-700 text-white text-sm font-medium py-2 px-5 rounded-lg transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {submitting ? 'Mengirim…' : 'Kirim Pengajuan Pembatalan'}
                    </button>
                </div>
            </div>
        </div>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <dt className="text-xs text-slate-500 uppercase tracking-wider">{label}</dt>
            <dd className="mt-0.5 font-medium text-slate-800">{value}</dd>
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