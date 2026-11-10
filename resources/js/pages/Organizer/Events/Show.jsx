import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, GripVertical } from 'lucide-react';
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
                    <Info label="Mulai Acara" value={event.start_time} />
                    <Info label="Alamat" value={event.address} />
                    <Info label="Kategori Tiket" value={String(event.ticket_categories_count ?? 0)} />
                    <Info label="Buka Pintu" value={event.open_gate ? `${event.open_gate_label} WIB` : 'Belum diatur'} />
                    <Info label="Tutup Pintu" value={event.close_gate ? `${event.close_gate_label} WIB` : 'Belum diatur'} />
                </dl>

                {event.duration_type === 'multi_day' && (
                    <div className="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <h2 className="text-sm font-semibold text-slate-800">Jadwal Pintu per Hari</h2>
                        <div className="mt-3 space-y-2">
                            {event.event_days.map((day) => (
                                <p key={day.day_number} className="text-sm text-slate-600">
                                    <span className="font-medium text-slate-800">
                                        Day {day.day_number} · {formatDate(day.date)}
                                    </span>
                                    {' · '}
                                    Buka {day.open_gate ? `${day.open_gate} WIB` : 'Belum diatur'}
                                    {' · '}
                                    Tutup {day.close_gate ? `${day.close_gate} WIB` : 'Belum diatur'}
                                </p>
                            ))}
                        </div>
                        <p className="mt-3 text-xs text-slate-500">
                            Jam mulai acara ({event.start_time}) terpisah dari jam buka dan tutup pintu.
                        </p>
                    </div>
                )}

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
                    <h2 className="font-semibold text-slate-900">Rundown Acara</h2>
                    <p className="mt-0.5 text-sm text-slate-500">
                        Susunan jalannya acara (sesi, jam mulai & selesai) yang ditampilkan ke vendor & gatekeeper.
                    </p>
                </div>
                <RundownSection event={event} />
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
            <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
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
                        {submitting ? 'Mengirim...' : 'Kirim Pengajuan Pembatalan'}
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

function RundownSection({ event }) {
    const isMultiDay = event.duration_type === 'multi_day';
    const lineup = event.lineup ?? [];
    const days = event.event_days ?? [];
    const [draggingId, setDraggingId] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        performer_id: '',
        event_day_id: isMultiDay ? String(days[0]?.id ?? '') : '',
        title: '',
        start_time: '',
        end_time: '',
        description: '',
    });

    function add(eventSubmit) {
        eventSubmit.preventDefault();
        post(`/organizer/events/${event.id}/rundowns`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    }

    function submitOrder(ids) {
        router.put(
            `/organizer/events/${event.id}/rundowns/reorder`,
            { ids },
            { preserveScroll: true, onFinish: () => setDraggingId(null) },
        );
    }

    function move(items, index, direction) {
        const target = index + direction;
        if (target < 0 || target >= items.length) {
            return;
        }

        const ids = items.map((item) => item.id);
        [ids[index], ids[target]] = [ids[target], ids[index]];
        submitOrder(ids);
    }

    function drop(items, targetId) {
        if (draggingId === null || draggingId === targetId) {
            return;
        }

        const ids = items.map((item) => item.id);
        const from = ids.indexOf(draggingId);
        const to = ids.indexOf(targetId);

        if (from === -1 || to === -1) {
            return;
        }

        ids.splice(to, 0, ids.splice(from, 1)[0]);
        submitOrder(ids);
    }

    const groups = isMultiDay
        ? days.map((day) => ({
              key: day.id,
              label: `Day ${day.day_number} \u00b7 ${formatDate(day.date)}`,
              items: event.rundowns.filter((rundown) => rundown.event_day_id === day.id),
          }))
        : [{ key: 'single', label: null, items: event.rundowns }];

    return (
        <div className="p-6">
            <form onSubmit={add} className="grid gap-4 rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:grid-cols-2">
                <RundownFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    lineup={lineup}
                    days={days}
                    isMultiDay={isMultiDay}
                />
                <div className="flex items-end sm:col-span-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-5 rounded-lg transition-all shadow-sm disabled:opacity-60"
                    >
                        {processing ? 'Menyimpan...' : 'Tambah Sesi'}
                    </button>
                </div>
            </form>

            {event.rundowns.length === 0 ? (
                <p className="py-8 text-center text-sm text-slate-400">Belum ada sesi rundown untuk event ini.</p>
            ) : (
                <div className="mt-5 space-y-5">
                    {groups
                        .filter((group) => group.items.length > 0)
                        .map((group) => (
                            <div key={group.key}>
                                {group.label && (
                                    <p className="mb-2 text-sm font-semibold text-indigo-600">{group.label}</p>
                                )}
                                <ul className="space-y-2.5">
                                    {group.items.map((rundown, index) => (
                                        <RundownRow
                                            key={rundown.id}
                                            event={event}
                                            rundown={rundown}
                                            lineup={lineup}
                                            days={days}
                                            isMultiDay={isMultiDay}
                                            isFirst={index === 0}
                                            isLast={index === group.items.length - 1}
                                            isDragging={draggingId === rundown.id}
                                            onMoveUp={() => move(group.items, index, -1)}
                                            onMoveDown={() => move(group.items, index, 1)}
                                            onDragStart={(dragEvent) => {
                                                dragEvent.dataTransfer.effectAllowed = 'move';
                                                dragEvent.dataTransfer.setData('text/plain', String(rundown.id));
                                                setDraggingId(rundown.id);
                                            }}
                                            onDragEnd={() => setDraggingId(null)}
                                            onDragOver={(dragEvent) => {
                                                dragEvent.preventDefault();
                                                dragEvent.dataTransfer.dropEffect = 'move';
                                            }}
                                            onDrop={() => drop(group.items, rundown.id)}
                                        />
                                    ))}
                                </ul>
                            </div>
                        ))}
                </div>
            )}
        </div>
    );
}

