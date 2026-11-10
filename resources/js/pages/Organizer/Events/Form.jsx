import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/components/AppLayout';
import StatusBadge from '@/components/StatusBadge';
import { addDays } from '@/lib/format';

const MAX_DAYS = 30;

function buildDays(count, current, startDate) {
    return Array.from({ length: count }, (_, index) => {
        const number = index + 1;
        const existing = current.find((day) => Number(day.day_number) === number);
        const day = existing ?? { day_number: number, open_gate: '', close_gate: '' };

        return { ...day, date: startDate ? addDays(startDate, index) : (day.date ?? '') };
    });
}

export default function Form({ event }) {
    const isEdit = Boolean(event);

    const { data, setData, post, put, processing, errors, transform } = useForm({
        title: event?.title ?? '',
        description: event?.description ?? '',
        venue_name: event?.venue_name ?? '',
        city: event?.city ?? '',
        address: event?.address ?? '',
        event_date: event?.event_date ?? '',
        duration_type: event?.duration_type ?? 'single',
        total_days: event?.total_days ?? 1,
        event_days: event?.event_days ?? [],
        start_date: event?.event_days?.[0]?.date ?? event?.event_date ?? '',
        start_time: event?.start_time ?? '19:00',
        end_time: event?.end_time ?? '',
        open_gate: event?.open_gate ?? '',
        close_gate: event?.close_gate ?? '',
        banner: null,
        performers: event?.performers?.join('\n') ?? '',
        status: 'draft',
    });

    const [previewUrl, setPreviewUrl] = useState(event?.banner_url ?? null);

    const dayOneHasGate = Boolean(data.event_days[0]?.open_gate || data.event_days[0]?.close_gate);

    transform((previous) => ({
        ...previous,
        performers: previous.performers
            .split('\n')
            .map((name) => name.trim())
            .filter((name) => name !== ''),
    }));

    function handleBannerChange(file) {
        if (!file) return;
        setData('banner', file);
        setPreviewUrl(URL.createObjectURL(file));
    }

    function handleDurationType(value) {
        if (value === 'multi_day' && data.event_days.length === 0) {
            const startDate = data.event_date || '';

            setData((data) => ({
                ...data,
                duration_type: value,
                start_date: startDate,
                event_days: buildDays(data.total_days, data.event_days, startDate),
            }));
            return;
        }

        setData('duration_type', value);
    }

    function handleTotalDays(value) {
        const days = Math.min(MAX_DAYS, Math.max(0, Number(value) || 0));

        setData((data) => ({
            ...data,
            total_days: days,
            event_days: buildDays(days, data.event_days, data.start_date),
        }));
    }

    function handleStartDate(value) {
        setData((data) => ({
            ...data,
            start_date: value,
            event_days: buildDays(data.event_days.length, data.event_days, value),
        }));
    }

    function updateDay(index, key, value) {
        setData('event_days', data.event_days.map((day, i) => (i === index ? { ...day, [key]: value } : day)));
    }

    function pullGateTimes(index) {
        const source = data.event_days[0];

        setData(
            'event_days',
            data.event_days.map((day, i) =>
                i === index
                    ? { ...day, open_gate: source.open_gate ?? '', close_gate: source.close_gate ?? '' }
                    : day,
            ),
        );
    }

    function pullGateTimesToEveryDay() {
        const source = data.event_days[0];

        setData(
            'event_days',
            data.event_days.map((day, i) =>
                i === 0
                    ? day
                    : { ...day, open_gate: source.open_gate ?? '', close_gate: source.close_gate ?? '' },
            ),
        );
    }

    function dayError(index, key) {
        return errors[`event_days.${index}.${key}`];
    }

    function submit(eventSubmit) {
        eventSubmit.preventDefault();
        const url = isEdit ? `/organizer/events/${event.id}` : '/organizer/events';
        const submitter = isEdit ? put : post;
        submitter(url, { preserveScroll: true });
    }

    return (
        <AppLayout>
            <div className="mb-8">
                <Link href="/organizer/events" className="text-sm font-medium text-indigo-600 hover:text-indigo-700">
                    ← Kembali ke Event Saya
                </Link>
                <h1 className="mt-2 text-2xl font-semibold text-slate-900">
                    {isEdit ? 'Edit Event' : 'Buat Event Baru'}
                </h1>
                <p className="mt-1 text-sm text-slate-500">
                    Lengkapi detail event. Pilih untuk menyimpan sebagai draft atau langsung mengajukan verifikasi.
                </p>
            </div>

            {isEdit && event.status === 'rejected' && (
                <div className="mb-6 rounded-lg border border-rose-200 bg-rose-50/60 px-4 py-3 text-sm text-rose-700">
                    Event ditolak: {event.rejection_reason} - perbaiki lalu ajukan ulang.
                </div>
            )}

            <form onSubmit={submit} className="max-w-3xl space-y-6">
                <Section title="Detail Event" note={isEdit ? `Status saat ini: ${event.status_label}` : undefined}>
                    {isEdit && <StatusBadge status={event.status} />}
                    <Field label="Nama Event" value={data.title} onChange={(value) => setData('title', value)} error={errors.title} placeholder="cth: Konser Musik Nusantara" />
                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">Deskripsi</span>
                        <textarea
                            value={data.description}
                            onChange={(eventChange) => setData('description', eventChange.target.value)}
                            rows={5}
                            placeholder="Ceritakan tentang event ini..."
                            className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                        />
                        {errors.description && <p className="text-sm text-rose-600">{errors.description}</p>}
                    </label>
                </Section>

                <Section title="Poster Event" note="Opsional. JPG / PNG / WEBP, maks. 2 MB.">
                    <label className="block space-y-1.5">
                        <span className="block text-sm font-medium text-slate-700">Unggah Poster</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(eventChange) => handleBannerChange(eventChange.target.files[0])}
                            className="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2.5 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        {errors.banner && <p className="text-sm text-rose-600">{errors.banner}</p>}
                    </label>
                    <div className="mt-2">
                        {previewUrl ? (
                            <img
                                src={previewUrl}
                                alt="Preview Poster"
                                className="h-40 w-full object-cover rounded-lg border border-slate-200"
                            />
                        ) : (
                            <div className="h-24 rounded-lg border border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-sm text-slate-400">
                                Belum ada poster
                            </div>
                        )}
                    </div>
                </Section>

                <Section title="Lokasi & Waktu">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Venue" value={data.venue_name} onChange={(value) => setData('venue_name', value)} error={errors.venue_name} placeholder="cth: Istora Senayan" />
                        <Field label="Kota" value={data.city} onChange={(value) => setData('city', value)} error={errors.city} placeholder="cth: Jakarta" />
                    </div>
                    <Field label="Alamat Lengkap" value={data.address} onChange={(value) => setData('address', value)} error={errors.address} placeholder="Jalan, kompleks, patokan" />

                    <div className="space-y-2">
                        <p className="text-sm font-medium text-slate-700">Durasi Event</p>
                        {[
                            { value: 'single', label: 'Single Day', description: 'Event digelar satu hari' },
                            { value: 'multi_day', label: 'Multi-Day', description: 'Event digelar beberapa hari (mis. 3 hari konser)' },
                        ].map((option) => (
                            <label
                                key={option.value}
                                className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-all ${
                                    data.duration_type === option.value
                                        ? 'border-indigo-600 bg-indigo-50/60 ring-1 ring-indigo-600'
                                        : 'border-slate-300 bg-white hover:border-slate-400'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="duration_type"
                                    value={option.value}
                                    checked={data.duration_type === option.value}
                                    onChange={() => handleDurationType(option.value)}
                                    className="mt-1 h-4 w-4 accent-indigo-600"
                                />
                                <span>
                                    <span className="block text-sm font-medium text-slate-800">{option.label}</span>
                                    <span className="block text-xs text-slate-500">{option.description}</span>
                                </span>
                            </label>
                        ))}
                        {errors.duration_type && <p className="text-sm text-rose-600">{errors.duration_type}</p>}
                    </div>

                    {data.duration_type === 'single' ? (
                        <>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field label="Tanggal Event" type="date" value={data.event_date} onChange={(value) => setData('event_date', value)} error={errors.event_date} />
                                <Field label="Jam Mulai Acara" type="time" value={data.start_time} onChange={(value) => setData('start_time', value)} error={errors.start_time} />
                                <Field label="Jam Selesai Acara (opsional)" type="time" value={data.end_time} onChange={(value) => setData('end_time', value)} error={errors.end_time} />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Jam Buka Pintu (opsional)" type="time" value={data.open_gate} onChange={(value) => setData('open_gate', value)} error={errors.open_gate} />
                                <Field label="Jam Tutup Pintu (opsional)" type="time" value={data.close_gate} onChange={(value) => setData('close_gate', value)} error={errors.close_gate} />
                            </div>
                            <p className="text-xs text-slate-400">
                                Jam mulai acara berbeda dari jam pintu. Scan tiket hanya diterima setelah pintu dibuka dan sebelum pintu ditutup. Tanggal pintu mengikuti tanggal event, cukup isi jamnya (WIB). Jam tutup tidak boleh sebelum jam buka.
                            </p>
                        </>
                    ) : (
                        <>
                            <Field
                                label="Tanggal Mulai"
                                type="date"
                                value={data.start_date}
                                onChange={handleStartDate}
                                hint="Tanggal tiap hari diisi otomatis berurutan dari tanggal ini."
                            />
                            <Field
                                label="Jumlah Hari"
                                type="number"
                                min="1"
                                max={MAX_DAYS}
                                value={data.total_days}
                                onChange={(value) => handleTotalDays(value)}
                                error={errors.total_days}
                                placeholder="3"
                                hint={`Maksimal ${MAX_DAYS} hari. Atur jadwal tiap hari di bawah.`}
                            />
                            <div className="space-y-3">
                                {data.event_days.map((day, index) => (
                                    <div key={day.day_number} className="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-sm font-medium text-slate-700">Day {day.day_number}</p>
                                            {index === 0 ? (
                                                data.event_days.length > 1 && (
                                                    <button
                                                        type="button"
                                                        onClick={pullGateTimesToEveryDay}
                                                        disabled={!dayOneHasGate}
                                                        className="text-xs font-medium text-indigo-600 hover:text-indigo-700 disabled:cursor-not-allowed disabled:text-slate-400"
                                                    >
                                                        Tarik ke semua hari
                                                    </button>
                                                )
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => pullGateTimes(index)}
                                                    disabled={!dayOneHasGate}
                                                    className="text-xs font-medium text-indigo-600 hover:text-indigo-700 disabled:cursor-not-allowed disabled:text-slate-400"
                                                >
                                                    Tarik jam dari Hari 1
                                                </button>
                                            )}
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-4">
                                            <div className="sm:col-span-1">
                                                <DayField
                                                    label="Tanggal"
                                                    type="date"
                                                    value={day.date}
                                                    onChange={(value) => updateDay(index, 'date', value)}
                                                    error={dayError(index, 'date')}
                                                    readOnly
                                                />
                                            </div>
                                            <div className="sm:col-span-1">
                                                <DayField
                                                    label="Jam Buka Pintu (opsional)"
                                                    type="time"
                                                    value={day.open_gate}
                                                    onChange={(value) => updateDay(index, 'open_gate', value)}
                                                    error={dayError(index, 'open_gate')}
                                                />
                                            </div>
                                            <div className="sm:col-span-1">
                                                <DayField
                                                    label="Jam Tutup Pintu (opsional)"
                                                    type="time"
                                                    value={day.close_gate}
                                                    onChange={(value) => updateDay(index, 'close_gate', value)}
                                                    error={dayError(index, 'close_gate')}
                                                />
                                            </div>
                                        </div>
                                        {errors.event_days && <p className="mt-2 text-sm text-rose-600">{errors.event_days}</p>}
                                    </div>
                                ))}
                            </div>
                            <p className="text-xs text-slate-400">
                                Jam mulai acara berbeda dari jam pintu. Tanggal pintu mengikuti tanggal hari, cukup isi jamnya (WIB). Jam tutup tidak boleh sebelum jam buka di hari yang sama. Kalau semua hari memakai jam pintu yang sama, isi Hari 1 lalu klik "Tarik ke semua hari".
                            </p>
                        </>
                    )}
                </Section>

                <Section title="Performer / Artis" note="Satu nama per baris.">
                    <label className="block space-y-1.5">
                        <textarea
                            value={data.performers}
                            onChange={(eventChange) => setData('performers', eventChange.target.value)}
                            rows={4}
                            placeholder={'Nama Band\nNama Artis Solo'}
                            className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
                        />
                        {errors.performers && <p className="text-sm text-rose-600">{errors.performers}</p>}
                    </label>
                </Section>

                <Section title="Simpan Sebagai">
                    <div className="grid gap-3 sm:grid-cols-2">
                        {[
                            { value: 'draft', label: 'Draft', description: 'Simpan tanpa mengajukan ke admin' },
                            { value: 'pending', label: 'Ajukan Verifikasi', description: 'Kirim langsung untuk peninjauan admin' },
                        ].map((option) => (
                            <label
                                key={option.value}
                                className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-all ${
                                    data.status === option.value
                                        ? 'border-indigo-600 bg-indigo-50/60 ring-1 ring-indigo-600'
                                        : 'border-slate-300 bg-white hover:border-slate-400'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="status"
                                    value={option.value}
                                    checked={data.status === option.value}
                                    onChange={() => setData('status', option.value)}
                                    className="mt-1 h-4 w-4 accent-indigo-600"
                                />
                                <span>
                                    <span className="block text-sm font-medium text-slate-800">{option.label}</span>
                                    <span className="block text-xs text-slate-500">{option.description}</span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {errors.status && <p className="mt-2 text-sm text-rose-600">{errors.status}</p>}
                </Section>

                <div className="flex items-center gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 px-6 rounded-lg transition-all shadow-sm disabled:opacity-60"
                    >
                        {processing ? 'Menyimpan...' : isEdit ? 'Simpan Perubahan' : 'Buat Event'}
                    </button>
                    <Link
                        href="/organizer/events"
                        className="bg-white hover:bg-slate-100 text-slate-700 border border-slate-300 py-2.5 px-5 rounded-lg transition-all"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AppLayout>
    );
}

function Section({ title, note, children }) {
    return (
        <div className="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-sm p-5 sm:p-6">
            <div className="mb-4 flex items-center gap-2">
                <h2 className="font-semibold text-slate-900">{title}</h2>
                {note && <span className="text-xs text-slate-400">{note}</span>}
            </div>
            <div className="space-y-4">{children}</div>
        </div>
    );
}

function Field({ label, type = 'text', value, onChange, error, placeholder, min, max, hint }) {
    return (
        <label className="block">
            <span className="block text-sm font-medium text-slate-700">{label}</span>
            <input
                type={type}
                min={min}
                max={max}
                value={value}
                onChange={(eventChange) => onChange(eventChange.target.value)}
                placeholder={placeholder}
                className="w-full bg-white border border-slate-300 text-slate-900 placeholder-slate-400 rounded-lg px-4 py-2.5 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all"
            />
            {hint && <span className="mt-1 block text-xs text-slate-400">{hint}</span>}
            {error && <p className="text-sm text-rose-600">{error}</p>}
        </label>
    );
}

function DayField({ label, type = 'text', value, onChange, error, readOnly = false }) {
    return (
        <label className="block">
            <span className="block text-[11px] font-medium text-slate-500">{label}</span>
            <input
                type={type}
                value={value}
                onChange={(eventChange) => onChange(eventChange.target.value)}
                readOnly={readOnly}
                className={`w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-all text-sm ${
                    readOnly ? 'cursor-not-allowed bg-slate-100 text-slate-500' : 'bg-white text-slate-900'
                }`}
            />
            {error && <p className="text-xs text-rose-600">{error}</p>}
        </label>
    );
}