function RundownFields({ data, setData, errors, lineup, days, isMultiDay }) {
    const inputClass =
        'w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all';

    return (
        <>
            <div className={`space-y-1.5 ${isMultiDay ? '' : 'sm:col-span-2'}`}>
                <label className="block text-sm font-medium text-slate-700">Band / Pengisi Acara</label>
                <select
                    value={data.performer_id}
                    onChange={(event) => setData('performer_id', event.target.value)}
                    className={inputClass}
                >
                    <option value="">Non-band / sesi lainnya</option>
                    {lineup.map((performer) => (
                        <option key={performer.id} value={performer.id}>
                            {performer.name}
                        </option>
                    ))}
                </select>
                {lineup.length === 0 && (
                    <p className="text-xs text-slate-400">Belum ada band di line-up. Tambahkan lewat Edit Event.</p>
                )}
                {errors.performer_id && <p className="text-sm text-rose-600">{errors.performer_id}</p>}
            </div>

            {isMultiDay && (
                <div className="space-y-1.5">
                    <label className="block text-sm font-medium text-slate-700">Hari</label>
                    <select
                        value={data.event_day_id}
                        onChange={(event) => setData('event_day_id', event.target.value)}
                        className={inputClass}
                    >
                        {days.map((day) => (
                            <option key={day.id} value={day.id}>
                                Day {day.day_number} {'\u00b7'} {formatDate(day.date)}
                            </option>
                        ))}
                    </select>
                    {errors.event_day_id && <p className="text-sm text-rose-600">{errors.event_day_id}</p>}
                </div>
            )}

            <div className="space-y-1.5 sm:col-span-2">
                <label className="block text-sm font-medium text-slate-700">Nama Panggung</label>
                <input
                    type="text"
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    placeholder="cth: Main Set / Opening DJ"
                    className={inputClass}
                />
                <p className="text-xs text-slate-400">Nama yang tampil di jadwal pembeli. Kosongkan jika ingin memakai nama band.</p>
                {errors.title && <p className="text-sm text-rose-600">{errors.title}</p>}
            </div>

            <div className="grid grid-cols-2 gap-4 sm:col-span-2">
                <div className="space-y-1.5">
                    <label className="block text-sm font-medium text-slate-700">Jam Mulai</label>
                    <input
                        type="time"
                        value={data.start_time}
                        onChange={(event) => setData('start_time', event.target.value)}
                        className={inputClass}
                    />
                    {errors.start_time && <p className="text-sm text-rose-600">{errors.start_time}</p>}
                </div>
                <div className="space-y-1.5">
                    <label className="block text-sm font-medium text-slate-700">Jam Selesai (opsional)</label>
                    <input
                        type="time"
                        value={data.end_time}
                        onChange={(event) => setData('end_time', event.target.value)}
                        className={inputClass}
                    />
                    {errors.end_time && <p className="text-sm text-rose-600">{errors.end_time}</p>}
                </div>
            </div>

            <div className="space-y-1.5 sm:col-span-2">
                <label className="block text-sm font-medium text-slate-700">Catatan Sesi (opsional)</label>
                <input
                    type="text"
                    value={data.description}
                    onChange={(event) => setData('description', event.target.value)}
                    placeholder="Keterangan singkat sesi"
                    className={inputClass}
                />
                {errors.description && <p className="text-sm text-rose-600">{errors.description}</p>}
            </div>
        </>
    );
}

function RundownRow({
    event,
    rundown,
    lineup,
    days,
    isMultiDay,
    isFirst,
    isLast,
    isDragging,
    onMoveUp,
    onMoveDown,
    onDragStart,
    onDragEnd,
    onDragOver,
    onDrop,
}) {
    const [editing, setEditing] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        performer_id: rundown.performer_id ? String(rundown.performer_id) : '',
        event_day_id: rundown.event_day_id ? String(rundown.event_day_id) : '',
        title: rundown.title ?? '',
        start_time: rundown.start_time,
        end_time: rundown.end_time ?? '',
        description: rundown.description ?? '',
    });

    function save(eventSubmit) {
        eventSubmit.preventDefault();
        put(`/organizer/events/${event.id}/rundowns/${rundown.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    }

    function remove() {
        router.delete(`/organizer/events/${event.id}/rundowns/${rundown.id}`, { preserveScroll: true });
    }

    if (editing) {
        return (
            <li className="rounded-lg border border-indigo-200 bg-indigo-50/40 p-4">
                <form onSubmit={save} className="grid gap-3 sm:grid-cols-2">
                    <RundownFields
                        data={data}
                        setData={setData}
                        errors={errors}
                        lineup={lineup}
                        days={days}
                        isMultiDay={isMultiDay}
                    />
                    <div className="flex items-center gap-2 sm:col-span-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-all shadow-sm disabled:opacity-60"
                        >
                            {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </button>
                        <button
                            type="button"
                            onClick={() => setEditing(false)}
                            className="text-sm font-medium text-slate-600 hover:text-slate-800"
                        >
                            Batal
                        </button>
                    </div>
                </form>
            </li>
        );
    }

    return (
        <li
            draggable
            onDragStart={onDragStart}
            onDragEnd={onDragEnd}
            onDragOver={onDragOver}
            onDrop={onDrop}
            className={`flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 ${
                isDragging ? 'border-indigo-300 bg-indigo-50/40 opacity-60' : 'border-slate-200 bg-white'
            }`}
        >
            <div className="flex min-w-0 items-start gap-3">
                <span
                    className="mt-0.5 cursor-grab text-slate-300 hover:text-slate-500"
                    title="Geser untuk mengubah urutan"
                    aria-hidden="true"
                >
                    <GripVertical className="h-4 w-4" />
                </span>
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                            {rundown.start_time}
                            {rundown.end_time ? ` - ${rundown.end_time}` : ''}
                        </span>
                        <p className="font-medium text-slate-900">{rundown.title ?? rundown.performer_name}</p>
                        {rundown.performer_genre && (
                            <span className="inline-flex items-center rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs font-medium text-slate-600">
                                {rundown.performer_genre}
                            </span>
                        )}
                    </div>
                    {rundown.title && rundown.performer_name && (
                        <p className="mt-1 text-sm text-slate-600">{rundown.performer_name}</p>
                    )}
                    {rundown.description && <p className="mt-1 text-sm text-slate-500">{rundown.description}</p>}
                </div>
            </div>
            <div className="flex items-center gap-1">
                <button
                    type="button"
                    onClick={onMoveUp}
                    disabled={isFirst}
                    title="Naikkan"
                    aria-label={`Naikkan ${rundown.title ?? rundown.performer_name ?? 'sesi'}`}
                    className="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-30 disabled:hover:bg-transparent"
                >
                    <ChevronUp className="h-4 w-4" />
                </button>
                <button
                    type="button"
                    onClick={onMoveDown}
                    disabled={isLast}
                    title="Turunkan"
                    aria-label={`Turunkan ${rundown.title ?? rundown.performer_name ?? 'sesi'}`}
                    className="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-30 disabled:hover:bg-transparent"
                >
                    <ChevronDown className="h-4 w-4" />
                </button>
                <button
                    type="button"
                    onClick={() => setEditing(true)}
                    className="ml-2 text-sm font-medium text-indigo-600 hover:text-indigo-700"
                >
                    Edit
                </button>
                <button
                    type="button"
                    onClick={remove}
                    className="text-sm font-medium text-rose-600 hover:text-rose-700"
                >
                    Hapus
                </button>
            </div>
        </li>
    );
}